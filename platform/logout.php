<?php
require_once __DIR__ . '/../includes/platform.php';
platformLogout();
header('Location: ' . BASE_PATH . '/platform/login.php');
exit;
