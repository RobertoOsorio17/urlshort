<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$enlace_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$enlace_id) {
    header("Location: dashboard.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, codigo, url_original FROM enlaces WHERE id = ? AND user_id = ?");
$stmt->execute([$enlace_id, $user_id]);
$enlace = $stmt->fetch();

if (!$enlace) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva_url = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);
    if ($nueva_url) {
        $stmt = $pdo->prepare("UPDATE enlaces SET url_original = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$nueva_url, $enlace_id, $user_id]);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "URL inválida.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar enlace</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
    <script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
    <style>
        .demo-card-wide.mdl-card {
            width: 512px;
            margin: 2rem auto;
        }
    </style>
</head>
<body>
    <div class="mdl-layout mdl-js-layout">
        <main class="mdl-layout__content">
            <div class="demo-card-wide mdl-card mdl-shadow--2dp">
                <div class="mdl-card__title">
                    <h2 class="mdl-card__title-text">Editar enlace</h2>
                </div>
                <div class="mdl-card__supporting-text">
                    <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
                    <form action="" method="post">
                        <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                            <input class="mdl-textfield__input" type="url" id="url" name="url" value="<?= htmlspecialchars($enlace['url_original']) ?>">
                            <label class="mdl-textfield__label" for="url">URL Original</label>
                        </div>
                        <button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored">
                            Guardar cambios
                        </button>
                    </form>
                </div>
                <div class="mdl-card__actions mdl-card--border">
                    <a class="mdl-button mdl-button--colored mdl-js-button mdl-js-ripple-effect" href="dashboard.php">
                        Volver al dashboard
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

