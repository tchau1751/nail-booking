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
$name = trim((string) ($input['name'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$duration = (int) ($input['duration_minutes'] ?? 0);
$price = (float) ($input['price'] ?? 0);
$imageUrl = trim((string) ($input['image_url'] ?? ''));
$category = trim((string) ($input['category'] ?? 'Nail Services'));
$sortOrder = (int) ($input['sort_order'] ?? 0);

if ($name === '' || mb_strlen($name) > 120) {
    json_response(['ok' => false, 'error' => 'Please enter a valid service name.'], 422);
}
if ($duration <= 0 || $duration > 600) {
    json_response(['ok' => false, 'error' => 'Duration must be between 1 and 600 minutes.'], 422);
}
if ($price < 0 || $price > 9999) {
    json_response(['ok' => false, 'error' => 'Please enter a valid price.'], 422);
}

$slugBase = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
$pdo = get_db();

if ($id > 0) {
    $stmt = $pdo->prepare(
        'UPDATE services SET name=:name, description=:description, duration_minutes=:duration, price=:price,
         image_url=:image_url, category=:category, sort_order=:sort_order WHERE id=:id'
    );
    $stmt->execute([
        'name' => $name, 'description' => $description, 'duration' => $duration, 'price' => $price,
        'image_url' => $imageUrl, 'category' => $category, 'sort_order' => $sortOrder, 'id' => $id,
    ]);
} else {
    $slug = $slugBase;
    $suffix = 1;
    $check = $pdo->prepare('SELECT COUNT(*) c FROM services WHERE slug = :slug');
    while (true) {
        $check->execute(['slug' => $slug]);
        if ((int) $check->fetch()['c'] === 0) break;
        $suffix++;
        $slug = $slugBase . '-' . $suffix;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO services (name, slug, description, duration_minutes, price, image_url, category, sort_order, is_active)
         VALUES (:name, :slug, :description, :duration, :price, :image_url, :category, :sort_order, 1)'
    );
    $stmt->execute([
        'name' => $name, 'slug' => $slug, 'description' => $description, 'duration' => $duration, 'price' => $price,
        'image_url' => $imageUrl, 'category' => $category, 'sort_order' => $sortOrder,
    ]);
    $id = (int) $pdo->lastInsertId();
}

json_response(['ok' => true, 'id' => $id]);
