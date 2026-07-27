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
function settings(): array {
    return fetchOne('SELECT * FROM business_settings WHERE id=1') ?? [];
}
