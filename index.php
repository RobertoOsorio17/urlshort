<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acortador de Enlaces</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
    <script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
    <style>
        .demo-card-wide.mdl-card {
            width: 512px;
            margin: 0 auto;
        }
        .demo-card-wide > .mdl-card__title {
            color: #fff;
            height: 176px;
            background: url('https://getmdl.io/assets/demos/welcome_card.jpg') center / cover;
        }
        .demo-card-wide > .mdl-card__menu {
            color: #fff;
        }
        .mdl-layout__content {
            padding: 24px;
            flex: none;
        }
        #resultado {
            margin-top: 20px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
        <header class="mdl-layout__header">
            <div class="mdl-layout__header-row">
                <span class="mdl-layout-title">Acortador de Enlaces</span>
                <div class="mdl-layout-spacer"></div>
                <nav class="mdl-navigation">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a class="mdl-navigation__link" href="dashboard.php">Dashboard</a>
                        <a class="mdl-navigation__link" href="logout.php">Cerrar sesión</a>
                    <?php else: ?>
                        <a class="mdl-navigation__link" href="login.php">Iniciar sesión</a>
                        <a class="mdl-navigation__link" href="registro.php">Registrarse</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>
        <main class="mdl-layout__content">
            <div class="demo-card-wide mdl-card mdl-shadow--2dp">
                <div class="mdl-card__title">
                    <h2 class="mdl-card__title-text">Acorta tu URL</h2>
                </div>
                <div class="mdl-card__supporting-text">
                    <form id="acortadorForm">
                        <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label" style="width: 100%;">
                            <input class="mdl-textfield__input" type="url" id="urlOriginal" required>
                            <label class="mdl-textfield__label" for="urlOriginal">Ingresa la URL a acortar</label>
                        </div>
                        <button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent">
                            Acortar
                        </button>
                    </form>
                </div>
                <div class="mdl-card__actions mdl-card--border">
                    <div id="resultado"></div>
                </div>
            </div>
        </main>
    </div>
    <script src="script.js"></script>
</body>
</html>
