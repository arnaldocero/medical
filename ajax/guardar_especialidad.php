<?php
session_start();
require_once '../config/db.php';

$id = $_POST['idEspecialidad'] ?? null;
$nombre = $_POST['nombre'];
$descripcion = $_POST['descripcion'];
$clinica_id = $_SESSION['clinica'];

try {
    if (empty($id)) {
        // Insertar nueva
        $sql = "INSERT INTO especialidades (clinica_id, nombre, descripcion) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clinica_id, $nombre, $descripcion]);
        $message = "Especialidad creada correctamente";
    } else {
        // Actualizar existente
        $sql = "UPDATE especialidades SET nombre = ?, descripcion = ? WHERE id = ? AND clinica_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $descripcion, $id, $clinica_id]);
        $message = "Especialidad actualizada correctamente";
    }

    echo json_encode(['status' => 'success', 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}