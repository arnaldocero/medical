<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// 1. Validar sesión activa
if (!isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada']);
    exit;
}

$clinica_id   = $_SESSION['clinica'];
$cita_id      = $_POST['id'] ?? '';
$nuevo_estado = $_POST['estado'] ?? '';

// 2. Validar parámetros obligatorios
if (empty($cita_id) || empty($nuevo_estado)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos para actualizar el estado']);
    exit;
}

// 3. Validar que el estado pertenezca a tu ENUM de la base de datos
$estados_permitidos = ['Asignada', 'En Sala', 'Atendida', 'Cancelada'];
if (!in_array($nuevo_estado, $estados_permitidos)) { 
    echo json_encode(['status' => 'error', 'message' => 'El estado enviado no es válido']);
    exit;
}

try {
    // 4. Ejecutar la actualización resguardando la clínica actual
    $sql = "UPDATE citas_medicas 
            SET estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP 
            WHERE id = ? AND clinica_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nuevo_estado, $cita_id, $clinica_id]);

    if ($stmt->rowCount() > 0) {
        // Mensaje estético personalizado para SweetAlert
        $mensaje = "La cita ha sido modificada a: " . $nuevo_estado;
        if ($nuevo_estado === 'En Sala')  $mensaje = "El paciente ingresó correctamente a la Sala de Espera.";
        if ($nuevo_estado === 'Cancelada') $mensaje = "La cita se canceló y el cupo ha sido liberado.";
        
        echo json_encode(['status' => 'success', 'message' => $mensaje]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se detectaron cambios o la cita no pertenece a tu clínica.']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error crítico de base de datos: ' . $e->getMessage()]);
}