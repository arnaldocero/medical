<?php
session_start();
header('Content-Type: application/json'); // Fuerza el encabezado JSON
require_once '../config/db.php';

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit();
}

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $clinica_id = $_SESSION['clinica']; // Seguridad: solo borrar centros del dueño de la sesión

    try {
        // Cambiamos el estado a 0 (Inactivo)
        $sql = "UPDATE centros_medicos SET estado = 0 WHERE id = ? AND clinica_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $clinica_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Centro médico desactivado correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se encontró el registro o no tienes permiso']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID no proporcionado']);
}

ob_end_flush(); // Envía el contenido