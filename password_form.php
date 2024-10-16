<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingrese la contraseña</title>
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
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .mdl-card {
            width: 512px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .mdl-card__title {
            background: linear-gradient(45deg, #2196F3 30%, #21CBF3 90%);
            color: white;
            padding: 24px;
        }
        .mdl-card__supporting-text {
            padding: 24px;
        }
        .mdl-textfield {
            width: 100%;
        }
        .mdl-card__actions {
            padding: 16px;
            display: flex;
            justify-content: flex-end;
        }
        .mdl-button--raised.mdl-button--colored {
            background-color: #2196F3 !important;
        }
        .mdl-button--raised.mdl-button--colored:hover {
            background-color: #21CBF3 !important;
        }
    </style>
</head>
<body>
    <div class="mdl-layout mdl-js-layout">
        <main class="mdl-layout__content">
            <div class="page-content">
                <div class="mdl-card mdl-shadow--2dp">
                    <div class="mdl-card__title">
                        <h2 class="mdl-card__title-text">Ingrese la contraseña</h2>
                    </div>
                    <div class="mdl-card__supporting-text">
                        <form action="" method="POST">
                            <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                                <input class="mdl-textfield__input" type="password" id="password" name="password">
                                <label class="mdl-textfield__label" for="password">Contraseña</label>
                            </div>
                            <div class="mdl-card__actions">
                                <button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored">
                                    Enviar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
