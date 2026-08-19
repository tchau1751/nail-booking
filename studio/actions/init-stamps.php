<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = get_db();

try {
    // Try to add the column
    $pdo->exec("ALTER TABLE clients ADD COLUMN stamp_count INT DEFAULT 0");
    echo "SUCCESS: stamp_count column added!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column already exists - that's OK!";
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}
?>