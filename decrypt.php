<?php
header('Content-Type: application/json');

require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['data'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$encryptedData = base64_decode($input['data']);

$decrypted = openssl_decrypt(
    $encryptedData,
    'AES-256-CBC',
    ENCRYPTION_KEY,
    0,
    ENCRYPTION_IV
);

if ($decrypted === false) {
    echo json_encode(['error' => 'Decryption failed: ' . openssl_error_string()]);
    exit;
}

echo json_encode(['decrypted' => $decrypted]);
