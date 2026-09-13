<?php
session_start();
require_once '../config/db.php';
// Importamos tu motor de seguridad
require_once '../config/security.php'; 

// Validamos el permiso específico para eliminar de manera segura
requerirPermisoAJAX('usuarios.eliminar');

if (isset($_POST['id'])) {
    // Usamos borrado lógico (estado = 0) para no romper la integridad referencial[cite: 27]
    $stmt = $pdo->prepare("UPDATE usuarios_clinicas SET estado = 0 WHERE id = ?");
    
    if ($stmt->execute([$_POST['id']])) {
        echo json_encode(['status' => 'success', 'message' => 'Usuario desactivado correctamente']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar']);
    }
}