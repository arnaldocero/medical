<?php
// Asegurar la importación del archivo de seguridad
require_once 'config/db.php';
require_once 'config/security.php';
include_once 'modal_atencion_express.php';
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="index.php" class="brand-link">
    <img src="dist/img/AdminLTELogo.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
    <span class="brand-text font-weight-light">Mi Clínica</span>
  </a>

  <div class="sidebar">
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <img src="dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
      </div>
      <div class="info">
        <a href="#" class="d-block"><?php echo htmlspecialchars($_SESSION['nombre']); ?></a>
      </div>
    </div>

    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        
        <li class="nav-header">MÓDULOS CLÍNICOS</li>
        
        <!-- CATEGORÍA: INFRAESTRUCTURA -->
        <?php if (tienePermiso('infraestructura.centros') || tienePermiso('infraestructura.consultorios') || tienePermiso('infraestructura.especialidades')): ?>
        <li class="nav-item has-treeview">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-hospital"></i>
            <p>
              Infraestructura
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview pl-2">
            <?php if (tienePermiso('infraestructura.centros')): ?>
            <li class="nav-item">
              <a href="centros.php" class="nav-link">
                <i class="fas fa-map-marker-alt nav-icon"></i>
                <p>Centros de Atención</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('infraestructura.consultorios')): ?>
            <li class="nav-item">
              <a href="consultorios.php" class="nav-link">
                <i class="fas fa-door-open nav-icon"></i>
                <p>Consultorios</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('infraestructura.especialidades')): ?>
            <li class="nav-item">
              <a href="especialidades.php" class="nav-link">
                <i class="fas fa-briefcase-med nav-icon"></i>
                <p>Especialidades</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- CATEGORÍA: PERSONAL Y PACIENTES -->
        <?php if (tienePermiso('usuarios.administrar') || tienePermiso('medicos.administrar') || tienePermiso('pacientes.gestionar') || (isset($_SESSION['rol']) && $_SESSION['rol'] == 1) || tienePermiso('roles.administrar')): ?>
        <li class="nav-item has-treeview">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-users"></i>
            <p>
              Personal y Pacientes
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview pl-2">
            <?php if (tienePermiso('usuarios.administrar')): ?>
            <li class="nav-item">
              <a href="usuarios.php" class="nav-link">
                <i class="fas fa-user-shield nav-icon"></i>
                <p>Gestión Usuarios</p>
              </a>
            </li>
            <?php endif; ?>

            <!-- NUEVO: GESTIÓN DE ROLES (SOLO VISIBLE PARA ADMIN O CON PERMISO) -->
            <?php if ((isset($_SESSION['rol']) && $_SESSION['rol'] == 1) || tienePermiso('roles.administrar')): ?>
            <li class="nav-item">
              <a href="roles.php" class="nav-link">
                <i class="fas fa-user-tag nav-icon text-warning"></i>
                <p>Gestión Roles</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('medicos.administrar')): ?>
            <li class="nav-item">
              <a href="medicos.php" class="nav-link">
                <i class="fas fa-user-md nav-icon"></i>
                <p>Gestión Médicos</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('pacientes.gestionar')): ?>
            <li class="nav-item">
              <a href="pacientes.php" class="nav-link">
                <i class="fas fa-user-injured nav-icon"></i>
                <p>Gestión Pacientes</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- CATEGORÍA: ATENCIÓN MÉDICA -->
        <?php if (tienePermiso('citas.agendar') || tienePermiso('citas.express') || tienePermiso('citas.estados') || tienePermiso('sala.espera') || tienePermiso('medico.panel') || tienePermiso('agenda.oferta') || tienePermiso('servicios.cups') || tienePermiso('diagnosticos.cie10')): ?>
        <li class="nav-item has-treeview"> 
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-notes-medical"></i>
            <p>
              Atención Médica
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview pl-2">
            <?php if (tienePermiso('citas.express')): ?>
            <li class="nav-item">
              <a href="atencion_express.php" class="nav-link text-warning">
                <i class="fas fa-bolt nav-icon"></i>
                <p>Atención Express</p>
              </a>
            </li>
            <?php endif; ?>

            <?php if (tienePermiso('citas.agendar')): ?>
            <li class="nav-item">
              <a href="agendar_cita.php" class="nav-link">
                <i class="fas fa-calendar-plus nav-icon text-success"></i>
                <p>Agendar Cita</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('citas.estados')): ?>
            <li class="nav-item">
              <a href="control_estados_citas.php" class="nav-link">
                <i class="fas fa-tasks nav-icon text-info"></i>
                <p>Control Estados (Ingresos)</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('sala.espera')): ?>
            <li class="nav-item">
              <a href="sala_espera.php" class="nav-link">
                <i class="fas fa-hourglass-half nav-icon"></i>
                <p>Sala de Espera</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('medico.panel')): ?>
            <li class="nav-item">
              <a href="panel_medico.php" class="nav-link">
                <i class="fas fa-user-md nav-icon text-success"></i>
                <p>Panel Atención Médica</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('agenda.oferta')): ?>
            <li class="nav-item">
              <a href="disponibilidad.php" class="nav-link">
                <i class="fas fa-calendar-alt nav-icon"></i>
                <p>Agendas Médicas (Oferta)</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('servicios.cups')): ?>
            <li class="nav-item">
              <a href="servicios.php" class="nav-link">
                <i class="fas fa-hand-holding-medical nav-icon"></i>
                <p>Gestión Servicios (CUPS)</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('diagnosticos.cie10')): ?>
            <li class="nav-item">
              <a href="diagnosticos.php" class="nav-link">
                <i class="fas fa-stethoscope nav-icon"></i>
                <p>Gestión Diagnósticos</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- CATEGORÍA: ÁREA FINANCIERA -->
        <?php if (tienePermiso('financiero.eps') || tienePermiso('financiero.manuales') || tienePermiso('financiero.contratos') || tienePermiso('financiero.dian') || tienePermiso('financiero.facturar') || tienePermiso('financiero.historial') || tienePermiso('financiero.rips')): ?>
        <li class="nav-item has-treeview">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-file-invoice-dollar"></i>
            <p>
              Área Financiera
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview pl-2">
            <?php if (tienePermiso('financiero.eps')): ?>
            <li class="nav-item">
              <a href="eps.php" class="nav-link">
                <i class="fas fa-building nav-icon"></i>
                <p>Gestión EPS</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('financiero.manuales')): ?>
            <li class="nav-item">
              <a href="manuales.php" class="nav-link">
                <i class="fas fa-book nav-icon"></i>
                <p>Manuales Tarifarios</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('financiero.contratos')): ?>
            <li class="nav-item">
              <a href="contratos.php" class="nav-link">
                <i class="fas fa-file-contract nav-icon"></i>
                <p>Gestión Contratos</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('financiero.dian')): ?>
            <li class="nav-item">
              <a href="dian_resoluciones.php" class="nav-link">
                <i class="fas fa-percentage nav-icon"></i>
                <p>Resoluciones DIAN</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('financiero.facturar')): ?>
            <li class="nav-item">
              <a href="facturacion.php" class="nav-link">
                <i class="fas fa-calculator nav-icon"></i>
                <p>Panel Facturación</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('financiero.historial')): ?>
            <li class="nav-item">
              <a href="historial_facturas.php" class="nav-link">
                <i class="fas fa-history nav-icon"></i>
                <p>Historial Facturas</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('financiero.rips')): ?>
            <li class="nav-item">
              <a href="rips.php" class="nav-link">
                <i class="fas fa-file-medical-alt nav-icon text-warning"></i>
                <p>Generar RIPS</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- CATEGORÍA: FARMACIA -->
        <?php if (tienePermiso('farmacia.medicamentos') || tienePermiso('farmacia.crear_formula') || tienePermiso('farmacia.despachar')): ?>
        <li class="nav-item has-treeview">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-pills"></i>
            <p>
              Farmacia y Medicamentos
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview pl-2">
            <?php if (tienePermiso('farmacia.medicamentos')): ?>
            <li class="nav-item">
              <a href="farmacia_medicamentos.php" class="nav-link">
                <i class="fas fa-capsules nav-icon"></i>
                <p>Gestión Medicamentos</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('farmacia.crear_formula')): ?>
            <li class="nav-item">
              <a href="crear_formula.php" class="nav-link">
                <i class="fas fa-file-medical nav-icon"></i>
                <p>Crear Fórmulas</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('farmacia.despachar')): ?>
            <li class="nav-item">
              <a href="despacho_farmacia.php" class="nav-link">
                <i class="fas fa-dolly nav-icon"></i>
                <p>Despachar Fórmulas</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- CATEGORÍA: INVENTARIO GENERAL -->
        <?php if (tienePermiso('inventario.articulos') || tienePermiso('inventario.movimientos')): ?>
        <li class="nav-item has-treeview">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-boxes"></i>
            <p>
              Inventario de Artículos
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview pl-2">
            <?php if (tienePermiso('inventario.articulos')): ?>
            <li class="nav-item">
              <a href="inventario_articulos.php" class="nav-link">
                <i class="fas fa-tags nav-icon"></i>
                <p>Gestión de Artículos</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (tienePermiso('inventario.movimientos')): ?>
            <li class="nav-item">
              <a href="reporte_movimientos.php" class="nav-link">
                <i class="fas fa-history nav-icon"></i>
                <p>Historial de Movimientos</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-4">
          <a href="ajax/logout.php" class="nav-link text-danger">
            <i class="nav-icon fas fa-power-off"></i>
            <p>Cerrar Sesión</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>