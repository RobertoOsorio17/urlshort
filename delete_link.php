<?php
session_start();
require_once 'db_connect.php';
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $linkId = $data['id'] ?? null;

    if ($linkId) {
        try {
            $stmt = $pdo->prepare("DELETE FROM enlaces WHERE id = ?");
            $stmt->execute([$linkId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No se encontró el enlace']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID de enlace no proporcionado']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
