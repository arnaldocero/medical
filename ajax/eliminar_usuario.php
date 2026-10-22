<?php
session_start();
require_once '../config/db.php';
// Importamos tu motor de seguridad
require_once '../config/security.php';
require_once '../config/roles.php';
header('Content-Type: application/json');

// Validamos el permiso específico para eliminar de manera segura
requerirPermisoAJAX('usuarios.eliminar');

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    if ($id === intval($_SESSION['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'No puede desactivar su propio usuario.']);
        exit();
    }

    // Usamos borrado lógico (estado = 0) para no romper la integridad referencial.
    // Solo personal del sistema de la misma clínica (médicos y pacientes se gestionan en sus módulos)
    validarUsuarioEnSedeAJAX($pdo, $id);

    $rolesExcluidos = listaSqlRoles(array_merge(ROLES_MEDICOS, ROLES_PACIENTES));
    $stmt = $pdo->prepare("UPDATE usuarios_clinicas SET estado = 0 WHERE id = ? AND clinica_id = ? AND rol_id NOT IN ($rolesExcluidos)");

    if ($stmt->execute([$id, $_SESSION['clinica']]) && $stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Usuario desactivado correctamente']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo desactivar el usuario.']);
    }
}
