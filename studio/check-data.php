<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/../includes/functions.php';
    $pdo = get_db();

    echo "<h2>Database Connection: OK</h2>";

    // Check clients
    $result = $pdo->query('SELECT COUNT(*) as cnt FROM clients');
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total Clients: " . $row['cnt'] . "</p>";

    // Check bookings
    $result = $pdo->query('SELECT COUNT(*) as cnt FROM bookings');
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total Bookings: " . $row['cnt'] . "</p>";

    // Check completed bookings
    $result = $pdo->query('SELECT COUNT(*) as cnt FROM bookings WHERE status = "completed"');
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "<p>Completed Bookings: " . $row['cnt'] . "</p>";

    // Sample clients
    echo "<h3>Sample Clients:</h3><ul>";
    $result = $pdo->query('SELECT full_name, stamp_count FROM clients LIMIT 5');
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "<li>No clients found</li>";
    }
    foreach ($rows as $c) {
        echo "<li>" . $c['full_name'] . " - " . $c['stamp_count'] . " stamps</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "<h2>ERROR:</h2>";
    echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
?>
