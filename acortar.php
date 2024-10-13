<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Usuario no autenticado']));
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['error' => 'Token CSRF inválido']));
}

$user_id = $_SESSION['user_id'];
    
// Validación de URL
$urlOriginal = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);
if (!$urlOriginal) {
    die(json_encode(['error' => 'URL inválida']));
}
    
// Limitación de tasa
if (!isset($_SESSION['last_request'])) {
    $_SESSION['last_request'] = time();
    $_SESSION['request_count'] = 1;
} else {
    $current_time = time();
    if ($current_time - $_SESSION['last_request'] < 60) {
        $_SESSION['request_count']++;
        if ($_SESSION['request_count'] > 5) {
            die(json_encode(['error' => 'Demasiadas solicitudes. Por favor, espere un minuto.']));
        }
    } else {
        $_SESSION['last_request'] = $current_time;
        $_SESSION['request_count'] = 1;
    }
}

// Generar un código único para la URL acortada
do {
    $codigoUnico = substr(md5(uniqid(rand(), true)), 0, 6);
    $stmt = $pdo->prepare("SELECT id FROM enlaces WHERE codigo = ?");
    $stmt->execute([$codigoUnico]);
} while ($stmt->fetch());

// Procesar fecha y hora de expiración
$expirationDateTime = null;
if (isset($_POST['expirationDate']) && !empty($_POST['expirationDate'])) {
    $expirationDate = $_POST['expirationDate'];
    $expirationTime = isset($_POST['expirationTime']) && !empty($_POST['expirationTime']) 
        ? $_POST['expirationTime'] 
        : '23:59:59';
    $expirationDateTime = date('Y-m-d H:i:s', strtotime("$expirationDate $expirationTime"));
}

// Procesar contraseña
$hashedPassword = null;
if (isset($_POST['password']) && !empty($_POST['password'])) {
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

// Insertar la URL original, el código, la fecha/hora de expiración y la contraseña en la base de datos
$stmt = $pdo->prepare("INSERT INTO enlaces (codigo, url_original, user_id, expiration_date, password) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$codigoUnico, $urlOriginal, $user_id, $expirationDateTime, $hashedPassword]);
    
$urlAcortada = 'http://' . $_SERVER['HTTP_HOST'] . '/r.php?c=' . $codigoUnico;
    
echo json_encode([
    'url' => $urlAcortada, 
    'id' => $pdo->lastInsertId(),
    'expirationDateTime' => $expirationDateTime,
    'hasPassword' => $hashedPassword !== null
]);
