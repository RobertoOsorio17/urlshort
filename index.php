<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URLify - Acortador de Enlaces Profesional</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-blue.min.css">
    <script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
    <style>
        body {
            font-family: 'Roboto', 'Helvetica', 'Arial', sans-serif;
            background-color: #f5f5f5;
        }
        .mdl-layout__header {
            background-color: #1976D2;
        }
        .hero {
            background: linear-gradient(45deg, #1976D2, #2196F3);
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        .hero h1 {
            font-weight: 700;
            margin-bottom: 20px;
        }
        .hero p {
            font-size: 1.2em;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        .shortener-card {
            margin-top: -50px;
        }
        .feature-icon {
            font-size: 48px;
            color: #1976D2;
        }
        .feature-title {
            font-weight: 500;
        }
        .mdl-layout__content {
            padding-bottom: 50px;
        }
    </style>
</head>
<body>
    <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
        <header class="mdl-layout__header">
            <div class="mdl-layout__header-row">
                <span class="mdl-layout-title">URLify</span>
                <div class="mdl-layout-spacer"></div>
                <nav class="mdl-navigation mdl-layout--large-screen-only">
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
        <div class="mdl-layout__drawer">
            <span class="mdl-layout-title">URLify</span>
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
        <main class="mdl-layout__content">
            <div class="hero">
                <div class="mdl-grid">
                    <div class="mdl-cell mdl-cell--12-col">
                        <h1>Acorta tus enlaces. Amplía tus posibilidades.</h1>
                        <p>URLify te ofrece una forma rápida y segura de acortar tus URLs, con características avanzadas para usuarios registrados.</p>
                    </div>
                </div>
            </div>

            <div class="mdl-grid">
                <div class="mdl-cell mdl-cell--8-col mdl-cell--2-offset-desktop">
                    <div class="mdl-card mdl-shadow--2dp shortener-card" style="width: 100%;">
                        <div class="mdl-card__title">
                            <h2 class="mdl-card__title-text">Acorta tu URL</h2>
                        </div>
                        <div class="mdl-card__supporting-text">
                            <form id="acortadorForm" action="acortar.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label" style="width: 100%;">
                                    <input class="mdl-textfield__input" type="url" id="urlOriginal" name="url" required>
                                    <label class="mdl-textfield__label" for="urlOriginal">Ingresa la URL a acortar</label>
                                </div>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                                        <input class="mdl-textfield__input" type="date" id="expirationDate" name="expirationDate">
                                        <label class="mdl-textfield__label" for="expirationDate">Fecha de expiración (opcional)</label>
                                    </div>
                                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                                        <input class="mdl-textfield__input" type="password" id="password" name="password">
                                        <label class="mdl-textfield__label" for="password">Contraseña (opcional)</label>
                                    </div>
                                <?php else: ?>
                                    <p class="mdl-typography--text-center">Las opciones avanzadas están disponibles para usuarios registrados. <a href="registro.php">Regístrate aquí</a>.</p>
                                <?php endif; ?>
                                <div class="mdl-card__actions mdl-card--border">
                                    <button class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--colored" type="submit">
                                        Acortar
                                        <i class="material-icons">send</i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="mdl-card__actions mdl-card--border">
                            <div id="resultado"></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="mdl-card mdl-shadow--2dp" style="width: 100%; margin-top: 20px;">
                    <div class="mdl-card__title">
                        <h2 class="mdl-card__title-text">Tus enlaces recientes</h2>
                    </div>
                    <div class="mdl-card__supporting-text">
                        <table class="mdl-data-table mdl-js-data-table mdl-shadow--2dp" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th class="mdl-data-table__cell--non-numeric">URL Original</th>
                                    <th class="mdl-data-table__cell--non-numeric">URL Acortada</th>
                                    <th>Fecha de Expiración</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los enlaces se cargarán dinámicamente con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mdl-grid">
                <div class="mdl-cell mdl-cell--4-col">
                    <div class="mdl-card mdl-shadow--2dp" style="width: 100%;">
                        <div class="mdl-card__title">
                            <h2 class="mdl-card__title-text">Rápido y Sencillo</h2>
                        </div>
                        <div class="mdl-card__supporting-text">
                            Acorta tus URLs en segundos. Sin complicaciones, sin registros obligatorios.
                        </div>
                    </div>
                </div>
                <div class="mdl-cell mdl-cell--4-col">
                    <div class="mdl-card mdl-shadow--2dp" style="width: 100%;">
                        <div class="mdl-card__title">
                            <h2 class="mdl-card__title-text">Seguro y Confiable</h2>
                        </div>
                        <div class="mdl-card__supporting-text">
                            Protege tus enlaces con contraseñas y establece fechas de expiración para mayor control.
                        </div>
                    </div>
                </div>
                <div class="mdl-cell mdl-cell--4-col">
                    <div class="mdl-card mdl-shadow--2dp" style="width: 100%;">
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
        <footer class="mdl-mini-footer">
            <div class="mdl-mini-footer__left-section">
                <div class="mdl-logo">URLify</div>
                <ul class="mdl-mini-footer__link-list">
                    <li><a href="#">Ayuda</a></li>
                    <li><a href="#">Privacidad y Términos</a></li>
                </ul>
            </div>
        </footer>
    </div>
    <script src="script.js"></script>
    <!-- Añadir esto justo antes del cierre del body -->
    <dialog class="mdl-dialog" id="delete-modal">
        <h4 class="mdl-dialog__title">Confirmar eliminación</h4>
        <div class="mdl-dialog__content">
            <p>¿Estás seguro de que quieres eliminar este enlace?</p>
        </div>
        <div class="mdl-dialog__actions">
            <button type="button" class="mdl-button confirm-delete">Eliminar</button>
            <button type="button" class="mdl-button close">Cancelar</button>
        </div>
    </dialog>
    <dialog class="mdl-dialog" id="login-modal">
        <h4 class="mdl-dialog__title">Iniciar sesión o registrarse</h4>
        <div class="mdl-dialog__content">
            <p>Para asociar este enlace a tu cuenta, necesitas iniciar sesión o registrarte.</p>
        </div>
        <div class="mdl-dialog__actions">
            <a id="login-link" class="mdl-button">Iniciar sesión</a>
            <a id="register-link" class="mdl-button">Registrarse</a>
            <button type="button" class="mdl-button close">Cancelar</button>
        </div>
    </dialog>
</body>
</html>
