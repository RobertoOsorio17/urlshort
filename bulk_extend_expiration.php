<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ids']) || !is_array($data['ids']) || !isset($data['days']) || !is_numeric($data['days'])) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

$ids = array_map('intval', $data['ids']);
$days = intval($data['days']);

// Validar el número de días
if ($days < 1 || $days > 365) {
    echo json_encode(['success' => false, 'error' => 'El número de días debe estar entre 1 y 365']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $pdo->prepare("UPDATE enlaces SET expiration_date = DATE_ADD(GREATEST(expiration_date, CURDATE()), INTERVAL ? DAY) WHERE id IN ($placeholders) AND user_id = ?");
$stmt->execute([$days, ...$ids, $user_id]);

echo json_encode(['success' => true]);
