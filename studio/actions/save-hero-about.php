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

$hero = trim((string) ($input['hero_image_url'] ?? ''));
$heroCard = trim((string) ($input['hero_card_image_url'] ?? ''));
$about = trim((string) ($input['about_image_url'] ?? ''));
$aboutFloat = trim((string) ($input['about_float_image_url'] ?? ''));
$logo = trim((string) ($input['logo_url'] ?? ''));

$pdo = get_db();
$pdo->prepare(
    'UPDATE business_settings SET hero_image_url=:hero, hero_card_image_url=:hero_card,
     about_image_url=:about, about_float_image_url=:about_float, logo_url=:logo
     ORDER BY id ASC LIMIT 1'
)->execute([
    'hero' => $hero, 'hero_card' => $heroCard, 'about' => $about, 'about_float' => $aboutFloat, 'logo' => $logo,
]);

json_response(['ok' => true]);
