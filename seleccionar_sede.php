<?php
session_start();
require_once 'config/auth.php';

// Requiere sesión iniciada (o "Recordarme")
if (!isset($_SESSION['id'])) {
    restaurarSesionDesdeCookie($pdo);
}
if (!isset($_SESSION['id'], $_SESSION['clinica'])) {
    header("Location: login.php");
    exit();
}

$sedes = obtenerSedesUsuario($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (seleccionarSede($pdo, $_POST['centro_id'] ?? -1)) {
        header("Location: index.php");
        exit();
    }
    $error = 'La sede seleccionada no es válida para su usuario.';
}

$sedeActualId = $_SESSION['centro'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Medical Creations | Seleccionar Sede</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <style>
    .sede-opcion { cursor: pointer; transition: all .15s; }
    .sede-opcion:hover { border-color: #007bff !important; background: #f0f7ff; }
    .sede-opcion input { display: none; }
    .sede-opcion.activa { border-color: #007bff !important; background: #e7f1ff; }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box" style="width: 420px; max-width: 95%;">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <a href="#" class="h1"><b>Medical</b> Creations</a>
    </div>
    <div class="card-body">
      <p class="login-box-msg mb-2">Hola <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong></p>

      <?php if (!esAdmin() && count($sedes) === 0): ?>
        <div class="alert alert-warning text-center">
          <i class="fas fa-exclamation-triangle"></i> Su usuario no tiene sedes asignadas.<br>Contacte al administrador.
        </div>
      <?php else: ?>
        <p class="text-center text-muted">Seleccione la sede o centro de atención donde va a trabajar:</p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" id="formSede">
          <?php foreach ($sedes as $s): ?>
            <label class="sede-opcion d-flex align-items-center border rounded p-3 mb-2 w-100 <?= (int)$sedeActualId === (int)$s['id'] ? 'activa' : '' ?>">
              <input type="radio" name="centro_id" value="<?= (int)$s['id'] ?>">
              <i class="fas fa-hospital fa-lg text-primary mr-3"></i>
              <span class="font-weight-bold"><?= htmlspecialchars($s['nombre_centro']) ?></span>
            </label>
          <?php endforeach; ?>

          <?php if (esAdmin()): ?>
            <label class="sede-opcion d-flex align-items-center border rounded p-3 mb-2 w-100 <?= $sedeActualId === 0 ? 'activa' : '' ?>">
              <input type="radio" name="centro_id" value="0">
              <i class="fas fa-globe-americas fa-lg text-success mr-3"></i>
              <span>
                <span class="font-weight-bold">Todas las sedes</span><br>
                <small class="text-muted">Ver y administrar todo. Para registrar información elija una sede.</small>
              </span>
            </label>
          <?php endif; ?>
        </form>
      <?php endif; ?>

      <p class="mt-3 mb-0 text-center">
        <a href="ajax/logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
      </p>
    </div>
  </div>
</div>
<script>
  // Al hacer clic en una sede se envía el formulario directamente
  document.querySelectorAll('.sede-opcion input').forEach(function (radio) {
    radio.addEventListener('change', function () { document.getElementById('formSede').submit(); });
  });
</script>
</body>
</html>
