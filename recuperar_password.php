<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Medical Creations | Recuperar Contraseña</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <a href="login.php" class="h1"><b>Medical</b> Creations</a>
    </div>
    <div class="card-body">
      <p class="login-box-msg">¿Olvidaste tu contraseña? Escribe tu correo y te enviaremos un enlace para crear una nueva.</p>

      <form id="formRecuperar">
        <div class="input-group mb-3">
          <input type="email" id="email" name="email" class="form-control" placeholder="Correo electrónico" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-envelope"></span>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block" id="btnRecuperar">Enviar enlace</button>
          </div>
        </div>
      </form>

      <p class="mt-3 mb-1">
        <a href="login.php">Volver al inicio de sesión</a>
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
    $('#formRecuperar').on('submit', function(e) {
        e.preventDefault();

        const btn = $('#btnRecuperar');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

        $.ajax({
            url: 'ajax/solicitar_recuperacion.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    // dev_link solo llega en desarrollo local (XAMPP no envía correos)
                    const extra = res.dev_link
                        ? '<hr><small>Modo local: <a href="' + $('<div>').text(res.dev_link).html() + '">abrir enlace de recuperación</a></small>'
                        : '';
                    Swal.fire({
                        icon: 'success',
                        title: 'Solicitud enviada',
                        html: $('<div>').text(res.message).html() + extra
                    }).then(() => {
                        if (!res.dev_link) window.location.href = 'login.php';
                    });
                } else {
                    Swal.fire('Atención', res.message, res.status === 'warning' ? 'warning' : 'error');
                }
                btn.prop('disabled', false).text('Enviar enlace');
            },
            error: function() {
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                btn.prop('disabled', false).text('Enviar enlace');
            }
        });
    });
});
</script>
</body>
</html>
