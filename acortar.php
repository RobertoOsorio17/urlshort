<?php
session_start();
require_once 'db_connect.php';
require_once 'config.php';

header('Content-Type: application/json');

// Función para registrar errores
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, 'error.log');
}

function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function checkRateLimit($user_id) {
    $rate_limit_file = sys_get_temp_dir() . "/rate_limit_$user_id.txt";
    $current_time = time();
    $limit = 100; // Número máximo de solicitudes
    $interval = 3600; // Intervalo de tiempo en segundos (1 hora)

    if (file_exists($rate_limit_file)) {
        $data = unserialize(file_get_contents($rate_limit_file));
        if ($current_time - $data['start_time'] > $interval) {
            $data = ['count' => 1, 'start_time' => $current_time];
        } elseif ($data['count'] >= $limit) {
            throw new Exception('Has excedido el límite de solicitudes. Por favor, inténtalo más tarde.');
        } else {
            $data['count']++;
        }
    } else {
        $data = ['count' => 1, 'start_time' => $current_time];
    }

    file_put_contents($rate_limit_file, serialize($data));
}

try {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'anonymous';
    checkRateLimit($user_id);

    // Validación de URL
    $urlOriginal = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);
    if (!$urlOriginal || !preg_match('/^https?:\/\//', $urlOriginal)) {
        throw new Exception('URL inválida');
    }

    // Generar un código único para la URL acortada
    do {
        $codigoUnico = substr(md5(uniqid(rand(), true)), 0, 6);
        $stmt = $pdo->prepare("SELECT id FROM enlaces WHERE codigo = ?");
        $stmt->execute([$codigoUnico]);
    } while ($stmt->fetch());

    // Procesar fecha y hora de expiración
    $expirationDateTime = null;
    if ($user_id && isset($_POST['expirationDate']) && !empty($_POST['expirationDate'])) {
        $expirationDate = $_POST['expirationDate'];
        if (!validateDate($expirationDate)) {
            throw new Exception('Fecha de expiración inválida');
        }
        $expirationTime = isset($_POST['expirationTime']) && !empty($_POST['expirationTime']) 
            ? $_POST['expirationTime'] 
            : '23:59:59';
        $expirationDateTime = date('Y-m-d H:i:s', strtotime("$expirationDate $expirationTime"));
    } else {
        // Para usuarios no registrados o sin fecha de expiración, establecer la expiración a 30 días
        $expirationDateTime = date('Y-m-d H:i:s', strtotime('+30 days'));
    }

    // Procesar contraseña
    $hashedPassword = null;
    if ($user_id && isset($_POST['password']) && !empty($_POST['password'])) {
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    // Insertar la URL original, el código, la fecha/hora de expiración y la contraseña en la base de datos
    $stmt = $pdo->prepare("INSERT INTO enlaces (codigo, url_original, user_id, expiration_date, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$codigoUnico, $urlOriginal, $user_id, $expirationDateTime, $hashedPassword]);

    $urlAcortada = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $codigoUnico;

    error_log("Respuesta JSON en acortar.php: " . json_encode([
        'url' => $urlAcortada, 
        'id' => $pdo->lastInsertId(),
        'expirationDate' => $expirationDateTime,
        'hasPassword' => $hashedPassword !== null,
        'url_original' => $urlOriginal
    ]));

    echo json_encode([
        'url' => $urlAcortada, 
        'id' => $pdo->lastInsertId(),
        'expirationDate' => $expirationDateTime,
        'hasPassword' => $hashedPassword !== null,
        'url_original' => $urlOriginal
    ], JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    logError('Error en acortar.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Hubo un error al acortar la URL. Por favor, inténtelo de nuevo. Detalles: ' . $e->getMessage()]);
}
