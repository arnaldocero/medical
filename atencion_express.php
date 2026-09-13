<?php
session_start();
if (!isset($_SESSION['id'])) { 
    header("Location: login.php"); 
    exit(); 
}

include 'layout/header.php';
include 'layout/nav.php';
include 'layout/sidebar.php';
require_once 'config/db.php';

try {
    $sql = "SELECT 
                p.usuario_id AS paciente_id,
                p.documento_identidad,
                p.telefono_contacto,
                u.nombre AS nombre_completo,
                u.email
            FROM pacientes_datos p
            INNER JOIN usuarios_clinicas u ON p.usuario_id = u.id
            ORDER BY u.nombre ASC
            LIMIT 50";
    $stmt = $pdo->query($sql);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pacientes = [];
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-bolt text-warning"></i> Atención Inmediata (Sin Cita Previa)</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="panel_medico.php" class="btn btn-secondary shadow-sm">
                        <i class="fas fa-arrow-left"></i> Volver al Panel
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-warning card-outline shadow">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-users-cog mr-1"></i> Seleccione un paciente para abrir consulta inmediata
                    </h3>
                </div>

                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6 offset-md-3">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-warning text-dark"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" id="filtroPacienteExpress" class="form-control form-control-lg" placeholder="Filtrar por cédula o nombre en tiempo real...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover minimal" id="tablaPacientesExpress">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th style="width: 60px;" class="text-center">#</th>
                                    <th>Documento / Cédula</th>
                                    <th>Nombre del Paciente</th>
                                    <th>Teléfono</th>
                                    <th style="width: 180px;" class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoPacientesExpress">
                                <?php if (!empty($pacientes)): ?>
                                    <?php foreach ($pacientes as $index => $pac): ?>
                                        <tr>
                                            <td class="text-center font-weight-bold"><?= $index + 1 ?></td>
                                            <td>
                                                <span class="badge badge-info p-2" style="font-size: 13px;">
                                                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($pac['documento_identidad'] ?: 'Sin Doc.') ?>
                                                </span>
                                            </td>
                                            <td class="font-weight-bold text-uppercase">
                                                <?= htmlspecialchars($pac['nombre_completo']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($pac['telefono_contacto'] ?: 'No registrado') ?></td>
                                            <td class="text-center">
                                                <button type="button" 
                                                        class="btn btn-success btn-sm btn-atender-express shadow-sm" 
                                                        data-id="<?= $pac['paciente_id'] ?>"
                                                        data-nombre="<?= htmlspecialchars($pac['nombre_completo']) ?>">
                                                    <i class="fas fa-stethoscope"></i> Atender Express
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No se encontraron pacientes registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL CON CONTROL DE CASCADA Y ESTADO DE AGENDA -->
<div class="modal fade" id="modalCitaExpress" tabindex="-1" role="dialog" aria-labelledby="modalCitaExpressLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title font-weight-bold" id="modalCitaExpressLabel">
                    <i class="fas fa-clipboard-list mr-1"></i> Parámetros de Atención Inmediata
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formCitaExpress">
                <div class="modal-body">
                    <input type="hidden" id="express_paciente_id" name="paciente_id">
                    <input type="hidden" id="express_valor_cita" name="valor_cita" value="0">
                    
                    <div class="form-group">
                        <label>Paciente Seleccionado:</label>
                        <input type="text" id="express_paciente_nombre" class="form-control font-weight-bold bg-light" readonly>
                    </div>

                    <div class="row">
                        <!-- 1. Centro / Sede -->
                        <div class="col-md-6 form-group">
                            <label for="express_centro_id"><span class="text-danger">*</span> Centro Médico / Sede:</label>
                            <select id="express_centro_id" name="centro_id" class="form-control" required>
                                <option value="">Cargando sedes...</option>
                            </select>
                        </div>

                        <!-- 2. Médico Tratante (Se activa tras elegir Centro) -->
                        <div class="col-md-6 form-group">
                            <label for="express_medico_id"><span class="text-danger">*</span> Médico Tratante:</label>
                            <select id="express_medico_id" name="medico_id" class="form-control" required disabled>
                                <option value="">-- Seleccione primero una sede --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <!-- 3. Consultorio (Se activa tras elegir Médico) -->
                        <div class="col-md-6 form-group">
                            <label for="express_consultorio_id"><span class="text-danger">*</span> Consultorio Asignado:</label>
                            <select id="express_consultorio_id" name="consultorio_id" class="form-control" required disabled>
                                <option value="">-- Seleccione primero un médico --</option>
                            </select>
                        </div>

                        <!-- 4. Servicio / Procedimiento -->
                        <div class="col-md-6 form-group">
                            <label for="express_servicio_id"><span class="text-danger">*</span> Servicio / Procedimiento:</label>
                            <select id="express_servicio_id" name="servicio_id" class="form-control" required>
                                <option value="">Cargando servicios...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Alerta de estado de agenda del médico -->
                    <div id="alertaAgendaHoy" class="alert d-none mt-2 mb-3"></div>

                    <div class="row">
                        <div class="col-md-12 form-group mb-0">
                            <label for="express_motivo">Motivo de la Consulta / Observación:</label>
                            <textarea id="express_motivo" name="motivo_consulta" class="form-control" rows="2" placeholder="Ingrese el motivo de la consulta inmediata...">Atención espontánea (Sin cita previa)</textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btnGuardarCitaExpress" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Iniciar Atención
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>