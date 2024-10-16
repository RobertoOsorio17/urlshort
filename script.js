document.addEventListener('DOMContentLoaded', function() {
    const expirationCheckbox = document.getElementById('expirationCheckbox');
    const expirationDateContainer = document.getElementById('expirationDateContainer');
    const passwordCheckbox = document.getElementById('passwordCheckbox');
    const passwordContainer = document.getElementById('passwordContainer');
    const resultado = document.getElementById('resultado');

    if (expirationCheckbox) {
        expirationCheckbox.addEventListener('change', function() {
            expirationDateContainer.style.display = this.checked ? 'block' : 'none';
        });
    }

    if (passwordCheckbox) {
        passwordCheckbox.addEventListener('change', function() {
            passwordContainer.style.display = this.checked ? 'block' : 'none';
        });
    }

    const acortadorForm = document.getElementById('acortadorForm');
    if (acortadorForm) {
        acortadorForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Formulario enviado');
            
            const urlOriginal = document.getElementById('urlOriginal').value;
            const expirationDate = document.getElementById('expirationDate').value;
            const resultado = document.getElementById('resultado');
            const boton = document.querySelector('button[type="submit"]');

            if (expirationDate && !validateExpirationDate(expirationDate)) {
                alert('La fecha de expiración no puede ser anterior a la fecha actual.');
                return;
            }

            console.log('URL original:', urlOriginal);

            resultado.innerHTML = '<div class="mdl-spinner mdl-js-spinner is-active"></div>';
            componentHandler.upgradeElement(resultado.firstChild);
            boton.disabled = true;

            const formData = new FormData(acortadorForm);
            
            console.log('Enviando solicitud a acortar.php');
            fetch('acortar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Respuesta recibida de acortar.php');
                return response.json();
            })
            .then(data => {
                console.log("Respuesta de acortar.php:", data);
                if (data.error) {
                    throw new Error(data.error);
                }
                resultado.innerHTML = 'URL acortada: <a href="' + data.url + '" target="_blank" class="mdl-button mdl-js-button mdl-button--colored">' + data.url.replace('http://', '') + '</a>';
                if (data.expirationDate) {
                    resultado.innerHTML += '<p>Expira el: ' + data.expirationDate + '</p>';
                }
                document.getElementById('urlOriginal').value = '';
                document.getElementById('urlOriginal').parentNode.classList.remove('is-dirty');
                
                updateGuestLinksTable(data);
                console.log('Cookies después de acortar:', document.cookie);
            })
            .catch(error => {
                console.error('Error:', error);
                resultado.textContent = 'Hubo un error al acortar la URL: ' + error.message;
            })
            .finally(() => {
                boton.disabled = false;
                console.log('Proceso de acortamiento completado');
            });
        });
    } else {
        console.error('El formulario #acortadorForm no se encontró en la página');
    }

    function updateGuestLinksTable(newLink) {
        let guestLinks = [];
        const encryptedLinks = localStorage.getItem('guest_links');
        if (encryptedLinks) {
            decryptData(encryptedLinks)
                .then(decryptedData => {
                    console.log('Decrypted data:', decryptedData);
                    try {
                        guestLinks = JSON.parse(decryptedData);
                    } catch (error) {
                        console.error('Error parsing decrypted data:', error);
                        guestLinks = [];
                    }
                    guestLinks.unshift(newLink);
                    return encryptData(JSON.stringify(guestLinks));
                })
                .then(encryptedData => {
                    localStorage.setItem('guest_links', encryptedData);
                    displayGuestLinks();
                })
                .catch(error => {
                    console.error('Error in updateGuestLinksTable:', error);
                });
        } else {
            guestLinks.push(newLink);
            encryptData(JSON.stringify(guestLinks))
                .then(encryptedData => {
                    localStorage.setItem('guest_links', encryptedData);
                    displayGuestLinks();
                })
                .catch(error => {
                    console.error('Error encrypting new guest links:', error);
                });
        }
    }

    function displayGuestLinks() {
        const storedLinks = localStorage.getItem('guest_links');
        if (storedLinks) {
            // Intenta descifrar los datos
            decryptData(storedLinks)
                .then(decryptedData => {
                    let guestLinks;
                    try {
                        guestLinks = JSON.parse(decryptedData);
                    } catch (error) {
                        // Si no se puede parsear, asumimos que los datos no están cifrados
                        console.log('Los datos almacenados no están cifrados. Cifrando...');
                        guestLinks = JSON.parse(storedLinks);
                        // Cifra los datos y guárdalos de nuevo
                        return encryptData(JSON.stringify(guestLinks))
                            .then(encryptedData => {
                                localStorage.setItem('guest_links', encryptedData);
                                return guestLinks;
                            });
                    }
                    return guestLinks;
                })
                .then(guestLinks => {
                    // Aquí va el código para mostrar los enlaces en la tabla
                    const tabla = document.querySelector('.mdl-data-table tbody');
                    if (tabla) {
                        tabla.innerHTML = '';
                        guestLinks.forEach((link, index) => {
                            const newRow = tabla.insertRow(-1);
                            newRow.innerHTML = `
                                <td class="mdl-data-table__cell--non-numeric">${link.url_original}</td>
                                <td class="mdl-data-table__cell--non-numeric">
                                    <a href="${link.url}" target="_blank">${link.url.replace('http://', '')}</a>
                                </td>
                                <td>${link.expirationDate || 'N/A'}</td>
                                <td>
                                    <button class="mdl-button mdl-js-button mdl-button--icon" onclick="showDeleteModal(${index}, '${link.id}')">
                                        <i class="material-icons">delete</i>
                                    </button>
                                    <button class="mdl-button mdl-js-button mdl-button--icon" onclick="associateGuestLink(${index})">
                                        <i class="material-icons">person_add</i>
                                    </button>
                                </td>
                            `;
                        });
                        componentHandler.upgradeElements(tabla);
                    }
                })
                .catch(error => {
                    console.error('Error decrypting guest links:', error);
                });
        }
    }

    function showDeleteModal(index, linkId) {
        const dialog = document.querySelector('#delete-modal');
        if (!dialog.showModal) {
            dialogPolyfill.registerDialog(dialog);
        }
        dialog.querySelector('.confirm-delete').onclick = function() {
            deleteGuestLink(index, linkId);
            dialog.close();
        };
        dialog.querySelector('.close').onclick = function() {
            dialog.close();
        };
        dialog.showModal();
    }

    function deleteGuestLink(index, linkId) {
        const encryptedLinks = localStorage.getItem('guest_links');
        if (encryptedLinks) {
            decryptData(encryptedLinks).then(decryptedData => {
                let guestLinks = JSON.parse(decryptedData);
                guestLinks.splice(index, 1);
                encryptData(JSON.stringify(guestLinks)).then(newEncryptedData => {
                    localStorage.setItem('guest_links', newEncryptedData);
                    displayGuestLinks();
                });
            });
        }
        
        // Eliminar de la base de datos
        fetch('delete_link.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: linkId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Enlace eliminado de la base de datos');
            } else {
                console.error('Error al eliminar el enlace de la base de datos');
            }
        });
    }

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

    // Hacer que las funciones estén disponibles globalmente
    window.deleteGuestLink = deleteGuestLink;
    window.associateGuestLink = associateGuestLink;
    window.showDeleteModal = showDeleteModal;

    // Llamar a displayGuestLinks al cargar la página
    displayGuestLinks();

    console.log('Current localStorage content:', localStorage.getItem('guest_links'));
});

