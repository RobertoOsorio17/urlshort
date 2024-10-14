<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URLify - Acortador de Enlaces</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
    <script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
        }
        .page-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 48px 24px;
        }
        .demo-card-wide.mdl-card {
            width: 100%;
            max-width: 600px;
            margin-bottom: 48px;
        }
        .demo-card-wide > .mdl-card__title {
            color: #fff;
            height: 176px;
            background: linear-gradient(45deg, #FE6B8B 30%, #FF8E53 90%);
        }
        .mdl-card__supporting-text {
            width: 100%;
            padding: 24px;
            box-sizing: border-box;
        }
        #resultado {
            margin-top: 20px;
            word-break: break-all;
        }
        .disabled-option {
            opacity: 0.5;
            pointer-events: none;
        }
        .info-text {
            font-size: 0.9em;
            color: #666;
            margin-top: 16px;
        }
        .features {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            margin-top: 48px;
            width: 100%;
            max-width: 1200px;
        }
        .feature-card {
            width: 300px;
            margin-bottom: 24px;
        }
        .feature-card > .mdl-card__title {
            color: #fff;
            height: 176px;
        }
        .feature-card:nth-child(1) > .mdl-card__title {
            background: linear-gradient(45deg, #2196F3 30%, #21CBF3 90%);
        }
        .feature-card:nth-child(2) > .mdl-card__title {
            background: linear-gradient(45deg, #4CAF50 30%, #8BC34A 90%);
        }
        .feature-card:nth-child(3) > .mdl-card__title {
            background: linear-gradient(45deg, #9C27B0 30%, #E91E63 90%);
        }
    </style>
</head>
<body>
    <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
        <header class="mdl-layout__header">
            <div class="mdl-layout__header-row">
                <span class="mdl-layout-title">URLify</span>
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
            <div class="page-content">
                <div class="demo-card-wide mdl-card mdl-shadow--2dp">
                    <div class="mdl-card__title">
                        <h2 class="mdl-card__title-text">Acorta tu URL</h2>
                    </div>
                    <div class="mdl-card__supporting-text">
                        <form id="acortadorForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label" style="width: 100%;">
                                <input class="mdl-textfield__input" type="url" id="urlOriginal" required>
                                <label class="mdl-textfield__label" for="urlOriginal">Ingresa la URL a acortar</label>
                            </div>
                            
                            <div class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect <?php echo !isset($_SESSION['user_id']) ? 'disabled-option' : ''; ?>">
                                <input type="checkbox" id="expirationCheckbox" class="mdl-checkbox__input" <?php echo !isset($_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                <label class="mdl-checkbox__label" for="expirationCheckbox">Establecer fecha de expiración</label>
                            </div>
                            <div id="expirationDateContainer" style="display: none;">
                                <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                                    <input class="mdl-textfield__input" type="date" id="expirationDate" name="expirationDate">
                                    <label class="mdl-textfield__label" for="expirationDate">Fecha de expiración</label>
                                </div>
                            </div>
                            
                            <div class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect <?php echo !isset($_SESSION['user_id']) ? 'disabled-option' : ''; ?>">
                                <input type="checkbox" id="passwordCheckbox" class="mdl-checkbox__input" <?php echo !isset($_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                <label class="mdl-checkbox__label" for="passwordCheckbox">Proteger con contraseña</label>
                            </div>
                            <div id="passwordContainer" style="display: none;">
                                <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                                    <input class="mdl-textfield__input" type="password" id="password" name="password">
                                    <label class="mdl-textfield__label" for="password">Contraseña</label>
                                </div>
                            </div>
                            
                            <?php if (!isset($_SESSION['user_id'])): ?>
                                <p class="info-text">Las opciones de fecha de expiración y contraseña están disponibles solo para usuarios registrados. <a href="registro.php">Regístrate aquí</a>.</p>
                                <p class="info-text">Los enlaces creados sin registro se eliminarán automáticamente después de 30 días.</p>
                            <?php endif; ?>

                            <button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent">
                                Acortar
                            </button>
                        </form>
                    </div>
                    <div class="mdl-card__actions mdl-card--border">
                        <div id="resultado"></div>
                    </div>
                </div>

                <div class="features">
                    <div class="feature-card mdl-card mdl-shadow--2dp">
                        <div class="mdl-card__title">
                            <h2 class="mdl-card__title-text">Rápido y Sencillo</h2>
                        </div>
                        <div class="mdl-card__supporting-text">
                            Acorta tus URLs en segundos. Sin complicaciones, sin registros obligatorios.
                        </div>
                    </div>
                    <div class="feature-card mdl-card mdl-shadow--2dp">
                        <div class="mdl-card__title">
                            <h2 class="mdl-card__title-text">Seguro y Confiable</h2>
                        </div>
                        <div class="mdl-card__supporting-text">
                            Protege tus enlaces con contraseñas y establece fechas de expiración para mayor control.
                        </div>
                    </div>
                    <div class="feature-card mdl-card mdl-shadow--2dp">
                        <div class="mdl-card__title">
                            <h2 class="mdl-card__title-text">Estadísticas Detalladas</h2>
                        </div>
                        <div class="mdl-card__supporting-text">
                            Registrate para acceder a estadísticas detalladas de tus enlaces acortados.
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="script.js"></script>
</body>
</html>
