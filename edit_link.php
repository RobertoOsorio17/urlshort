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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $url = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);
    
    if (!$id || !$url) {
        die(json_encode(['error' => 'Datos inválidos']));
    }

    $expirationDateTime = null;
    if (isset($_POST['expirationDate']) && !empty($_POST['expirationDate'])) {
        $expirationDate = $_POST['expirationDate'];
        $expirationTime = isset($_POST['expirationTime']) && !empty($_POST['expirationTime']) 
            ? $_POST['expirationTime'] 
            : '23:59:59';
        $expirationDateTime = date('Y-m-d H:i:s', strtotime("$expirationDate $expirationTime"));
    }

    $passwordUpdate = "";
    $passwordParams = [];
    if (isset($_POST['removePassword']) && $_POST['removePassword'] === '1') {
        $passwordUpdate = ", password = NULL";
    } elseif (isset($_POST['password']) && !empty($_POST['password'])) {
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $passwordUpdate = ", password = ?";
        $passwordParams[] = $hashedPassword;
    }

    $stmt = $pdo->prepare("UPDATE enlaces SET url_original = ?, expiration_date = ? $passwordUpdate WHERE id = ? AND user_id = ?");
    $params = [$url, $expirationDateTime];
    $params = array_merge($params, $passwordParams, [$id, $user_id]);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'expirationDateTime' => $expirationDateTime,
            'hasPassword' => !empty($passwordParams) || (isset($_POST['removePassword']) && $_POST['removePassword'] !== '1')
        ]);
    } else {
        echo json_encode(['error' => 'No se pudo actualizar el enlace']);
    }
}
