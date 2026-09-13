<?php 
session_start();
include 'layout/header.php'; include 'layout/nav.php'; include 'layout/sidebar.php'; 
require_once 'config/db.php';

$clinica_id = $_SESSION['clinica'];

// Obtener Médicos (Rol 2)
$medicos = $pdo->prepare("SELECT id, nombre FROM usuarios_clinicas WHERE clinica_id = ? AND rol_id = 2 AND estado = 1");
$medicos->execute([$clinica_id]);

// Obtener Sedes
$sedes = $pdo->prepare("SELECT id, nombre_centro FROM centros_medicos WHERE clinica_id = ? AND estado = 1");
$sedes->execute([$clinica_id]);
?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>Configuración de Disponibilidad Horaria</h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Nueva Disponibilidad</h3></div>
                    <form id="formDisponibilidad">
                        <div class="card-body">
                            <div class="form-group">
                                <label>1. Seleccione Médico</label>
                                <select name="usuario_id" id="selMedico" class="form-control select2" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach($medicos as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= $m['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>2. Especialidad a ejercer</label>
                                <select name="especialidad_id" id="selEspecialidad" class="form-control" required disabled>
                                    <option value="">Seleccione médico primero...</option>
                                </select>
                            </div>
                            <hr>
                            <div class="form-group">
                                <label>3. Sede de Atención</label>
                                <select name="centro_id" id="selSede" class="form-control" required>
                                    <option value="">Seleccione Sede...</option>
                                    <?php foreach($sedes as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= $s['nombre_centro'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>4. Consultorio</label>
                                <select name="consultorio_id" id="selConsultorio" class="form-control" required disabled>
                                    <option value="">Seleccione sede primero...</option>
                                </select>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Día</label>
                                    <select name="dia_semana" class="form-control" required>
                                        <option value="Lunes">Lunes</option>
                                        <option value="Martes">Martes</option>
                                        <option value="Miercoles">Miércoles</option>
                                        <option value="Jueves">Jueves</option>
                                        <option value="Viernes">Viernes</option>
                                        <option value="Sabado">Sábado</option>
                                        <option value="Domingo">Domingo</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>Duración Cita (min)</label>
                                    <input type="number" name="duracion_cita" class="form-control" value="20" min="5" step="5">
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label>Hora Inicio</label>
                                    <input type="time" name="hora_inicio" class="form-control" required>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label>Hora Fin</label>
                                    <input type="time" name="hora_fin" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary btn-block">Guardar Disponibilidad</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Horarios Configurados</h3></div>
                    <div class="card-body" id="divTablaDisponibilidad">
                        </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>

