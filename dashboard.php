<?php
session_start();
require_once 'db_connect.php';

// Construir la URL base dinámicamente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];

// Manejar la asociación de enlaces
$showAssociateModal = false;
if (isset($_GET['associate']) && $_GET['associate'] == '1') {
    if (isset($_SESSION['associationToken'])) {
        $showAssociateModal = true;
        $associationToken = $_SESSION['associationToken'];
    } else {
        // El usuario intentó acceder a associate=1 sin un token válido
        error_log("Intento de asociación sin token válido para el usuario ID: " . $_SESSION['user_id']);
        // Opcionalmente, puedes redirigir al usuario o mostrar un mensaje de error
        // header("Location: dashboard.php");
        // exit;
    }
}

// Obtener los enlaces del usuario
$stmt = $pdo->prepare("SELECT id, codigo, url_original, expiration_date, CASE WHEN password IS NOT NULL THEN 1 ELSE 0 END as has_password FROM enlaces WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$enlaces = $stmt->fetchAll();

// Manejar la eliminación de enlaces
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $enlace_id = filter_input(INPUT_POST, 'delete', FILTER_VALIDATE_INT);
    if ($enlace_id) {
        // Iniciar una transacción
        $pdo->beginTransaction();
        
        try {
            // Obtener el código del enlace
            $stmt = $pdo->prepare("SELECT codigo FROM enlaces WHERE id = ? AND user_id = ?");
            $stmt->execute([$enlace_id, $user_id]);
            $codigo = $stmt->fetchColumn();
            
            if ($codigo) {
                // Eliminar el enlace de la tabla principal
                $stmt = $pdo->prepare("DELETE FROM enlaces WHERE id = ? AND user_id = ?");
                $stmt->execute([$enlace_id, $user_id]);
                
                // Insertar el código en la tabla de enlaces eliminados
                $stmt = $pdo->prepare("INSERT INTO enlaces_eliminados (codigo) VALUES (?)");
                $stmt->execute([$codigo]);
                
                // Confirmar la transacción
                $pdo->commit();
            }
        } catch (Exception $e) {
            // Si algo sale mal, revertir la transacción
            $pdo->rollBack();
            error_log("Error al eliminar enlace: " . $e->getMessage());
        }
    }
    header("Location: dashboard.php");
    exit;
}

// Al principio del archivo
if (isset($_SESSION['associationToken'])) {
    $showAssociateModal = true;
    $associationToken = $_SESSION['associationToken'];
    // No elimines el token aquí, lo haremos después de asociar el enlace
} else {
    $showAssociateModal = false;
}

// Al principio del archivo
error_log('GET parameters: ' . print_r($_GET, true));
error_log('SESSION: ' . print_r($_SESSION, true));

