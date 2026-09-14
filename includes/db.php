<?php
require_once __DIR__ . '/../config/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Keep MySQL's NOW()/CURDATE() on the same clock as PHP. Without this
        // the server's local time wins and "today" can differ from the app's
        // timezone — which silently empties any query filtered on CURDATE().
        $offset = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
        $pdo->exec("SET time_zone = '$offset'");
    }
    return $pdo;
}
function query(string $sql, array $p = []): PDOStatement {
    $s = db()->prepare($sql); $s->execute($p); return $s;
}
function fetchOne(string $sql, array $p = []): ?array {
    $r = query($sql,$p)->fetch(); return $r ?: null;
}
function fetchAll(string $sql, array $p = []): array {
    return query($sql,$p)->fetchAll();
}
function get_db(): PDO {   // alias used by the studio/POS pages
    return db();
}
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function settings(): array {
    return fetchOne('SELECT * FROM business_settings WHERE id=1') ?? [];
}
