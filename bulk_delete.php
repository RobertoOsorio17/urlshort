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

if (!isset($data['ids']) || !is_array($data['ids'])) {
    echo json_encode(['success' => false, 'error' => 'IDs inválidos']);
    exit;
}

$ids = array_map('intval', $data['ids']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $pdo->prepare("DELETE FROM enlaces WHERE id IN ($placeholders) AND user_id = ?");
$stmt->execute([...$ids, $user_id]);

echo json_encode(['success' => true]);
