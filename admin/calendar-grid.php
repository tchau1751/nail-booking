<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/layout_start.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>

<div style="background: red; color: white; padding: 40px; text-align: center; font-size: 24px; font-weight: bold;">
  ✅ NEW CALENDAR FILE IS WORKING!
  <br/>Current Time: <?php echo date('Y-m-d H:i:s'); ?>
</div>

<div style="background: blue; color: white; padding: 20px; margin: 20px;">
  <h2>Calendar Grid:</h2>
  <div style="display: grid; grid-template-columns: 50px repeat(3, 1fr); background: white; color: black;">
    <div style="border: 1px solid #ccc; padding: 10px;">Time</div>
    <div style="border: 1px solid #ccc; padding: 10px;">T1</div>
    <div style="border: 1px solid #ccc; padding: 10px;">T2</div>
    <div style="border: 1px solid #ccc; padding: 10px;">Unassigned</div>

    <?php for ($h = 8; $h <= 18; $h++): ?>
      <div style="border: 1px solid #ccc; padding: 10px; background: #f0f0f0;"><?= $h ?>:00</div>
      <div style="border: 1px solid #ccc; padding: 10px; min-height: 80px;"></div>
      <div style="border: 1px solid #ccc; padding: 10px; min-height: 80px;"></div>
      <div style="border: 1px solid #ccc; padding: 10px; min-height: 80px;"></div>
    <?php endfor; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
