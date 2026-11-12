<?php
/**
 * Desbloquea una cuenta bloqueada por intentos fallidos de inicio de sesión, sin esperar los 30 minutos.
 * Lo usan Gestión Usuarios, Médicos y Pacientes; el permiso depende del tipo de usuario.
 */
session_start();
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../config/roles.php';
require_once '../config/login_seguridad.php';
header('Content-Type: application/json');

requerirSesionAJAX();

$usuario_id = intval($_POST['usuario_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, nombre, rol_id, bloqueado_hasta, intentos_fallidos FROM usuarios_clinicas WHERE id = ? AND clinica_id = ?");
$stmt->execute([$usuario_id, $_SESSION['clinica']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado.']);
    exit();
}

// Permiso según el tipo de usuario
if (esRolMedico($usuario['rol_id'])) {
    requerirPermisoAJAX('medicos.administrar');
} elseif (esRolPaciente($usuario['rol_id'])) {
    requerirPermisoAJAX('pacientes.gestionar');
} else {
    requerirPermisoAJAX(permisosUsuarios('editar'));
}

if (!puedeTocarRol($usuario['rol_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Solo el Administrador puede desbloquear a un Administrador.']);
    exit();
}
validarUsuarioEnSedeAJAX($pdo, $usuario_id);

if (!estadoBloqueoCuenta($pdo, $usuario_id) && intval($usuario['intentos_fallidos']) === 0) {
    echo json_encode(['status' => 'info', 'message' => 'La cuenta de ' . $usuario['nombre'] . ' no está bloqueada.']);
    exit();
}

desbloquearCuenta($pdo, $usuario_id, $_SESSION['nombre'] . ' (#' . $_SESSION['id'] . ')');
auditar($pdo, 'usuario', 'desbloquear', $usuario_id, "Desbloqueó la cuenta de {$usuario['nombre']}", ['bloqueado_hasta' => $usuario['bloqueado_hasta'], 'intentos_fallidos' => $usuario['intentos_fallidos']], ['bloqueado_hasta' => null, 'intentos_fallidos' => 0]);
echo json_encode(['status' => 'success', 'message' => 'Cuenta de ' . $usuario['nombre'] . ' desbloqueada. Ya puede iniciar sesión.']);
