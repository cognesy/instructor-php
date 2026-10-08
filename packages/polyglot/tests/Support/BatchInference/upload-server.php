<?php declare(strict_types=1);

header('Content-Type: application/json');

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing uploaded file'], JSON_THROW_ON_ERROR);
    return;
}

echo json_encode([
    'sha256' => hash_file('sha256', $file['tmp_name']),
    'bytes' => filesize($file['tmp_name']),
    'purpose' => $_POST['purpose'] ?? null,
    'contentType' => $_SERVER['CONTENT_TYPE'] ?? '',
], JSON_THROW_ON_ERROR);
