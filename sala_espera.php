<?php 
// Removida la comprobación de sesión para permitir el acceso público directo
include 'layout/header.php'; 
// Removidos nav.php y sidebar.php para maximizar el espacio de la pantalla y ocultar el menú del sistema
require_once 'config/db.php';
?>

<style>
  /* Estilos específicos optimizados para la visualización de la sala de espera a pantalla completa */
  body {
    background-color: #f4f6f9;
  }
  /* Quitamos márgenes izquierdos que AdminLTE aplica por defecto debido al sidebar */
  .content-wrapper-sala {
    padding: 20px;
    margin-left: 0 !important;
    min-height: 100vh;
  }
  .tabla-sala { font-size: 1.8rem; } /* Aumentamos un poco el tamaño de letra para pantallas/televisores */
  .tabla-sala th { font-size: 1.4rem; background-color: #1e3c72; color: white; text-align: center; }
  .tabla-sala td { vertical-align: middle !important; text-align: center; padding: 15px !important; }
  .reloj-panel { font-size: 2.2rem; font-weight: bold; color: #1e3c72; }
  .llamado-row { animation: pulse 2s infinite; background-color: #fff3cd !important; font-weight: bold; }
  @keyframes pulse {
    0% { background-color: #fff3cd; }
    50% { background-color: #ffeba0; }
    100% { background-color: #fff3cd; }
  }
  /* Animación de parpadeo de fondo estilo llamado de aeropuerto/clínica */
  @keyframes parpadeoLlamado {
    0% { background-color: rgba(220, 53, 69, 0.2); } /* Rojo claro */
    50% { background-color: rgba(220, 53, 69, 0.6); } /* Rojo intenso */
    100% { background-color: rgba(220, 53, 69, 0.2); }
  }

  .fila-llamado-activa {
    animation: parpadeoLlamado 1.5s infinite;
    border: 3px solid #dc3545 !important;
    color: #000 !important;
    font-size: 1.1em;
  }

  .fila-llamado-activa td {
    vertical-align: middle !important;
  }
</style>

<!-- Cambiamos "content-wrapper" por una clase personalizada para que no dependa del sidebar -->
<div class="content-wrapper-sala">
    <section class="content-header mb-3">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-8 col-md-6">
                    <h1><i class="fas fa-hourglass-half text-primary"></i> Sala de Espera Virtual</h1>
                    <p class="text-muted m-0">Monitoreo en tiempo real de pacientes listos para atención.</p>
                </div>
                <div class="col-sm-4 col-md-6 text-right">
                    <div class="reloj-panel" id="liveClock">00:00:00 PM</div>
                    <span class="badge badge-secondary p-2" style="font-size: 1rem;"><i class="far fa-calendar-alt"></i> <?= date('d/m/Y') ?></span>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary shadow-lg">
                <div class="card-header">
                    <h3 class="card-title text-muted" style="font-size: 1.2rem;">
                        <i class="fas fa-info-circle text-info"></i> Por motivos de privacidad y protección de datos, los apellidos se muestran abreviados.
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover m-0 tabla-sala">
                            <thead>
                                <tr>
                                    <th style="width: 15%;"><i class="far fa-clock"></i> Hora Cita</th>
                                    <th style="width: 35%;"><i class="fas fa-user"></i> Paciente</th>
                                    <th style="width: 25%;"><i class="fas fa-user-md"></i> Especialista</th>
                                    <th style="width: 25%;"><i class="fas fa-door-open"></i> Consultorio / Área</th>
                                </tr>
                            </thead>
                            <tbody id="tbodySalaEspera">
                                <tr>
                                    <td colspan="4" class="text-center p-5 text-muted" style="font-size: 1.5rem;">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Conectando con el módulo de recepción...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<audio id="sndAlerta" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-600.wav" preload="auto"></audio>

<?php include 'layout/footer.php'; ?>