// Definir las funciones fuera del evento DOMContentLoaded para que sean globales

function encryptData(data) {
    return fetch('encrypt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({data: data})
    })
    .then(response => response.json())
    .then(result => {
        if (result.error) {
            throw new Error(result.error);
        }
        return result.encrypted;
    });
}

function decryptData(encryptedData) {
    return fetch('decrypt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({data: encryptedData})
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(result => {
        if (result.error) {
            console.error('Decryption error:', result.error);
            throw new Error(result.error);
        }
        return result.decrypted;
    });
}

// Añade esto al final del archivo script.js

function bulkDelete() {
    const selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert('Por favor, selecciona al menos un enlace para eliminar.');
        return;
    }
    if (confirm('¿Estás seguro de que quieres eliminar los enlaces seleccionados?')) {
        fetch('bulk_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al eliminar los enlaces: ' + data.error);
            }
        });
    }
}

function bulkExtendExpiration() {
    const selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert('Por favor, selecciona al menos un enlace para extender su expiración.');
        return;
    }
    const days = prompt('¿Cuántos días quieres extender la expiración?');
    if (days && !isNaN(days)) {
        fetch('bulk_extend_expiration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ ids: selectedIds, days: parseInt(days) })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al extender la expiración: ' + data.error);
            }
        });
    }
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
        .map(checkbox => checkbox.value)
        .filter(id => id !== 'on'); // Excluir el checkbox principal
}

// Manejar el checkbox principal
document.getElementById('checkbox-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
});

// Añade esta función después de las funciones existentes
function validateExpirationDate(expirationDate) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const expDate = new Date(expirationDate);
    return expDate >= today;
}

function openEditModal(id, url, expirationDate, hasPassword) {
    document.getElementById('editId').value = id;
    document.getElementById('editUrl').value = url;
    
    const editExpirationCheckbox = document.getElementById('editExpirationCheckbox');
    const editExpirationDateContainer = document.getElementById('editExpirationDateContainer');
    const editPasswordCheckbox = document.getElementById('editPasswordCheckbox');
    const editPasswordContainer = document.getElementById('editPasswordContainer');
    const editRemovePassword = document.getElementById('editRemovePassword');
    
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
    editRemovePassword.value = '0';
    
    editDialog.showModal();
    componentHandler.upgradeElements(editDialog);
}

document.getElementById('editPasswordCheckbox').addEventListener('change', function() {
    const editPasswordContainer = document.getElementById('editPasswordContainer');
    const editRemovePassword = document.getElementById('editRemovePassword');
    
    editPasswordContainer.style.display = this.checked ? 'block' : 'none';
    editRemovePassword.value = this.checked ? '0' : '1';
});

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
