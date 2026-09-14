<?php
// ============================================================
//  Static check: every SQL statement that touches a salon's table
//  should mention tenant_id.
//
//  Reads the source, runs nothing. Works a PHP statement at a time,
//  so SQL glued together with "." still counts as one query, and a
//  $where built a few lines up counts if that assignment names
//  tenant_id. Statements just after unscoped( are deliberate and
//  skipped. What is left is worth a look.
//
//    D:\xampp\php\php.exe tests\tenant_lint.php
//
//  Exit code 0 means nothing was found.
// ============================================================
chdir(dirname(__DIR__));
require 'includes/tenant.php';   // for TENANT_TABLES only; nothing is called

// Places that talk to the schema itself, not to a salon's rows, and two
// stray pages that do not use the app's database helpers at all.
const LINT_SKIP = ['includes/schema.php', 'includes/tenant.php', 'pos/includes/migrate.php',
                   'tests/', 'config/', '.claude/', 'kiosk-login-check.php', 'admin/sms-'];

$found = 0;

/** Does some assignment to $name in this file carry tenant_id, directly or one variable deep? */
function varIsScoped(string $name, string $src, int $depth = 0): bool {
    $pattern = '/\$' . preg_quote($name, '/') . '\s*(?:\[[^\]]*\])?\s*\.?=(?!=)([^;]*);/s';
    if (!preg_match_all($pattern, $src, $m)) return false;
    foreach ($m[1] as $rhs) {
        if (stripos($rhs, 'tenant_id') !== false) return true;
        if ($depth === 0 && preg_match_all('/\$([A-Za-z_]\w*)/', $rhs, $inner)) {
            foreach ($inner[1] as $v) if ($v !== $name && varIsScoped($v, $src, 1)) return true;
        }
    }
    return false;
}

function lintStatement(string $path, array $strings, array $vars, string $src, array $unscopedLines): void {
    global $found;
    if (!$strings) return;
    $sql = implode(' ', array_column($strings, 1));
    if (!preg_match_all('/\b(?:FROM|JOIN|INTO|UPDATE)\s+`?([A-Za-z_][A-Za-z0-9_]*)`?/i', $sql, $m)) return;
    $hits = array_values(array_unique(array_intersect(array_map('strtolower', $m[1]), TENANT_TABLES)));
    if (!$hits || stripos($sql, 'tenant_id') !== false) return;
    foreach (array_unique($vars) as $v) if (varIsScoped($v, $src)) return;

    $line = $strings[0][0];
    foreach ($unscopedLines as $u) if ($line >= $u && $line <= $u + 3) return;

    $found++;
    printf("%s:%d  [%s]  %s\n", $path, $line, implode(',', $hits),
           substr(preg_replace('/\s+/', ' ', trim($sql, "'\" ")), 0, 110));
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = str_replace('\\', '/', substr($file->getPathname(), 2));
    if (substr($path, -4) !== '.php') continue;
    foreach (LINT_SKIP as $skip) if (strpos($path, $skip) === 0) continue 2;

    $src = file_get_contents($file->getPathname());
    $unscopedLines = [];
    foreach (explode("\n", $src) as $i => $text) {
        if (strpos($text, 'unscoped(') !== false) $unscopedLines[] = $i + 1;
    }

    // Collect every string literal and variable in a statement; a statement
    // ends at ; { or } outside a string. Double-quoted strings with variables
    // in them arrive in pieces and are stitched back together.
    $strings = []; $vars = [];
    $inString = false; $buffer = ''; $startLine = 0; $lastLine = 1;
    foreach (token_get_all($src) as $tok) {
        if (is_string($tok)) {
            if ($tok === '"') {
                if ($inString) { $strings[] = [$startLine, $buffer]; $inString = false; }
                else { $inString = true; $buffer = ''; $startLine = $lastLine; }
            } elseif ($inString) {
                $buffer .= $tok;
            } elseif ($tok === ';' || $tok === '{' || $tok === '}') {
                lintStatement($path, $strings, $vars, $src, $unscopedLines);
                $strings = []; $vars = [];
            }
            continue;
        }
        [$id, $text, $line] = $tok;
        $lastLine = $line + substr_count($text, "\n");
        if ($id === T_VARIABLE) $vars[] = substr($text, 1);
        if ($inString) { $buffer .= $text; continue; }
        if ($id === T_CONSTANT_ENCAPSED_STRING) $strings[] = [$line, $text];
        if ($id === T_CLOSE_TAG) { lintStatement($path, $strings, $vars, $src, $unscopedLines); $strings = []; $vars = []; }
    }
}

echo $found ? "\n$found statement(s) to look at\n" : "No unscoped SQL found\n";
exit($found ? 1 : 0);
