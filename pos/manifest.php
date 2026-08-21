<?php
// Web app manifest. Served by PHP so the paths follow SUBFOLDER even if the
// salon renames the htdocs folder.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$name = 'Nail Salon POS';
try {
    $s = settings();
    if (!empty($s['business_name'])) $name = $s['business_name'];
} catch (Throwable $e) {
    // Database not up yet — the manifest still has to serve.
}

$base = BASE_PATH . '/pos/';
echo json_encode([
    'name'              => $name . ' POS',
    'short_name'        => 'POS',
    'description'       => 'Point of sale, check-in queue and reports for the salon counter.',
    'id'                => $base,
    'start_url'         => $base,
    'scope'             => $base,
    'display'           => 'standalone',
    'orientation'       => 'any',
    'background_color'  => '#f6f3f1',
    'theme_color'       => '#3a2a24',
    'categories'        => ['business', 'productivity'],
    'icons' => [
        ['src' => $base . 'assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . 'assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . 'assets/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    // Long-press the installed icon to jump straight to a screen.
    'shortcuts' => [
        ['name' => 'Register',  'url' => $base,               'icons' => [['src' => $base . 'assets/icons/icon-192.png', 'sizes' => '192x192']]],
        ['name' => 'Queue',     'url' => $base . 'queue.php',  'icons' => [['src' => $base . 'assets/icons/icon-192.png', 'sizes' => '192x192']]],
        ['name' => 'Check-in',  'url' => $base . 'kiosk.php',  'icons' => [['src' => $base . 'assets/icons/icon-192.png', 'sizes' => '192x192']]],
        ['name' => 'Reports',   'url' => $base . 'reports.php','icons' => [['src' => $base . 'assets/icons/icon-192.png', 'sizes' => '192x192']]],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
