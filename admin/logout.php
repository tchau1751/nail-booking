<?php
require_once __DIR__ . '/../includes/auth.php';
logoutAdmin();
header('Location: ' . BASE_PATH . '/admin/login.php');
exit;
