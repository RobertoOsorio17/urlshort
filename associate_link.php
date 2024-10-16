<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Verificar si el usuario está autenticado
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    // Verificar el token CSRF
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
        throw new Exception('Token CSRF inválido');
    }

    error_log('CSRF Token recibido: ' . ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'No recibido'));
    error_log('CSRF Token esperado: ' . ($_SESSION['csrf_token'] ?? 'No establecido'));

    // Obtener y validar los datos del enlace
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['url_original']) || !isset($data['url'])) {
        throw new Exception('Datos del enlace inválidos');
    }

    $url_original = filter_var($data['url_original'], FILTER_VALIDATE_URL);
    $url_corta = filter_var($data['url'], FILTER_SANITIZE_URL);
    $user_id = $_SESSION['user_id'];

    if (!$url_original || !$url_corta) {
        throw new Exception('URLs inválidas');
    }

    // Extraer el código de la URL corta
    $codigo = basename($url_corta);

    // Verificar si el enlace ya existe
    $stmt = $pdo->prepare("SELECT id, user_id FROM enlaces WHERE codigo = ?");
    $stmt->execute([$codigo]);
    $enlace_existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($enlace_existente) {
        if ($enlace_existente['user_id'] == $user_id) {
            throw new Exception('Este enlace ya está asociado a tu cuenta');
        } else if ($enlace_existente['user_id'] !== null) {
            throw new Exception('Este enlace ya está asociado a otra cuenta');
        } else {
            // El enlace existe pero no está asociado a ningún usuario, lo asociamos
            $stmt = $pdo->prepare("UPDATE enlaces SET user_id = ? WHERE codigo = ?");
            if (!$stmt->execute([$user_id, $codigo])) {
                throw new Exception('Error al asociar el enlace a tu cuenta');
            }
        }
    } else {
        // El enlace no existe, lo insertamos
        $stmt = $pdo->prepare("INSERT INTO enlaces (codigo, url_original, user_id) VALUES (?, ?, ?)");
        if (!$stmt->execute([$codigo, $url_original, $user_id])) {
            throw new Exception('Error al insertar el enlace en la base de datos');
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Error en associate_link.php: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
