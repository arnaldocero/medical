<?php 
session_start();
if (!isset($_SESSION['id'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Requerir el archivo de seguridad para poder usar la función tienePermiso()
require_once 'config/security.php';
?>
<?php include 'layout/header.php'; ?>
<?php include 'layout/nav.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<?php
// Determinar el título dinámico según los permisos principales del usuario
$tituloPanel = "Panel Principal";

if (tienePermiso('usuarios.administrar')) {
    $tituloPanel = "Panel de Administración";
} elseif (tienePermiso('medico.panel')) {
    $tituloPanel = "Panel Médico";
} elseif (tienePermiso('financiero.facturar')) {
    $tituloPanel = "Panel Financiero";
} elseif (tienePermiso('farmacia.medicamentos')) {
    $tituloPanel = "Panel de Farmacia";
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <h1 class="m-0"><?php echo $tituloPanel; ?></h1>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <p class="mb-4">Bienvenido al sistema de gestión de la clínica. Seleccione una opción rápida para comenzar:</p>
      
      <div class="row">
        <!-- Tarjeta de Atención Médica -->
        <?php if (tienePermiso('medico.panel')): ?>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-success">
            <div class="inner">
              <h3>Consulta</h3>
              <p>Atención a Pacientes</p>
            </div>
            <div class="icon">
              <i class="fas fa-user-md"></i>
            </div>
            <a href="panel_medico.php" class="small-box-footer">Ingresar al panel <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Tarjeta de Agenda de Citas -->
        <?php if (tienePermiso('citas.agendar')): ?>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-info">
            <div class="inner">
              <h3>Agenda</h3>
              <p>Control de Citas</p>
            </div>
            <div class="icon">
              <i class="fas fa-calendar-alt"></i>
            </div>
            <a href="agendar_cita.php" class="small-box-footer">Gestionar citas <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Tarjeta de Atención Express -->
        <?php if (tienePermiso('citas.express')): ?>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-warning">
            <div class="inner">
              <h3>Express</h3>
              <p>Atención Rápida</p>
            </div>
            <div class="icon">
              <i class="fas fa-bolt"></i>
            </div>
            <a href="atencion_express.php" class="small-box-footer">Atender ahora <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Tarjeta de Facturación -->
        <?php if (tienePermiso('financiero.facturar')): ?>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-primary">
            <div class="inner">
              <h3>Caja</h3>
              <p>Facturación</p>
            </div>
            <div class="icon">
              <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <a href="facturacion.php" class="small-box-footer">Ir a facturación <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Tarjeta de Farmacia -->
        <?php if (tienePermiso('farmacia.medicamentos')): ?>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-danger">
            <div class="inner">
              <h3>Farmacia</h3>
              <p>Inventario y Despachos</p>
            </div>
            <div class="icon">
              <i class="fas fa-pills"></i>
            </div>
            <a href="farmacia_medicamentos.php" class="small-box-footer">Ir a farmacia <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Tarjeta de Administración General -->
        <?php if (tienePermiso('usuarios.administrar')): ?>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-secondary">
            <div class="inner">
              <h3>Ajustes</h3>
              <p>Gestión del Sistema</p>
            </div>
            <div class="icon">
              <i class="fas fa-cogs"></i>
            </div>
            <a href="usuarios.php" class="small-box-footer">Administrar usuarios <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </section>
</div>

<?php include 'layout/footer.php'; ?>