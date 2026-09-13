<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Validar sesión activa (Usuario y Clínica)
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida o no iniciada.']);
    exit();
}

require_once '../config/db.php';

$clinica_id        = $_SESSION['clinica'];
$usuario_sesion_id = intval($_SESSION['id']); // ID del usuario conectado
$rol_usuario       = intval($_SESSION['rol'] ?? 0); // Convertir ROL a entero (Médico = 2)

$paciente_id    = intval($_POST['paciente_id'] ?? 0);
$medico_id      = intval($_POST['medico_id'] ?? 0); // Médico seleccionado en el formulario
$centro_id      = intval($_POST['centro_id'] ?? 0);
$consultorio_id = intval($_POST['consultorio_id'] ?? 0);
$servicio_id    = intval($_POST['servicio_id'] ?? 0);
$duracion       = intval($_POST['duracion'] ?? 20); 
$valor_cita     = $_POST['valor_cita'] ?? 0;
$motivo         = $_POST['motivo_consulta'] ?? 'En Sala';

if ($paciente_id === 0 || $medico_id === 0 || $centro_id === 0 || $servicio_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Por favor complete todos los campos obligatorios.']);
    exit();
}

try {
    date_default_timezone_set('America/Bogota');
    
    $fecha_cita  = date('Y-m-d');
    $hora_inicio = date('H:i:s');
    $hora_fin    = date('H:i:s', strtotime("+$duracion minutes", strtotime($hora_inicio)));

    $sqlInsert = "INSERT INTO citas_medicas 
                    (clinica_id, paciente_id, medico_id, centro_id, consultorio_id, servicio_id, fecha_cita, hora_inicio, hora_fin, motivo_consulta, valor_cita, estado) 
                  VALUES 
                    (:clinica_id, :paciente_id, :medico_id, :centro_id, :consultorio_id, :servicio_id, :fecha_cita, :hora_inicio, :hora_fin, :motivo, :valor_cita, 'En Atencion')";
    
    $stmtInsert = $pdo->prepare($sqlInsert);
    $res = $stmtInsert->execute([
        ':clinica_id'     => $clinica_id,
        ':paciente_id'    => $paciente_id,
        ':medico_id'      => $medico_id,
        ':centro_id'      => $centro_id,
        ':consultorio_id' => $consultorio_id,
        ':servicio_id'    => $servicio_id,
        ':fecha_cita'     => $fecha_cita,
        ':hora_inicio'    => $hora_inicio,
        ':hora_fin'       => $hora_fin,
        ':motivo'         => $motivo,
        ':valor_cita'     => $valor_cita
    ]);

    $nueva_cita_id = $pdo->lastInsertId();

    echo json_encode([
        'status'            => 'success',
        'message'           => 'Atención Express registrada correctamente.',
        'cita_id'           => $nueva_cita_id,
        'rol'               => $rol_usuario,       // Rol como entero (ej. 2)
        'medico_id'         => $medico_id,         // ID del médico asignado a la cita
        'usuario_sesion_id' => $usuario_sesion_id  // ID del usuario logueado actualmente
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error en base de datos: ' . $e->getMessage()]);
}