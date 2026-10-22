<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="index.php" class="nav-link">Inicio</a>
    </li>
  </ul>

  <!-- Sede de trabajo actual -->
  <ul class="navbar-nav ml-auto">
    <li class="nav-item">
      <?php $puedeCambiarSede = (isset($_SESSION['rol']) && intval($_SESSION['rol']) === 1) || count($_SESSION['sedes'] ?? []) > 1; ?>
      <a href="<?= $puedeCambiarSede ? 'seleccionar_sede.php' : '#' ?>" class="nav-link" title="<?= $puedeCambiarSede ? 'Cambiar de sede' : 'Sede de trabajo' ?>">
        <i class="fas fa-hospital text-primary"></i>
        <span class="font-weight-bold"><?= htmlspecialchars($_SESSION['centro_nombre'] ?? 'Sin sede') ?></span>
        <?php if ($puedeCambiarSede): ?><i class="fas fa-exchange-alt ml-1 text-muted small"></i><?php endif; ?>
      </a>
    </li>
  </ul>
</nav>
