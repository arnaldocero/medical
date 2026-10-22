<?php
session_start();

// Si ya hay sesión activa, o una cookie "Recordarme" válida, entrar directamente
if (!isset($_SESSION['id']) && !empty($_COOKIE['c_remember'])) {
    require_once 'config/db.php';
    require_once 'config/auth.php';
    restaurarSesionDesdeCookie($pdo);
}
if (isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Medical Creations | Iniciar Sesión</title>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <a href="#" class="h1"><b>Medical</b> Creations</a>
    </div>
    <div class="card-body">
      <p class="login-box-msg">Ingresa tus credenciales para iniciar sesión</p>

      <form id="formLogin">
        <div class="input-group mb-3">
          <input type="text" id="usuario" name="usuario" class="form-control" placeholder="Usuario o Email" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-user"></span>
            </div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="password" id="password" name="password" class="form-control" placeholder="Contraseña" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-lock"></span>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-8">
            <div class="icheck-primary">
              <input type="checkbox" id="remember" name="recordarme" value="1">
              <label for="remember">
                Recordarme
              </label>
            </div>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
          </div>
          </div>
      </form>

      <p class="mb-1 mt-3">
        <a href="recuperar_password.php">Olvidé mi contraseña</a>
      </p>
    </div>
    </div>
  </div>
<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    $('#formLogin').on('submit', function(e) {
        e.preventDefault();

        // Bloqueamos el botón para evitar doble clic
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Cargando...');

        const datos = {
            usuario: $('#usuario').val(),
            password: $('#password').val(),
            recordarme: $('#remember').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: 'ajax/login_proceso.php', // Asegúrate de que esta ruta sea correcta
            type: 'POST',
            data: datos,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Bienvenido!',
                        text: 'Acceso correcto, redireccionando...',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = response.redirect || 'index.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                    btn.prop('disabled', false).text('Entrar');
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de red',
                    text: 'No se pudo conectar con el servidor.'
                });
                btn.prop('disabled', false).text('Entrar');
            }
        });
    });
});
</script>
</body>
</html>