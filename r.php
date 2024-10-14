<?php
$host = 'localhost';
$db   = 'acortador_urls';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Sanitización de entrada de usuario
$codigo = isset($_GET['c']) ? $_GET['c'] : '';

$urlOriginal = '';
$error = '';

if ($codigo) {
    // Primero, verificar si el código está en la tabla de enlaces eliminados
    $stmt = $pdo->prepare("SELECT 1 FROM enlaces_eliminados WHERE codigo = ?");
    $stmt->execute([$codigo]);
    if ($stmt->fetchColumn()) {
        $error = "Esta URL acortada ha sido eliminada.";
    } else {
        // Si no está en la tabla de eliminados, buscar en la tabla principal
        $stmt = $pdo->prepare("SELECT url_original, expiration_date, password FROM enlaces WHERE codigo = ?");
        $stmt->execute([$codigo]);
        $resultado = $stmt->fetch();
        
        if ($resultado) {
            if ($resultado['expiration_date'] && strtotime($resultado['expiration_date']) < time()) {
                $error = "Este enlace ha expirado.";
            } elseif ($resultado['password']) {
                if (!isset($_POST['password'])) {
                    include 'password_form.php';
                    exit;
                } elseif (!password_verify($_POST['password'], $resultado['password'])) {
                    $error = "Contraseña incorrecta.";
                } else {
                    $urlOriginal = $resultado['url_original'];
                }
            } else {
                $urlOriginal = $resultado['url_original'];
            }
        } else {
            $error = "URL no encontrada.";
        }
    }
} else {
    $error = "Código no proporcionado.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirigiendo...</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
    <script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
    <style>
        .demo-card-wide.mdl-card {
            width: 512px;
            margin: 2rem auto;
        }
        .demo-card-wide > .mdl-card__title {
            color: #fff;
            height: 176px;
            background: url('https://getmdl.io/assets/demos/welcome_card.jpg') center / cover;
        }
        .mdl-card__supporting-text {
            font-size: 1.1em;
        }
        #countdown {
            font-size: 2em;
            font-weight: bold;
            color: #3f51b5;
        }
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="mdl-layout mdl-js-layout">
        <main class="mdl-layout__content">
            <div class="demo-card-wide mdl-card mdl-shadow--2dp">
                <div class="mdl-card__title">
                    <h2 class="mdl-card__title-text">Redirigiendo...</h2>
                </div>
                <div class="mdl-card__supporting-text">
                    <?php if ($error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php else: ?>
                        <p>Estás siendo redirigido a:</p>
                        <p><strong><?php echo htmlspecialchars($urlOriginal); ?></strong></p>
                        <p>La redirección comenzará en <span id="countdown">5</span> segundos.</p>
                        <p>Si no eres redirigido automáticamente, haz clic en el botón de abajo.</p>
                    <?php endif; ?>
                </div>
                <div class="mdl-card__actions mdl-card--border center">
                    <?php if (!$error): ?>
                        <a href="<?php echo htmlspecialchars($urlOriginal); ?>" class="mdl-button mdl-button--colored mdl-js-button mdl-js-ripple-effect">
                            Ir ahora
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php if (!$error): ?>
    <script>
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        const intervalId = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(intervalId);
                window.location.href = <?php echo json_encode($urlOriginal); ?>;
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>
