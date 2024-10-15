<?php
session_start();
require_once 'db_connect.php';

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
if (isset($_GET['associate']) && isset($_SESSION['associationToken'])) {
    $showAssociateModal = true;
    $associationToken = $_SESSION['associationToken'];
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
if (isset($_GET['associate']) && isset($_SESSION['associationToken'])) {
    $showAssociateModal = true;
    $associationToken = $_SESSION['associationToken'];
    // No elimines el token aquí, lo haremos después de asociar el enlace
} else {
    $showAssociateModal = false;
}
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
                        <table class="mdl-data-table mdl-js-data-table mdl-shadow--2dp" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th class="mdl-data-table__cell--non-numeric">URL Original</th>
                                    <th>URL Acortada</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enlaces as $enlace): ?>
                                <tr>
                                    <td class="mdl-data-table__cell--non-numeric"><?= htmlspecialchars($enlace['url_original']) ?></td>
                                    <td>
                                        <a href="<?= $enlace['codigo'] ?>" target="_blank">
                                            <?= 'http://' . $_SERVER['HTTP_HOST'] . '/' . $enlace['codigo'] ?>
                                        </a>
                                    </td>
                                    <td>
                                        <button class="mdl-button mdl-js-button mdl-button--icon mdl-button--colored" onclick="openEditModal(<?= $enlace['id'] ?>, '<?= htmlspecialchars($enlace['url_original'], ENT_QUOTES) ?>', '<?= $enlace['expiration_date'] ?>', '<?= $enlace['has_password'] ?>')">
                                            <i class="material-icons">edit</i>
                                        </button>
                                        <button class="mdl-button mdl-js-button mdl-button--icon mdl-button--accent" onclick="openDeleteModal(<?= $enlace['id'] ?>)">
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
                        <input class="mdl-textfield__input" type="date" id="expirationDate" name="expirationDate">
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
        </div>
        <div class="mdl-dialog__actions">
            <button type="button" class="mdl-button confirm-associate">Asociar</button>
            <button type="button" class="mdl-button close">Cancelar</button>
        </div>
    </dialog>

    <script>
        var deleteId;
        var editDialog = document.getElementById('editModal');
        var deleteDialog = document.getElementById('deleteModal');

        if (!editDialog.showModal) {
            dialogPolyfill.registerDialog(editDialog);
        }
        if (!deleteDialog.showModal) {
            dialogPolyfill.registerDialog(deleteDialog);
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
            editPasswordContainer.style.display = hasPassword === '1' ? 'block' : 'none';
            
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
            document.getElementById('editForm').submit();
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

        var shortenDialog = document.getElementById('shortenModal');

        if (!shortenDialog.showModal) {
            dialogPolyfill.registerDialog(shortenDialog);
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
                editPasswordContainer.style.display = this.checked ? 'block' : 'none';
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
                const id = document.getElementById('editId').value;
                const url = document.getElementById('editUrl').value;
                const expirationDate = document.getElementById('editExpirationDate').value;
                const expirationTime = document.getElementById('editExpirationTime').value;
                const password = document.getElementById('editPassword').value;

                const formData = new FormData();
                formData.append('id', id);
                formData.append('url', url);
                formData.append('csrf_token', document.querySelector('#editForm input[name="csrf_token"]').value);
                if (editExpirationCheckbox.checked && expirationDate) {
                    formData.append('expirationDate', expirationDate);
                    formData.append('expirationTime', expirationTime || '23:59');
                }
                if (editPasswordCheckbox.checked && password) {
                    formData.append('password', password);
                }

                fetch('edit_link.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        // Manejar error
                    } else {
                        // Actualizar la fila en la tabla
                        closeEditModal();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
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
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOMContentLoaded event fired');
                console.log('showAssociateModal:', <?php echo json_encode($showAssociateModal); ?>);
                console.log('associationToken:', <?php echo json_encode($associationToken); ?>);
                console.log('linkToAssociate:', sessionStorage.getItem('linkToAssociate'));

                const linkToAssociate = JSON.parse(sessionStorage.getItem('linkToAssociate'));
                if (linkToAssociate) {
                    const dialog = document.querySelector('#associate-modal');
                    if (!dialog.showModal) {
                        dialogPolyfill.registerDialog(dialog);
                    }
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
                    dialog.showModal();
                }
            });

            function associateLinkToAccount(link) {
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
            <?php endif; ?>
        });
    </script>
</body>
</html>