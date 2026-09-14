<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$today  = date('Y-m-d');
$month  = date('Y-m');
$tid    = tenantId();

echo json_encode([
    'success' => true,
    'data' => [
        'total_appointments'   => (int)fetchOne('SELECT COUNT(*) c FROM appointments WHERE tenant_id=?', [$tid])['c'],
        'pending'              => (int)fetchOne("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND status='pending'", [$tid])['c'],
        'confirmed_today'      => (int)fetchOne("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND status='confirmed' AND appointment_date=?", [$tid, $today])['c'],
        'today_appointments'   => (int)fetchOne("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND appointment_date=?", [$tid, $today])['c'],
        'month_revenue'        => (float)(fetchOne("SELECT COALESCE(SUM(s.price),0) r FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? AND a.status='completed' AND DATE_FORMAT(a.appointment_date,'%Y-%m')=?", [$tid, $month])['r'] ?? 0),
        'active_services'      => (int)fetchOne("SELECT COUNT(*) c FROM services WHERE tenant_id=? AND is_active=1", [$tid])['c'],
        'active_technicians'   => (int)fetchOne("SELECT COUNT(*) c FROM technicians WHERE tenant_id=? AND is_active=1", [$tid])['c'],
        'upcoming'             => fetchAll("SELECT a.*,s.name service_name,t.name technician_name FROM appointments a JOIN services s ON s.id=a.service_id LEFT JOIN technicians t ON t.id=a.technician_id WHERE a.tenant_id=? AND a.appointment_date>=? AND a.status IN('pending','confirmed') ORDER BY a.appointment_date,a.start_time LIMIT 8", [$tid, $today]),
    ]
]);
