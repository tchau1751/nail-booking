<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!current_admin()) {
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!admin_csrf_verify($input['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Session expired — please refresh.'], 419);
}

$id = (int) ($input['id'] ?? 0);
$fullName = trim((string) ($input['full_name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$dobRaw = trim((string) ($input['date_of_birth'] ?? ''));
$notes = trim((string) ($input['notes'] ?? ''));

if ($fullName === '' || mb_strlen($fullName) > 150) {
    json_response(['ok' => false, 'error' => 'Please enter a valid client name.'], 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
}
if ($email === '' && $phone === '') {
    json_response(['ok' => false, 'error' => 'Please provide an email or phone number.'], 422);
}

$dob = null;
if ($dobRaw !== '') {
    $dobObj = DateTime::createFromFormat('Y-m-d', $dobRaw);
    if (!$dobObj || $dobObj > new DateTime('today')) {
        json_response(['ok' => false, 'error' => 'That date of birth doesn\'t look right.'], 422);
    }
    $dob = $dobObj->format('Y-m-d');
}

$pdo = get_db();

if ($id > 0) {
    $stmt = $pdo->prepare('UPDATE clients SET full_name=:full_name, email=:email, phone=:phone, date_of_birth=:dob, notes=:notes WHERE id=:id');
    $stmt->execute(['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'dob' => $dob, 'notes' => $notes ?: null, 'id' => $id]);
} else {
    $stmt = $pdo->prepare('INSERT INTO clients (full_name, email, phone, date_of_birth, notes) VALUES (:full_name, :email, :phone, :dob, :notes)');
    $stmt->execute(['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'dob' => $dob, 'notes' => $notes ?: null]);
    $id = (int) $pdo->lastInsertId();
}

json_response(['ok' => true, 'id' => $id]);
