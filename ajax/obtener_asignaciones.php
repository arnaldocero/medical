<?php
/**
 * Lista las sedes de la clínica con las que tiene asignadas un usuario (personal, médico o paciente).
 * Usado por los modales "Asignar Sedes" de Gestión Usuarios, Médicos y Pacientes.
 */
session_start();
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../config/roles.php';

requerirSesionAJAX();

$usuario_id = intval($_POST['usuario_id'] ?? 0);
$clinica_id = $_SESSION['clinica'];

$stmtU = $pdo->prepare("SELECT rol_id FROM usuarios_clinicas WHERE id = ? AND clinica_id = ?");
$stmtU->execute([$usuario_id, $clinica_id]);
$rolDestino = $stmtU->fetchColumn();

if ($rolDestino === false) {
    echo "<p class='text-danger'>Usuario no encontrado.</p>";
    exit();
}

// Permiso según el tipo de usuario
requerirPermisoAJAX(permisoAsignarSede($rolDestino));

// El editor solo ve registros de sus sedes (el Admin en "Todas" ve todo)
if (!usuarioEnSedesVisibles($pdo, $usuario_id)) {
    echo "<p class='text-danger'>Este registro no pertenece a su sede de trabajo.</p>";
    exit();
}

$centros = $pdo->prepare("SELECT id, nombre_centro FROM centros_medicos WHERE clinica_id = ? AND estado = 1 ORDER BY nombre_centro");
$centros->execute([$clinica_id]);

$asignados = $pdo->prepare("SELECT centro_id FROM asignacion_centros WHERE usuario_id = ?");
$asignados->execute([$usuario_id]);
$listaAsignados = array_map('intval', $asignados->fetchAll(PDO::FETCH_COLUMN));

$editables = array_map('intval', $_SESSION['sedes'] ?? []);

foreach ($centros as $c) {
    $id = (int) $c['id'];
    $checked = in_array($id, $listaAsignados, true) ? 'checked' : '';
    $editable = esAdmin() || in_array($id, $editables, true);
    $disabled = $editable ? '' : 'disabled';
    $nota = $editable ? '' : " <small class='text-muted'>(sin acceso)</small>";
    $nombre = htmlspecialchars($c['nombre_centro']);
    echo "
    <div class='custom-control custom-checkbox'>
        <input class='custom-control-input' type='checkbox' name='centros[]' id='centro_{$id}' value='{$id}' $checked $disabled>
        <label for='centro_{$id}' class='custom-control-label'>{$nombre}{$nota}</label>
    </div>";
}

/**
 * Permiso requerido para cambiar las sedes de un usuario, según su rol.
 */
function permisoAsignarSede($rolId) {
    if (esRolMedico($rolId)) return 'medicos.administrar';
    if (esRolPaciente($rolId)) return 'pacientes.gestionar';
    return 'usuarios.asignar_sede';
}
