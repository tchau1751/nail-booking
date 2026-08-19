<?php

function handle_image_upload(string $fieldName, string $subdir): ?string
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $file['error'] . ').');
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image must be 5MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    }
    $ext = $allowed[$mime];

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded file.');
    }

    return '/uploads/' . $subdir . '/' . $filename;
}
