<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xlsx_reader.php';

if (!current_admin()) {
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
if (!admin_csrf_verify($_POST['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Session expired — please refresh.'], 419);
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'Please choose a CSV or Excel file to import.'], 422);
}

$tmpPath = $_FILES['file']['tmp_name'];
$originalName = (string) $_FILES['file']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

try {
    if ($ext === 'xlsx') {
        $rows = read_xlsx_rows($tmpPath);
    } elseif ($ext === 'csv' || $ext === 'txt') {
        $rows = [];
        $handle = fopen($tmpPath, 'r');
        if ($handle) {
            // Strip a UTF-8 BOM if present, which otherwise corrupts the first header cell.
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        }
    } else {
        json_response(['ok' => false, 'error' => 'Please upload a .csv or .xlsx file.'], 422);
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Could not read that file: ' . $e->getMessage()], 422);
}

if (empty($rows)) {
    json_response(['ok' => false, 'error' => 'That file appears to be empty.'], 422);
}

// Flexible header matching — different platforms name these columns differently.
$headerRow = array_map(fn($h) => strtolower(trim((string) $h)), array_shift($rows));
$aliases = [
    'name' => ['name', 'full name', 'fullname', 'customer name', 'client name'],
    'phone' => ['phone', 'phone number', 'mobile', 'mobile number', 'cell'],
    'email' => ['email', 'email address'],
    'dob' => ['date of birth', 'dob', 'birthday', 'birth date'],
    'notes' => ['notes', 'note'],
];
$colIndex = [];
foreach ($aliases as $field => $names) {
    foreach ($headerRow as $i => $h) {
        if (in_array($h, $names, true)) {
            $colIndex[$field] = $i;
            break;
        }
    }
}

if (!isset($colIndex['name'])) {
    json_response(['ok' => false, 'error' => 'Could not find a "Name" column in this file. Expected a header like "Name" or "Full Name".'], 422);
}

$pdo = get_db();
$imported = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $row) {
    if (empty(array_filter($row, fn($v) => trim((string) $v) !== ''))) continue;

    $name = trim((string) ($row[$colIndex['name']] ?? ''));
    if ($name === '') { $skipped++; continue; }

    $phone = isset($colIndex['phone']) ? trim((string) ($row[$colIndex['phone']] ?? '')) : '';
    $email = isset($colIndex['email']) ? trim((string) ($row[$colIndex['email']] ?? '')) : '';
    $notes = isset($colIndex['notes']) ? trim((string) ($row[$colIndex['notes']] ?? '')) : '';

    $dob = null;
    if (isset($colIndex['dob'])) {
        $dobRaw = trim((string) ($row[$colIndex['dob']] ?? ''));
        if ($dobRaw !== '') {
            foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y'] as $fmt) {
                $parsed = DateTime::createFromFormat($fmt, $dobRaw);
                if ($parsed) { $dob = $parsed->format('Y-m-d'); break; }
            }
        }
    }

    try {
        if ($phone !== '' || $email !== '') {
            $stmt = $pdo->prepare('SELECT id FROM clients WHERE (email <> "" AND email = :email) OR (phone <> "" AND phone = :phone) LIMIT 1');
            $stmt->execute(['email' => $email, 'phone' => $phone]);
            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }
        }

        $pdo->prepare('INSERT INTO clients (full_name, phone, email, date_of_birth, notes) VALUES (:name, :phone, :email, :dob, :notes)')
            ->execute(['name' => $name, 'phone' => $phone, 'email' => $email, 'dob' => $dob, 'notes' => $notes !== '' ? $notes : null]);
        $imported++;
    } catch (Throwable $e) {
        $errors++;
    }
}

json_response(['ok' => true, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);
