<?php declare(strict_types=1);

header('Content-Type: application/json');

$path = tempnam(sys_get_temp_dir(), 'batch-json-receiver-');
$input = fopen('php://input', 'rb');
$output = fopen($path, 'wb');
stream_copy_to_stream($input, $output);
fclose($input);
fclose($output);

echo json_encode([
    'sha256' => hash_file('sha256', $path),
    'bytes' => filesize($path),
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'contentType' => $_SERVER['CONTENT_TYPE'] ?? '',
], JSON_THROW_ON_ERROR);
unlink($path);
