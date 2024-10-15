<?php
header('Content-Type: application/json');

require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['data'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$data = $input['data'];

$encrypted = openssl_encrypt(
    $data,
    'AES-256-CBC',
    ENCRYPTION_KEY,
    0,
    ENCRYPTION_IV
);

if ($encrypted === false) {
    echo json_encode(['error' => 'Encryption failed: ' . openssl_error_string()]);
    exit;
}

echo json_encode(['encrypted' => base64_encode($encrypted)]);
