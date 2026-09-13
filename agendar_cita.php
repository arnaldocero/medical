<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
require_once 'config/db.php';

$clinica_id = $_SESSION['clinica'];

// 1. CONSULTA: Pacientes activos (Rol 5)
$stmtPacientes = $pdo->prepare("SELECT id, nombre FROM usuarios_clinicas WHERE clinica_id = ? AND rol_id = 5 AND estado = 1 ORDER BY nombre ASC");
$stmtPacientes->execute([$clinica_id]);
$pacientes = $stmtPacientes->fetchAll();

// 2. CONSULTA: Médicos activos
$stmtMedicos = $pdo->prepare("
    SELECT u.id, u.nombre, dm.especialidad 
    FROM usuarios_clinicas u
    INNER JOIN datos_medicos dm ON u.id = dm.usuario_id
    WHERE u.clinica_id = ? AND dm.estado_medico = 1 AND u.estado = 1
    ORDER BY u.nombre ASC
");
$stmtMedicos->execute([$clinica_id]);
$medicos = $stmtMedicos->fetchAll();

// 3. CONSULTA: Sedes (Centros Médicos)
$stmtCentros = $pdo->prepare("SELECT id, nombre_centro FROM centros_medicos WHERE clinica_id = ? AND estado = 1 ORDER BY nombre_centro ASC");
$stmtCentros->execute([$clinica_id]);
$centros = $stmtCentros->fetchAll();
?>

<link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-layer-group text-success"></i> Agendamiento de Citas Paso a Paso</h1>
        </div>
    </section>

    <section class="content">
        <div class="card card-success card-outline">
            <div class="card-header">
                <h3 class="card-title">Asistente de Programación Real (Flujo Controlado)</h3>
            </div>
            <div class="card-body">
                <form id="formAgendarPasoAPaso">
                    <input type="hidden" id="duracion_calculada" name="duracion_calculada">
                    <input type="hidden" id="valor_cita" name="valor_cita">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>1. Seleccione el Paciente <span class="text-danger">*</span></label>
                            <select name="paciente_id" id="paciente_id" class="form-control select2" required>
                                <option value="">-- Seleccione un Paciente --</option>
                                <?php foreach($pacientes as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label>2. Sede de Atención <span class="text-danger">*</span></label>
                            <select name="centro_id" id="centro_id" class="form-control" required>
                                <option value="">-- Seleccione Sede --</option>
                                <?php foreach($centros as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_centro']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label>3. Médico Especialista <span class="text-danger">*</span></label>
                            <select name="medico_id" id="medico_id" class="form-control" disabled required>
                                <option value="">-- Seleccione Primero la Sede --</option>
                                <?php foreach($medicos as $m): ?>
                                    <option value="<?= $m['id'] ?>">Dr(a). <?= htmlspecialchars($m['nombre']) ?> (<?= htmlspecialchars($m['especialidad']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label>4. Consultorio Asignado <span class="text-danger">*</span></label>
                            <select name="consultorio_id" id="consultorio_id" class="form-control" disabled required>
                                <option value="">-- Esperando Sede --</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label>5. Fecha de la Cita <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_cita" id="fecha_cita" class="form-control" min="<?= date('Y-m-d') ?>" disabled required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label>6. Hora de la Cita <span class="text-danger">*</span></label>
                            <select name="hora_inicio" id="hora_inicio" class="form-control" disabled required>
                                <option value="">-- Seleccione Fecha Primero --</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label>7. Procedimiento / CUPS <span class="text-danger">*</span></label>
                            <select name="servicio_id" id="servicio_id" class="form-control" disabled required>
                                <option value="">-- Complete los pasos anteriores --</option>
                            </select>
                        </div>

                        <div class="col-12 mb-3">
                            <div class="callout callout-success bg-light row m-0 p-2 text-center">
                                <div class="col-md-6">
                                    <strong>Duración Parametrizada:</strong> <span id="lblDuracion" class="text-primary">--</span> minutos.
                                </div>
                                <div class="col-md-6">
                                    <strong>Valor a Liquidar:</strong> <span id="lblCosto" class="text-success">$0.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label>Observaciones o Motivo de Consulta</label>
                            <textarea name="motivo_consulta" id="motivo_consulta" class="form-control" rows="2" placeholder="Notas adicionales..."></textarea>
                        </div>
                    </div>

                    <div class="card-footer p-0 pt-3 bg-transparent text-right">
                        <button type="submit" id="btnGuardar" class="btn btn-success btn-lg" disabled>
                            <i class="fas fa-check-circle"></i> Confirmar y Agendar Cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>
