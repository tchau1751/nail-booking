<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!current_admin()) {
    http_response_code(401);
    exit('Not authenticated.');
}

$pdo = get_db();
$rows = $pdo->query("
    SELECT l.created_at, c.full_name, l.phone, l.message, l.status
    FROM client_message_log l
    LEFT JOIN clients c ON c.id = l.client_id
    WHERE l.message_type = 'promotion'
    ORDER BY l.id DESC
")->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="diamond-nails-promotion-sends-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Sent At', 'Client Name', 'Phone', 'Message', 'Status']);
foreach ($rows as $r) {
    fputcsv($out, [$r['created_at'], $r['full_name'] ?? '', $r['phone'], $r['message'], $r['status']]);
}
fclose($out);
