<?php
session_start();
require_once 'db_connect.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Usuario no autenticado']));
}

// Verificar el token CSRF
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    die(json_encode(['error' => 'Token CSRF inválido']));
}

// Obtener y validar los datos del enlace
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['url_original']) || !isset($data['url'])) {
    die(json_encode(['error' => 'Datos del enlace inválidos']));
}

$url_original = filter_var($data['url_original'], FILTER_VALIDATE_URL);
$url_corta = filter_var($data['url'], FILTER_SANITIZE_URL);
$user_id = $_SESSION['user_id'];

if (!$url_original || !$url_corta) {
    die(json_encode(['error' => 'URLs inválidas']));
}

// Extraer el código de la URL corta
$codigo = basename($url_corta);

// Verificar si el enlace ya existe para este usuario
$stmt = $pdo->prepare("SELECT id FROM enlaces WHERE codigo = ? AND user_id = ?");
$stmt->execute([$codigo, $user_id]);
if ($stmt->fetch()) {
    die(json_encode(['error' => 'Este enlace ya está asociado a tu cuenta']));
}

// Insertar el enlace en la base de datos
$stmt = $pdo->prepare("INSERT INTO enlaces (codigo, url_original, user_id) VALUES (?, ?, ?)");
if ($stmt->execute([$codigo, $url_original, $user_id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Error al asociar el enlace']);
}
