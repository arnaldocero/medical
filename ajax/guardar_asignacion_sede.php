<?php
/**
 * Guarda las sedes asignadas a un usuario (personal, médico o paciente).
 * Solo se modifican las sedes a las que el editor tiene acceso; las demás se conservan.
 */
session_start();
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../config/roles.php';
header('Content-Type: application/json');

requerirSesionAJAX();

$usuario_id = intval($_POST['idUsuarioAsignar'] ?? 0);
$clinica_id = $_SESSION['clinica'];
$marcadas = array_map('intval', (array) ($_POST['centros'] ?? []));

$stmtU = $pdo->prepare("SELECT rol_id FROM usuarios_clinicas WHERE id = ? AND clinica_id = ?");
$stmtU->execute([$usuario_id, $clinica_id]);
$rolDestino = $stmtU->fetchColumn();

if ($rolDestino === false) {
    echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado.']);
    exit();
}

if (esRolMedico($rolDestino)) {
    requerirPermisoAJAX('medicos.administrar');
} elseif (esRolPaciente($rolDestino)) {
    requerirPermisoAJAX('pacientes.gestionar');
} else {
    requerirPermisoAJAX('usuarios.asignar_sede');
}

validarUsuarioEnSedeAJAX($pdo, $usuario_id);

// Sedes válidas de la clínica y sedes que este editor puede modificar
$stmtC = $pdo->prepare("SELECT id FROM centros_medicos WHERE clinica_id = ? AND estado = 1");
$stmtC->execute([$clinica_id]);
$sedesClinica = array_map('intval', $stmtC->fetchAll(PDO::FETCH_COLUMN));
$editables = esAdmin() ? $sedesClinica : array_values(array_intersect($sedesClinica, array_map('intval', $_SESSION['sedes'] ?? [])));

$stmtA = $pdo->prepare("SELECT centro_id FROM asignacion_centros WHERE usuario_id = ?");
$stmtA->execute([$usuario_id]);
$actuales = array_map('intval', $stmtA->fetchAll(PDO::FETCH_COLUMN));

// Resultado final = sedes que no puede tocar (se conservan) + las marcadas que sí puede tocar
$conservar = array_diff($actuales, $editables);
$nuevas = array_intersect($marcadas, $editables);
$final = array_values(array_unique(array_merge($conservar, $nuevas)));

if (empty($final)) {
    echo json_encode(['status' => 'warning', 'message' => 'Debe dejar al menos una sede asignada.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Quitar las sedes editables que se desmarcaron
    $quitar = array_diff(array_intersect($actuales, $editables), $nuevas);
    if (!empty($quitar)) {
        $pdo->prepare("DELETE FROM asignacion_centros WHERE usuario_id = ? AND centro_id IN (" . implode(',', array_map('intval', $quitar)) . ")")
            ->execute([$usuario_id]);
    }

    // Agregar las nuevas
    foreach ($nuevas as $centro_id) {
        asignarUsuarioASede($pdo, $usuario_id, $centro_id);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Sedes actualizadas correctamente']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('guardar_asignacion_sede: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'No se pudieron guardar las sedes.']);
}
