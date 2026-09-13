<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida o expirada.']);
    exit;
}

$accion = $_REQUEST['accion'] ?? ''; // Usamos REQUEST para admitir GET y POST

try {
    // ACCIÓN 1: TRAER LOS PACIENTES EN TIEMPO REAL
    if ($accion === 'listar_pacientes') {
        $medico_id = $_SESSION['id'];
        $hoy = date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT cm.id, cm.hora_inicio, p.nombre AS paciente, cm.estado, con.nombre_consultorio
            FROM citas_medicas cm
            INNER JOIN usuarios_clinicas p ON cm.paciente_id = p.id
            INNER JOIN consultorios con ON cm.consultorio_id = con.id
            WHERE cm.medico_id = ? AND cm.fecha_cita = ? AND cm.estado IN ('En Sala', 'En Consulta')
            ORDER BY cm.hora_inicio ASC
        ");
        $stmt->execute([$medico_id, $hoy]);
        $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar la hora para un formato más amigable antes de enviar al JS
        foreach ($pacientes as &$p) {
            $p['hora_formato'] = date('g:i A', strtotime($p['hora_inicio']));
        }

        echo json_encode(['status' => 'success', 'data' => $pacientes]);
        exit;
    }

    // ACCIÓN 2: CAMBIAR ESTADO AL LLAMAR PACIENTE
    if ($accion === 'llamar_paciente') {
        $cita_id = $_POST['cita_id'] ?? '';
        if (empty($cita_id)) {
            echo json_encode(['status' => 'error', 'message' => 'ID de cita no proporcionado.']);
            exit;
        }

        $sql = "UPDATE citas_medicas SET estado = 'En Consulta' WHERE id = ? AND medico_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cita_id, $_SESSION['id']]);

        echo json_encode(['status' => 'success', 'message' => 'Paciente llamado exitosamente.']);
        exit;
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida.']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}