$(document).ready(function() {
    $('#formLogin').on('submit', function(e) {
        e.preventDefault();

        const datos = {
            usuario: $('#usuario').val(),
            password: $('#password').val()
        };

        $.ajax({
            url: 'ajax/login_proceso.php',
            type: 'POST',
            data: datos,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Redirigir al index del Administrador
                    window.location.href = 'index.php';
                } else {
                    alert(response.message); // Aquí podrías usar SweetAlert2 para que sea más bonito
                }
            },
            error: function() {
                alert("Error de comunicación con el servidor");
            }
        });
    });
});