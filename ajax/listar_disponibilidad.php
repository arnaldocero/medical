<?php
session_start();
require_once '../config/db.php';

$clinica_id = $_SESSION['clinica'];

$sql = "SELECT d.id, u.nombre as medico, e.nombre as especialidad, 
               cm.nombre_centro, con.nombre_consultorio, 
               d.dia_semana, d.hora_inicio, d.hora_fin, d.duracion_cita
        FROM disponibilidad_medica d
        INNER JOIN usuarios_clinicas u ON d.usuario_id = u.id
        INNER JOIN especialidades e ON d.especialidad_id = e.id
        INNER JOIN centros_medicos cm ON d.centro_id = cm.id
        INNER JOIN consultorios con ON d.consultorio_id = con.id
        WHERE d.clinica_id = ? AND d.estado = 1
        ORDER BY d.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$clinica_id]);
$resultados = $stmt->fetchAll();
?>

<table id="tablaDisponibilidad" class="table table-sm table-hover table-striped">
    <thead class="bg-navy">
        <tr>
            <th>Médico / Especialidad</th>
            <th>Ubicación</th>
            <th>Horario</th>
            <th>Acción</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$resultados): ?>
            <tr><td colspan="4" class="text-center">No hay horarios configurados</td></tr>
        <?php endif; ?>
        
        <?php foreach ($resultados as $r): ?>
        <tr>
            <td>
                <strong><?= $r['medico'] ?></strong><br>
                <small class="text-muted"><?= $r['especialidad'] ?></small>
            </td>
            <td>
                <i class="fas fa-hospital"></i> <?= $r['nombre_centro'] ?><br>
                <i class="fas fa-door-open"></i> <?= $r['nombre_consultorio'] ?>
            </td>
            <td>
                <span class="badge badge-info"><?= $r['dia_semana'] ?></span><br>
                <?= date("g:i a", strtotime($r['hora_inicio'])) ?> - <?= date("g:i a", strtotime($r['hora_fin'])) ?><br>
                <small><?= $r['duracion_cita'] ?> min por cita</small>
            </td>
            <td>
                <button class="btn btn-danger btn-sm btnEliminarDispo" data-id="<?= $r['id'] ?>">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
    // Inicializar DataTable para esta tabla específica
    $('#tablaDisponibilidad').DataTable({
        "destroy": true,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json" }
    });
</script>