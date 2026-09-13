<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); } ?>
<?php include 'layout/header.php'; ?>
<?php include 'layout/nav.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <h1>Panel de Administración</h1>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <p>Bienvenido al sistema de gestión de la clínica.</p>
    </div>
  </section>
</div>

<?php include 'layout/footer.php'; ?>