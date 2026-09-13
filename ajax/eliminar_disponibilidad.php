<?php
session_start();
require_once '../config/db.php';

$id = $_POST['id'];

try {
    // Usamos borrado lógico o físico. En agendas suele ser mejor el físico si no hay citas ligadas,
    // pero aquí usaremos físico para mantener limpia la configuración.
    $stmt = $pdo->prepare("DELETE FROM disponibilidad_medica WHERE id = ? AND clinica_id = ?");
    $stmt->execute([$id, $_SESSION['clinica']]);
    
    echo json_encode(['status' => 'success', 'message' => 'Horario eliminado']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}