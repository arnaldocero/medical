<?php
session_start();
// Seguridad: sesión activa + permiso del módulo
require_once __DIR__ . '/../config/security.php';
requerirPermisoAJAX('sala.espera');
require_once '../config/db.php';

header('Content-Type: application/json');

// Solo pacientes de la clínica del usuario en sesión
$clinica_id = $_SESSION['clinica'];
$hoy = date('Y-m-d');

try {
    // Traemos tanto 'En Sala' como 'En Consulta'
    $sql = "SELECT cm.id, cm.hora_inicio, p.nombre AS paciente, cm.estado, con.nombre_consultorio, m.nombre AS medico
            FROM citas_medicas cm
            INNER JOIN usuarios_clinicas p ON cm.paciente_id = p.id
            INNER JOIN usuarios_clinicas m ON cm.medico_id = m.id
            INNER JOIN consultorios con ON cm.consultorio_id = con.id
            WHERE cm.clinica_id = ? AND " . sqlEnSedes('cm.centro_id') . " AND cm.fecha_cita = ? AND cm.estado IN ('En Sala', 'En Consulta')
            ORDER BY CASE WHEN cm.estado = 'En Consulta' THEN 1 ELSE 2 END, cm.hora_inicio ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clinica_id, $hoy]);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear datos
    foreach ($pacientes as &$p) {
        $p['hora_formato'] = date('g:i A', strtotime($p['hora_inicio']));
        
        // Cortar el nombre para que no desfigure la pantalla gigante
        $partes = explode(' ', trim($p['paciente']));
        $p['paciente_corto'] = (count($partes) >= 3) ? $partes[0] . ' ' . $partes[2] : $partes[0] . ' ' . ($partes[1] ?? '');
    }

    echo json_encode(['status' => 'success', 'data' => $pacientes]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
}