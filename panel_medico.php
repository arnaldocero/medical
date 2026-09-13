<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-user-md text-primary"></i> Panel de Atención Médica</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card card-outline card-success shadow">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list"></i> Pacientes en Espera (Hoy)</h3>
            </div>
            <div class="card-body">
                <table id="tablaPacientesMedico" class="table table-bordered table-striped text-center">
                    <thead>
                        <tr>
                            <th>Hora Cita</th>
                            <th>Paciente</th>
                            <th>Consultorio</th>
                            <th>Estado Actual</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPacientesMedico">
                        <tr>
                            <td colspan="5" class="text-muted p-4">
                                <i class="fas fa-spinner fa-spin mr-2"></i> Cargando agenda del día...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>

