<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['clinica'])) {
    echo '<div class="p-3 text-center text-danger">Sesión expirada. Redireccionando...</div>';
    exit;
}

$clinica_id = $_SESSION['clinica'];
$fecha      = $_POST['fecha'] ?? date('Y-m-d');
$centro_id  = $_POST['centro_id'] ?? '';

// Construcción dinámica de la query
$sql = "SELECT cm.id, cm.fecha_cita, cm.hora_inicio, cm.hora_fin, cm.estado, cm.motivo_consulta,
               p.nombre AS paciente, m.nombre AS medico, c.nombre_centro, con.nombre_consultorio
        FROM citas_medicas cm
        INNER JOIN usuarios_clinicas p ON cm.paciente_id = p.id
        INNER JOIN usuarios_clinicas m ON cm.medico_id = m.id
        INNER JOIN centros_medicos c ON cm.centro_id = c.id
        INNER JOIN consultorios con ON cm.consultorio_id = con.id
        WHERE cm.clinica_id = ? AND cm.fecha_cita = ?";

$params = [$clinica_id, $fecha];

if (!empty($centro_id)) {
    $sql .= " AND cm.centro_id = ?";
    $params[] = $centro_id;
}

$sql .= " ORDER BY cm.hora_inicio ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($citas) === 0) {
    echo '<div class="p-4 text-center text-muted"><strong>No hay citas agendadas para los criterios seleccionados.</strong></div>';
    exit;
}
?>

<div class="table-responsive">
    <table class="table table-hover table-striped m-0 vertical-align-middle">
        <thead class="thead-dark">
            <tr>
                <th>Hora</th>
                <th>Paciente</th>
                <th>Especialista</th>
                <th>Sede / Consultorio</th>
                <th class="text-center">Estado</th>
                <th class="text-right">Acciones Disponibles</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($citas as $c): 
                // Definir el color de la etiqueta según el estado de tu ENUM
                $badge_class = 'badge-primary'; // 'Asignada' Default
                if ($c['estado'] === 'En Sala')  $badge_class = 'badge-warning text-dark';
                if ($c['estado'] === 'Atendida') $badge_class = 'badge-success';
                if ($c['estado'] === 'Cancelada') $badge_class = 'badge-danger';
            ?>
                <tr>
                    <td>
                        <strong class="text-indigo"><i class="far fa-clock"></i> <?= date('g:i A', strtotime($c['hora_inicio'])) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($c['paciente']) ?></td>
                    <td>Dr(a). <?= htmlspecialchars($c['medico']) ?></td>
                    <td>
                        <small class="d-block text-muted"><?= htmlspecialchars($c['nombre_centro']) ?></small>
                        <span class="badge badge-light border"><i class="fas fa-door-open"></i> <?= htmlspecialchars($c['nombre_consultorio']) ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $badge_class ?> p-2" style="font-size: 0.9rem;"><?= $c['estado'] ?></span>
                    </td>
                    <td class="text-right">
                        <div class="btn-group">
                            <?php if ($c['estado'] === 'Asignada'): ?>
                                <button type="button" class="btn btn-warning btn-sm btnCambiarEstado" data-id="<?= $c['id'] ?>" data-estado="En Sala" title="Registrar llegada a Sala de Espera">
                                    <i class="fas fa-sign-in-alt"></i> Llegó (En Sala)
                                </button>
                            <?php endif; ?>

                            <?php if ($c['estado'] === 'En Sala'): ?>
                                <button type="button" class="btn btn-success btn-sm btnCambiarEstado" data-id="<?= $c['id'] ?>" data-estado="Atendida" title="Finalizar consulta médica">
                                    <i class="fas fa-user-check"></i> Atendido
                                </button>
                            <?php endif; ?>

                            <?php if ($c['estado'] !== 'Cancelada' && $c['estado'] !== 'Atendida'): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm btnCambiarEstado" data-id="<?= $c['id'] ?>" data-estado="Cancelada" title="Cancelar Cita">
                                    <i class="fas fa-times-circle"></i> Cancelar
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-light btn-sm" disabled>Sin acciones</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>