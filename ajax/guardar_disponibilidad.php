<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Validar que la sesión esté activa
if (!isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada']);
    exit;
}

// Recibir y limpiar datos
$clinica_id      = $_SESSION['clinica'];
$usuario_id      = $_POST['usuario_id'] ?? '';
$especialidad_id = $_POST['especialidad_id'] ?? ''; // Ahora guarda el ID numérico correctamente
$centro_id       = $_POST['centro_id'] ?? '';
$consultorio_id  = $_POST['consultorio_id'] ?? '';
$dia_semana      = $_POST['dia_semana'] ?? '';
$hora_inicio     = $_POST['hora_inicio'] ?? '';
$hora_fin        = $_POST['hora_fin'] ?? '';
$duracion_cita   = $_POST['duracion_cita'] ?? 20;

if(empty($usuario_id) || empty($especialidad_id) || empty($centro_id) || empty($consultorio_id) || empty($dia_semana)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos del formulario son obligatorios.']);
    exit;
}

// Validar que la hora de inicio sea menor a la hora de fin
if (strtotime($hora_inicio) >= strtotime($hora_fin)) {
    echo json_encode(['status' => 'error', 'message' => 'La hora de inicio debe ser menor a la hora de fin']);
    exit;
}

try {
    /** * VALIDAR TRASLAPE: Evitar que el consultorio sea ocupado por dos médicos a la vez
     */
    $sql_validar = "SELECT d.hora_inicio, d.hora_fin, u.nombre as medico 
                    FROM disponibilidad_medica d
                    INNER JOIN usuarios_clinicas u ON d.usuario_id = u.id
                    WHERE d.consultorio_id = ? 
                    AND d.dia_semana = ? 
                    AND d.estado = 1
                    AND (? < d.hora_fin AND ? > d.hora_inicio)";
    
    $stmt_val = $pdo->prepare($sql_validar);
    $stmt_val->execute([$consultorio_id, $dia_semana, $hora_inicio, $hora_fin]);
    $conflicto = $stmt_val->fetch(PDO::FETCH_ASSOC);

    if ($conflicto) {
        echo json_encode([
            'status' => 'error', 
            'message' => "Conflicto de horario: El consultorio ya está asignado al Dr(a). {$conflicto['medico']} de {$conflicto['hora_inicio']} a {$conflicto['hora_fin']}"
        ]);
        exit;
    }

    /**
     * VALIDAR MÉDICO: Evitar que el mismo médico trabaje en dos lugares al mismo tiempo
     */
    $sql_medico = "SELECT hora_inicio, hora_fin 
                   FROM disponibilidad_medica 
                   WHERE usuario_id = ? 
                   AND dia_semana = ? 
                   AND estado = 1
                   AND (? < hora_fin AND ? > hora_inicio)";
    
    $stmt_med = $pdo->prepare($sql_medico);
    $stmt_med->execute([$usuario_id, $dia_semana, $hora_inicio, $hora_fin]);
    
    if ($stmt_med->fetch()) {
        echo json_encode([
            'status' => 'error', 
            'message' => "El médico ya tiene otra asignación en este mismo horario."
        ]);
        exit;
    }

    // Inserción limpia en disponibilidad_medica
    $sql_insert = "INSERT INTO disponibilidad_medica 
                   (clinica_id, usuario_id, especialidad_id, centro_id, consultorio_id, dia_semana, hora_inicio, hora_fin, duracion_cita, estado) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt_ins = $pdo->prepare($sql_insert);
    $stmt_ins->execute([
        $clinica_id, 
        $usuario_id, 
        $especialidad_id, 
        $centro_id, 
        $consultorio_id, 
        $dia_semana, 
        $hora_inicio, 
        $hora_fin, 
        $duracion_cita
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Disponibilidad guardada correctamente']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}