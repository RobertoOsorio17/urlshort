document.addEventListener('DOMContentLoaded', function() {
    const expirationCheckbox = document.getElementById('expirationCheckbox');
    const expirationDateContainer = document.getElementById('expirationDateContainer');
    const passwordCheckbox = document.getElementById('passwordCheckbox');
    const passwordContainer = document.getElementById('passwordContainer');

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
            
            const urlOriginal = document.getElementById('urlOriginal').value;
            const expirationDate = document.getElementById('expirationDate') ? document.getElementById('expirationDate').value : null;
            const password = document.getElementById('password') ? document.getElementById('password').value : null;
            const resultado = document.getElementById('resultado');
            const boton = document.querySelector('button[type="submit"]');
            
            // Mostrar efecto de carga
            resultado.innerHTML = '<div class="mdl-spinner mdl-js-spinner is-active"></div>';
            componentHandler.upgradeElement(resultado.firstChild);
            boton.disabled = true;
            
            const formData = new FormData();
            formData.append('url', urlOriginal);
            if (expirationDate) {
                formData.append('expirationDate', expirationDate);
            }
            if (password) {
                formData.append('password', password);
            }
            
            fetch('acortar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                resultado.innerHTML = 'URL acortada: <a href="' + data.url + '" target="_blank" class="mdl-button mdl-js-button mdl-button--colored">' + data.url.replace('http://', '') + '</a>';
                if (data.expirationDate) {
                    resultado.innerHTML += '<p>Expira el: ' + data.expirationDate + '</p>';
                }
                document.getElementById('urlOriginal').value = '';
                document.getElementById('urlOriginal').parentNode.classList.remove('is-dirty');
            })
            .catch(error => {
                console.error('Error:', error);
                resultado.textContent = 'Hubo un error al acortar la URL: ' + error.message;
            })
            .finally(() => {
                boton.disabled = false;
            });
        });
    }
});
