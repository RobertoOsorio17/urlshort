document.getElementById('acortadorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const urlOriginal = document.getElementById('urlOriginal').value;
    const resultado = document.getElementById('resultado');
    const boton = document.querySelector('button[type="submit"]');
    
    // Mostrar efecto de carga
    resultado.innerHTML = '<div class="mdl-spinner mdl-js-spinner is-active"></div>';
    componentHandler.upgradeElement(resultado.firstChild);
    boton.disabled = true;
    
    fetch('acortar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'url=' + encodeURIComponent(urlOriginal)
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            resultado.textContent = data.error;
        } else {
            resultado.innerHTML = 'URL acortada: <a href="' + data.url + '" target="_blank" class="mdl-button mdl-js-button mdl-button--colored">' + data.url + '</a>';
        }
        document.getElementById('urlOriginal').value = '';
        document.getElementById('urlOriginal').parentNode.classList.remove('is-dirty');
    })
    .catch(error => {
        console.error('Error:', error);
        resultado.textContent = 'Hubo un error al acortar la URL.';
    })
    .finally(() => {
        boton.disabled = false;
    });
});
