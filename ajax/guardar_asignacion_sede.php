<?php
session_start();
require_once '../config/db.php';
// Importamos tu motor de seguridad
require_once '../config/security.php'; 

// Validamos el permiso de escritura en la asignación de sedes
requerirPermisoAJAX('usuarios.asignar_sede');

$usuario_id = $_POST['idUsuarioAsignar'];
$clinica_id = $_SESSION['clinica'];
$centros = $_POST['centros'] ?? []; // Array de IDs de centros[cite: 28]

try {
    $pdo->beginTransaction();

    // 1. Borrar asignaciones anteriores para este usuario[cite: 28]
    $del = $pdo->prepare("DELETE FROM asignacion_centros WHERE usuario_id = ? AND clinica_id = ?");
    $del->execute([$usuario_id, $clinica_id]);

    // 2. Insertar las nuevas[cite: 28]
    if (!empty($centros)) {
        $ins = $pdo->prepare("INSERT INTO asignacion_centros (clinica_id, usuario_id, centro_id) VALUES (?, ?, ?)");
        foreach ($centros as $centro_id) {
            $ins->execute([$clinica_id, $usuario_id, $centro_id]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Sedes actualizadas correctamente']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}