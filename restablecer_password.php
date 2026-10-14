<?php
require_once 'config/db.php';

$token = trim($_GET['token'] ?? '');
$valido = false;

if (!empty($token)) {
    // Verificamos que el token exista y que no haya expirado
    $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios_clinicas WHERE token_recuperacion = ? AND expira_recuperacion > NOW() AND estado = 1");
    $stmt->execute([hash('sha256', $token)]); // En BD se guarda solo el hash del token
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $valido = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ClinicaSoft | Restablecer Contraseña</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>Clinica</b>Soft</a>
  </div>
  <div class="card card-outline card-warning shadow">
    <div class="card-body login-card-body">
      <?php if ($valido): ?>
        <p class="login-box-msg">Hola <strong><?= htmlspecialchars($user['nombre']) ?></strong>, escribe tu nueva clave.</p>
        
        <form id="formRestablecer">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          
          <div class="input-group mb-3">
            <input type="password" name="password" id="pass1" class="form-control" placeholder="Nueva Contraseña" required minlength="6">
            <div class="input-group-append">
              <div class="input-group-text"><span class="fas fa-lock"></span></div>
            </div>
          </div>
          
          <div class="input-group mb-3">
            <input type="password" id="pass2" class="form-control" placeholder="Confirmar Contraseña" required minlength="6">
            <div class="input-group-append">
              <div class="input-group-text"><span class="fas fa-lock"></span></div>
            </div>
          </div>
          
          <button type="submit" class="btn btn-warning btn-block" id="btnUpdatePass">Actualizar Contraseña</button>
        </form>
      <?php else: ?>
        <div class="alert alert-danger text-center">
          <i class="fas fa-exclamation-triangle"></i> El enlace no es válido o ya ha expirado. Por seguridad, solicite uno nuevo.
        </div>
        <a href="login.php" class="btn btn-default btn-block">Volver al Login</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('#formRestablecer').on('submit', function(e) {
        e.preventDefault();
        
        if ($('#pass1').val() !== $('#pass2').val()) {
            Swal.fire('Atención', 'Las contraseñas no coinciden.', 'warning');
            return;
        }

        const btn = $('#btnUpdatePass');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

        $.ajax({
            url: 'ajax/guardar_nueva_password.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: res.message,
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = 'login.php';
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.prop('disabled', false).text('Actualizar Contraseña');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo conectar al servidor.', 'error');
                btn.prop('disabled', false).text('Actualizar Contraseña');
            }
        });
    });
});
</script>
</body>
</html>