<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Clients Page Test</h1>";

echo "<h2>1. Testing includes...</h2>";
try {
  require_once __DIR__ . '/includes/layout_start.php';
  echo "✅ layout_start.php loaded<br>";
} catch (Exception $e) {
  echo "❌ Error loading layout_start.php: " . $e->getMessage() . "<br>";
  exit;
}

echo "<h2>2. Testing database...</h2>";
try {
  $pdo = get_db();
  echo "✅ Database connected<br>";
} catch (Exception $e) {
  echo "❌ Database error: " . $e->getMessage() . "<br>";
  exit;
}

echo "<h2>3. Testing query...</h2>";
try {
  $result = $pdo->query("SELECT COUNT(*) as count FROM clients");
  $row = $result->fetch();
  echo "✅ Query successful<br>";
  echo "Total clients: " . $row['count'] . "<br>";
} catch (Exception $e) {
  echo "❌ Query error: " . $e->getMessage() . "<br>";
  exit;
}

echo "<h2>✅ All tests passed!</h2>";
echo "The issue is with the clients.php page display logic.";

require_once __DIR__ . '/includes/layout_end.php';
?>
