<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
requireRoleJson('manager');
requireAdminUnlockJson();
$rows = fetchAll('SELECT * FROM sms_log WHERE tenant_id=? ORDER BY sent_at DESC LIMIT 200', [tenantId()]);
echo json_encode(['success'=>true,'data'=>$rows]);