error_log('Session ID: ' . session_id());
error_log('User ID: ' . ($_SESSION['user_id'] ?? 'No establecido'));
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
    <script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <style>
        .demo-card-wide.mdl-card {
            width: 100%;
            margin: 2rem auto;
        }
        .mdl-dialog {
            width: 80%;
            max-width: 500px;
        }
        .mdl-button--icon {
            margin-right: 8px;
        }
        .mdl-data-table {
            width: 100%;
            table-layout: fixed;
        }
        
        .mdl-data-table td, .mdl-data-table th {
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        
        #add-url-row {
            background-color: #f5f5f5;
        }
        
        #add-url-button {
            margin: 20px 0;
            transition: transform 0.3s ease;
        }
        
        #add-url-button:hover {
            transform: scale(1.1);
        }
        
        .mdl-button--fab.mdl-button--colored {
            background-color: #ff4081;
        }
        
        .mdl-button--fab.mdl-button--colored:hover {
            background-color: #ff6b9b;
        }
        
        .mdl-card__supporting-text {
            width: auto;
            padding: 16px 24px;
        }
        
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            50% { transform: translateX(10px); }
            75% { transform: translateX(-10px); }
            100% { transform: translateX(0); }
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        .error-message {
            margin-top: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
        <header class="mdl-layout__header">
            <div class="mdl-layout__header-row">
                <span class="mdl-layout-title">Dashboard</span>
                <div class="mdl-layout-spacer"></div>
                <nav class="mdl-navigation">
                    <button class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" onclick="openShortenModal()">
                        Acortar URL
                    </button>
                    <a class="mdl-navigation__link" href="logout.php">Cerrar sesión</a>
                </nav>
            </div>
        </header>
        <main class="mdl-layout__content">
            <div class="page-content">
                <div class="demo-card-wide mdl-card mdl-shadow--2dp">
                    <div class="mdl-card__title">
                        <h2 class="mdl-card__title-text">Tus enlaces acortados</h2>
                    </div>
                    <div class="mdl-card__supporting-text">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <h4 style="margin: 0;">Enlaces acortados</h4>
                            <button class="mdl-button mdl-js-button mdl-button--fab mdl-button--colored mdl-js-ripple-effect" id="add-url-button" onclick="openShortenModal()">
                                <i class="material-icons">add</i>
                            </button>
                        </div>
                        <div class="mdl-grid">
                            <div class="mdl-cell mdl-cell--12-col">
                                <button id="bulk-actions-menu" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent">
                                    Acciones masivas
                                </button>
                                <ul class="mdl-menu mdl-menu--bottom-left mdl-js-menu mdl-js-ripple-effect" for="bulk-actions-menu">
                                    <li class="mdl-menu__item" onclick="bulkDelete()">Eliminar seleccionados</li>
                                    <li class="mdl-menu__item" onclick="bulkExtendExpiration()">Extender expiración</li>
                                </ul>
                            </div>
                        </div>
                        <table class="mdl-data-table mdl-js-data-table mdl-shadow--2dp">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect mdl-data-table__select" for="checkbox-all">
                                            <input type="checkbox" id="checkbox-all" class="mdl-checkbox__input">
                                        </label>
                                    </th>
                                    <th class="mdl-data-table__cell--non-numeric">URL Original</th>
                                    <th class="mdl-data-table__cell--non-numeric">URL Acortada</th>
                                    <th class="mdl-data-table__cell--non-numeric">Fecha de Expiración</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enlaces as $enlace): ?>
                                <tr>
                                    <td>
                                        <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect mdl-data-table__select" for="checkbox-<?= $enlace['id'] ?>">
                                            <input type="checkbox" id="checkbox-<?= $enlace['id'] ?>" class="mdl-checkbox__input" value="<?= $enlace['id'] ?>">
                                        </label>
                                    </td>
                                    <td class="mdl-data-table__cell--non-numeric"><?= htmlspecialchars($enlace['url_original']) ?></td>
                                    <td class="mdl-data-table__cell--non-numeric">
                                        <a href="<?= $base_url . $enlace['codigo'] ?>" target="_blank"><?= $base_url . $enlace['codigo'] ?></a>
                                    </td>
                                    <td class="mdl-data-table__cell--non-numeric"><?= $enlace['expiration_date'] ?></td>
                                    <td>
                                        <button class="mdl-button mdl-js-button mdl-button--icon" onclick="openEditModal(<?= $enlace['id'] ?>, '<?= htmlspecialchars($enlace['url_original'], ENT_QUOTES) ?>', '<?= $enlace['expiration_date'] ?>', '<?= $enlace['has_password'] ?>')">
                                            <i class="material-icons">edit</i>
                                        </button>
                                        <button class="mdl-button mdl-js-button mdl-button--icon" onclick="openDeleteModal(<?= $enlace['id'] ?>)">
                                            <i class="material-icons">delete</i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de edición -->
    <dialog class="mdl-dialog" id="editModal">
        <h4 class="mdl-dialog__title">Editar URL</h4>
        <div class="mdl-dialog__content">
            <form id="editForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" id="editId" name="id">
                <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                    <input class="mdl-textfield__input" type="url" id="editUrl" name="url" required>
                    <label class="mdl-textfield__label" for="editUrl">Nueva URL</label>
                </div>
                <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="editExpirationCheckbox">
                    <input type="checkbox" id="editExpirationCheckbox" class="mdl-checkbox__input">
                    <span class="mdl-checkbox__label">Establecer fecha de expiración</span>
                </label>
                <div id="editExpirationDateContainer" style="display: none;">
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input class="mdl-textfield__input" type="date" id="editExpirationDate" name="expirationDate">
                        <label class="mdl-textfield__label" for="editExpirationDate">Fecha de expiración</label>
                    </div>
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input class="mdl-textfield__input" type="time" id="editExpirationTime" name="expirationTime">
                        <label class="mdl-textfield__label" for="editExpirationTime">Hora de expiración</label>
                    </div>
                </div>
                <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="editPasswordCheckbox">
                    <input type="checkbox" id="editPasswordCheckbox" class="mdl-checkbox__input">
                    <span class="mdl-checkbox__label">Cambiar contraseña</span>
                </label>
                <div id="editPasswordContainer" style="display: none;">
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input class="mdl-textfield__input" type="password" id="editPassword" name="password">
                        <label class="mdl-textfield__label" for="editPassword">Nueva contraseña</label>
                    </div>
                </div>
                <input type="hidden" id="editRemovePassword" name="removePassword" value="0">
            </form>
        </div>
        <div class="mdl-dialog__actions">
            <button type="button" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored mdl-js-ripple-effect" onclick="submitEditForm()">Guardar</button>
            <button type="button" class="mdl-button mdl-js-button mdl-js-ripple-effect close" onclick="closeEditModal()">Cancelar</button>
        </div>
    </dialog>

    <!-- Modal de confirmación de eliminación -->
    <dialog class="mdl-dialog" id="deleteModal">
        <h4 class="mdl-dialog__title">Confirmar eliminación</h4>
        <div class="mdl-dialog__content">
            <p>¿Estás seguro de que quieres eliminar esta URL acortada?</p>
        </div>
        <div class="mdl-dialog__actions">
            <form id="deleteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="delete" id="deleteId">
                <button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored mdl-js-ripple-effect">Eliminar</button>
                <button type="button" class="mdl-button mdl-js-button mdl-js-ripple-effect close" onclick="closeDeleteModal()">Cancelar</button>
            </form>
        </div>
    </dialog>

    <!-- Modal para acortar URL -->
    <dialog class="mdl-dialog" id="shortenModal">
        <h4 class="mdl-dialog__title">Acortar URL</h4>
        <div class="mdl-dialog__content">
            <form id="shortenForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                    <input class="mdl-textfield__input" type="url" id="shortenUrl" name="url" required>
                    <label class="mdl-textfield__label" for="shortenUrl">URL a acortar</label>
                </div>
                <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="expirationCheckbox">
                    <input type="checkbox" id="expirationCheckbox" class="mdl-checkbox__input">
                    <span class="mdl-checkbox__label">Establecer fecha de expiración</span>
                </label>
                <div id="expirationDateContainer" style="display: none;">
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input type="date" id="expirationDate" name="expirationDate" class="mdl-textfield__input" min="<?php echo date('Y-m-d'); ?>">
                        <label class="mdl-textfield__label" for="expirationDate">Fecha de expiración</label>
                    </div>
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input class="mdl-textfield__input" type="time" id="expirationTime" name="expirationTime">
                        <label class="mdl-textfield__label" for="expirationTime">Hora de expiración</label>
                    </div>
                </div>
                <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="passwordCheckbox">
                    <input type="checkbox" id="passwordCheckbox" class="mdl-checkbox__input">
                    <span class="mdl-checkbox__label">Establecer contraseña</span>
                </label>
                <div id="passwordContainer" style="display: none;">
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input class="mdl-textfield__input" type="password" id="password" name="password">
                        <label class="mdl-textfield__label" for="password">Contraseña</label>
                    </div>
                </div>
                <p id="shortenError" class="error-message" style="color: red; display: none;"></p>
            </form>
        </div>
        <div class="mdl-dialog__actions">
            <button type="button" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored mdl-js-ripple-effect" onclick="submitShortenForm()">Acortar</button>
            <button type="button" class="mdl-button mdl-js-button mdl-js-ripple-effect close" onclick="closeShortenModal()">Cancelar</button>
        </div>
    </dialog>

    <!-- Añade este modal al final del body -->
    <dialog class="mdl-dialog" id="associate-modal">
        <h4 class="mdl-dialog__title">Asociar enlace</h4>
        <div class="mdl-dialog__content">
            <p>¿Estás seguro de que quieres asociar este enlace a tu cuenta?</p>
            <p><strong>URL original:</strong> <span id="original-url"></span></p>
            <p><strong>URL acortada:</strong> <span id="short-url"></span></p>
        </div>
        <div class="mdl-dialog__actions">
            <button type="button" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored mdl-js-ripple-effect confirm-associate">
                Asociar
            </button>
            <button type="button" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect close">
                Cancelar
            </button>
        </div>
    </dialog>

    <!-- Modal de confirmación para eliminación masiva -->
    <dialog class="mdl-dialog" id="bulkDeleteModal">
        <h4 class="mdl-dialog__title">Confirmar eliminación masiva</h4>
        <div class="mdl-dialog__content">
            <p>¿Estás seguro de que quieres eliminar los enlaces seleccionados?</p>
        </div>
        <div class="mdl-dialog__actions">
            <button type="button" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored mdl-js-ripple-effect" onclick="confirmBulkDelete()">Eliminar</button>
            <button type="button" class="mdl-button mdl-js-button mdl-js-ripple-effect close" onclick="closeBulkDeleteModal()">Cancelar</button>
        </div>
    </dialog>

    <!-- Modifica el modal para extender expiración masiva -->
    <dialog class="mdl-dialog" id="bulkExtendExpirationModal">
        <h4 class="mdl-dialog__title">Extender expiración</h4>
        <div class="mdl-dialog__content">
            <p>¿Cuántos días quieres extender la expiración de los enlaces seleccionados?</p>
            <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                <input class="mdl-textfield__input" type="number" id="extendDays" min="1" max="365">
                <label class="mdl-textfield__label" for="extendDays">Número de días (máximo 365)</label>
            </div>
        </div>
        <div class="mdl-dialog__actions">
            <button type="button" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored mdl-js-ripple-effect" onclick="confirmBulkExtendExpiration()">Extender</button>
            <button type="button" class="mdl-button mdl-js-button mdl-js-ripple-effect close" onclick="closeBulkExtendExpirationModal()">Cancelar</button>
        </div>
    </dialog>

    <script>
        var deleteId;
        var editDialog = document.getElementById('editModal');
        var deleteDialog = document.getElementById('deleteModal');
        var shortenDialog = document.getElementById('shortenModal');

        if (!editDialog.showModal) {
            dialogPolyfill.registerDialog(editDialog);
        }
        if (!deleteDialog.showModal) {
            dialogPolyfill.registerDialog(deleteDialog);
        }
        if (!shortenDialog.showModal) {
            dialogPolyfill.registerDialog(shortenDialog);
        }

        function openEditModal(id, url, expirationDate, hasPassword) {
            document.getElementById('editId').value = id;
            document.getElementById('editUrl').value = url;
            
            const editExpirationCheckbox = document.getElementById('editExpirationCheckbox');
            const editExpirationDateContainer = document.getElementById('editExpirationDateContainer');
            const editPasswordCheckbox = document.getElementById('editPasswordCheckbox');
            const editPasswordContainer = document.getElementById('editPasswordContainer');
            
            if (expirationDate) {
                editExpirationCheckbox.checked = true;
                editExpirationDateContainer.style.display = 'block';
                const [date, time] = expirationDate.split(' ');
                document.getElementById('editExpirationDate').value = date;
                document.getElementById('editExpirationTime').value = time;
            } else {
                editExpirationCheckbox.checked = false;
                editExpirationDateContainer.style.display = 'none';
            }
            
            editPasswordCheckbox.checked = hasPassword === '1';
            editPasswordCheckbox.disabled = false; // Siempre habilitado para permitir cambios
            editPasswordContainer.style.display = editPasswordCheckbox.checked ? 'block' : 'none';
            document.getElementById('editRemovePassword').value = '0';
            
            editDialog.showModal();
            componentHandler.upgradeElements(editDialog);
            
            // Actualizar el estado visual de los checkboxes
            editExpirationCheckbox.parentNode.MaterialCheckbox.check();
            editExpirationCheckbox.parentNode.MaterialCheckbox.checkToggleState();
            editPasswordCheckbox.parentNode.MaterialCheckbox.check();
            editPasswordCheckbox.parentNode.MaterialCheckbox.checkToggleState();
        }

        function closeEditModal() {
            editDialog.close();
        }

        function submitEditForm() {
            const formData = new FormData(document.getElementById('editForm'));
            const editPasswordCheckbox = document.getElementById('editPasswordCheckbox');
            const editRemovePassword = document.getElementById('editRemovePassword');
            
            // Si el checkbox de contraseña está deshabilitado y no está marcado, 
            // significa que queremos eliminar la contraseña existente
            if (editPasswordCheckbox.disabled && !editPasswordCheckbox.checked) {
                formData.append('removePassword', '1');
            } else {
                formData.append('removePassword', editRemovePassword.value);
            }

            fetch('edit_link.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEditModal();
                    location.reload();
                } else {
                    alert('Error al editar el enlace: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al editar el enlace');
            });
        }

        function openDeleteModal(id) {
            deleteId = id;
            deleteDialog.showModal();
            componentHandler.upgradeElements(deleteDialog);
        }

        function closeDeleteModal() {
            deleteDialog.close();
        }

        function confirmDelete() {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete';
            input.value = deleteId;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        function openShortenModal() {
            document.getElementById('shortenUrl').value = '';
            document.getElementById('shortenError').style.display = 'none';
            shortenDialog.showModal();
            componentHandler.upgradeElements(shortenDialog);
        }

        function closeShortenModal() {
            shortenDialog.close();
        }

        // Mover estas funciones fuera del evento DOMContentLoaded
        function bulkDelete() {
            const selectedIds = getSelectedIds();
            if (selectedIds.length === 0) {
                alert('Por favor, selecciona al menos un enlace para eliminar.');
                return;
            }
            openBulkDeleteModal(selectedIds);
        }

        function bulkExtendExpiration() {
            const selectedIds = getSelectedIds();
            if (selectedIds.length === 0) {
                alert('Por favor, selecciona al menos un enlace para extender su expiración.');
                return;
            }
            openBulkExtendExpirationModal(selectedIds);
        }

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('tbody input[type="checkbox"]:checked'))
                .map(checkbox => checkbox.value);
        }

        function openBulkDeleteModal(ids) {
            selectedIdsForBulkActions = ids;
            const dialog = document.getElementById('bulkDeleteModal');
            if (!dialog.showModal) {
                dialogPolyfill.registerDialog(dialog);
            }
            dialog.showModal();
        }

        function closeBulkDeleteModal() {
            document.getElementById('bulkDeleteModal').close();
        }

        function confirmBulkDelete() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                console.error('CSRF token not found');
                alert('Error de seguridad. Por favor, recarga la página e intenta de nuevo.');
                return;
            }
            fetch('bulk_delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken.getAttribute('content')
                },
                body: JSON.stringify({ ids: selectedIdsForBulkActions })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar los enlaces: ' + data.error);
                }
            });
            closeBulkDeleteModal();
        }

        function openBulkExtendExpirationModal(ids) {
            selectedIdsForBulkActions = ids;
            const dialog = document.getElementById('bulkExtendExpirationModal');
            if (!dialog.showModal) {
                dialogPolyfill.registerDialog(dialog);
            }
            dialog.showModal();
        }

        function closeBulkExtendExpirationModal() {
            document.getElementById('bulkExtendExpirationModal').close();
        }

        function confirmBulkExtendExpiration() {
            const days = document.getElementById('extendDays').value;
            if (!days || isNaN(days) || days < 1 || days > 365) {
                alert('Por favor, introduce un número válido de días (entre 1 y 365).');
                return;
            }
            fetch('bulk_extend_expiration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ ids: selectedIdsForBulkActions, days: parseInt(days) })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al extender la expiración: ' + data.error);
                }
            });
            closeBulkExtendExpirationModal();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const expirationCheckbox = document.getElementById('expirationCheckbox');
            const expirationDateContainer = document.getElementById('expirationDateContainer');
            const passwordCheckbox = document.getElementById('passwordCheckbox');
            const passwordContainer = document.getElementById('passwordContainer');
            const editExpirationCheckbox = document.getElementById('editExpirationCheckbox');
            const editExpirationDateContainer = document.getElementById('editExpirationDateContainer');
            const editPasswordCheckbox = document.getElementById('editPasswordCheckbox');
            const editPasswordContainer = document.getElementById('editPasswordContainer');

            expirationCheckbox.addEventListener('change', function() {
                expirationDateContainer.style.display = this.checked ? 'block' : 'none';
            });

            passwordCheckbox.addEventListener('change', function() {
                passwordContainer.style.display = this.checked ? 'block' : 'none';
            });

            editExpirationCheckbox.addEventListener('change', function() {
                editExpirationDateContainer.style.display = this.checked ? 'block' : 'none';
            });

            editPasswordCheckbox.addEventListener('change', function() {
                const editPasswordContainer = document.getElementById('editPasswordContainer');
                const editRemovePassword = document.getElementById('editRemovePassword');
                
                editPasswordContainer.style.display = this.checked ? 'block' : 'none';
                editRemovePassword.value = this.checked ? '0' : '1';
            });

            window.submitShortenForm = function() {
                const urlOriginal = document.getElementById('shortenUrl').value;
                const errorElement = document.getElementById('shortenError');
                const shortenForm = document.getElementById('shortenForm');
                const expirationDate = document.getElementById('expirationDate').value;
                const expirationTime = document.getElementById('expirationTime').value;
                const password = document.getElementById('password').value;
                
                // Validación de URL
                const urlPattern = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
                if (!urlPattern.test(urlOriginal)) {
                    errorElement.textContent = "Por favor, introduce una URL válida.";
                    errorElement.style.display = "block";
                    shortenForm.classList.add('shake');
                    setTimeout(() => {
                        shortenForm.classList.remove('shake');
                    }, 500);
                    return;
                }
                
                errorElement.style.display = "none";
                
                const formData = new FormData();
                formData.append('url', urlOriginal);
                formData.append('csrf_token', document.querySelector('#shortenForm input[name="csrf_token"]').value);
                if (expirationCheckbox.checked && expirationDate) {
                    formData.append('expirationDate', expirationDate);
                    formData.append('expirationTime', expirationTime || '23:59');
                }
                if (passwordCheckbox.checked && password) {
                    formData.append('password', password);
                }
                
                fetch('acortar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        errorElement.textContent = data.error;
                        errorElement.style.display = "block";
                        shortenForm.classList.add('shake');
                        setTimeout(() => {
                            shortenForm.classList.remove('shake');
                        }, 500);
                    } else {
                        // Añadir la nueva URL a la tabla
                        const tabla = document.querySelector('.mdl-data-table tbody');
                        const newRow = tabla.insertRow(0);
                        newRow.innerHTML = `
                            <td class="mdl-data-table__cell--non-numeric">${urlOriginal}</td>
                             <td><a href="${data.url}" target="_blank">${data.url.replace('http://', '')}</a></td>
                            <td>${data.expirationDateTime ? data.expirationDateTime : 'No expira'}</td>
                            <td>
                                <button class="mdl-button mdl-js-button mdl-button--icon mdl-button--colored" onclick="openEditModal(${data.id}, '${urlOriginal}')">
                                    <i class="material-icons">edit</i>
                                </button>
                                <button class="mdl-button mdl-js-button mdl-button--icon mdl-button--accent" onclick="openDeleteModal(${data.id})">
                                    <i class="material-icons">delete</i>
                                </button>
                            </td>
                        `;
                        componentHandler.upgradeElements(newRow);
                        closeShortenModal();
                        document.getElementById('shortenUrl').value = '';
                        document.getElementById('expirationDate').value = '';
                        document.getElementById('expirationTime').value = '';
                        document.getElementById('password').value = '';
                        expirationCheckbox.checked = false;
                        expirationDateContainer.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    errorElement.textContent = 'Hubo un error al acortar la URL.';
                    errorElement.style.display = "block";
                    shortenForm.classList.add('shake');
                    setTimeout(() => {
                        shortenForm.classList.remove('shake');
                    }, 500);
                });
            };

            window.submitEditForm = function() {
                const formData = new FormData(document.getElementById('editForm'));
                const editPasswordCheckbox = document.getElementById('editPasswordCheckbox');
                const editRemovePassword = document.getElementById('editRemovePassword');
                
                // Si el checkbox de contraseña está deshabilitado y no está marcado, 
                // significa que queremos eliminar la contraseña existente
                if (editPasswordCheckbox.disabled && !editPasswordCheckbox.checked) {
                    formData.append('removePassword', '1');
                } else {
                    formData.append('removePassword', editRemovePassword.value);
                }

                fetch('edit_link.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeEditModal();
                        location.reload();
                    } else {
                        alert('Error al editar el enlace: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al editar el enlace');
                });
            };

            window.openDeleteModal = function(id) {
                document.getElementById('deleteId').value = id;
                deleteDialog.showModal();
            };

            // Añade esto después de las otras funciones en el script
            function associateGuestLink(index) {
                const encryptedLinks = localStorage.getItem('guest_links');
                if (encryptedLinks) {
                    decryptData(encryptedLinks)
                        .then(decryptedData => {
                            const guestLinks = JSON.parse(decryptedData);
                            const linkToAssociate = guestLinks[index];
                            
                            // Generar un token único para esta operación
                            const associationToken = Math.random().toString(36).substr(2, 9);
                            
                            // Almacenar el token y el enlace en sessionStorage
                            sessionStorage.setItem('associationToken', associationToken);
                            sessionStorage.setItem('linkToAssociate', JSON.stringify(linkToAssociate));
                            
                            // Mostrar el modal de login/registro
                            showLoginModal(associationToken);
                        })
                        .catch(error => {
                            console.error('Error al procesar el enlace de invitado:', error);
                        });
                }
            }

            function showLoginModal(token) {
                const dialog = document.querySelector('#login-modal');
                if (!dialog.showModal) {
                    dialogPolyfill.registerDialog(dialog);
                }
                dialog.querySelector('#login-link').onclick = function(e) {
                    e.preventDefault();
                    window.location.href = `login.php?token=${token}`;
                };
                dialog.querySelector('#register-link').onclick = function(e) {
                    e.preventDefault();
                    window.location.href = `registro.php?token=${token}`;
                };
                dialog.showModal();
            }

            <?php if ($showAssociateModal): ?>
            console.log('showAssociateModal:', <?php echo json_encode($showAssociateModal); ?>);
            console.log('associationToken:', <?php echo json_encode($associationToken ?? null); ?>);
            console.log('linkToAssociate:', sessionStorage.getItem('linkToAssociate'));

            const linkToAssociate = JSON.parse(sessionStorage.getItem('linkToAssociate'));
            if (linkToAssociate) {
                const dialog = document.querySelector('#associate-modal');
                if (!dialog.showModal) {
                    dialogPolyfill.registerDialog(dialog);
                }
                
                // Actualizar el contenido del modal con los detalles del enlace
                dialog.querySelector('#original-url').textContent = linkToAssociate.url_original;
                dialog.querySelector('#short-url').textContent = linkToAssociate.url;
                
                dialog.querySelector('.confirm-associate').onclick = function() {
                    associateLinkToAccount(linkToAssociate);
                    dialog.close();
                };
                dialog.querySelector('.close').onclick = function() {
                    dialog.close();
                    sessionStorage.removeItem('linkToAssociate');
                    sessionStorage.removeItem('associationToken');
                    <?php unset($_SESSION['associationToken']); ?>
                };
                
                // Actualizar los botones para el efecto ripple
                componentHandler.upgradeElements(dialog.querySelectorAll('.mdl-button'));
                
                dialog.showModal();
            }
            <?php endif; ?>

            console.log('Modal HTML:', document.querySelector('#associate-modal').outerHTML);

            // Manejar el checkbox principal
            document.getElementById('checkbox-all').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    if (checkbox.parentNode.classList.contains('is-checked') !== this.checked) {
                        checkbox.parentNode.classList.toggle('is-checked');
                    }
                });
            });
        });

        function associateLinkToAccount(link) {
            console.log('linkToAssociate:', link);
            fetch('associate_link.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $_SESSION["csrf_token"]; ?>'
                },
                body: JSON.stringify(link)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Enlace asociado correctamente');
                    sessionStorage.removeItem('linkToAssociate');
                    sessionStorage.removeItem('associationToken');
                    <?php unset($_SESSION['associationToken']); ?>
                    location.reload();
                } else {
                    alert('Error al asociar el enlace: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al asociar el enlace');
            });
        }

        // Asegúrate de que este código esté fuera de cualquier evento o función
        document.addEventListener('DOMContentLoaded', function() {
            componentHandler.upgradeDom();
        });

        console.log('CSRF Token:', '<?php echo $_SESSION["csrf_token"]; ?>');
    </script>
</body>
</html>