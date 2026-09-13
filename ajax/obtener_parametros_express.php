<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no activa']);
    exit();
}

require_once '../config/db.php';

date_default_timezone_set('America/Bogota');

$clinica_id        = $_SESSION['clinica'];
$usuario_sesion_id = intval($_SESSION['id']);
$rol               = strtolower($_SESSION['rol'] ?? 'medico'); 
$action            = $_GET['action'] ?? 'inicial';

try {
    switch ($action) {
        
        // 1. Carga Inicial de la pantalla
        case 'inicial':
            // Centros
            $stmtCentros = $pdo->prepare("SELECT id, nombre_centro AS nombre FROM centros_medicos WHERE clinica_id = ? AND estado = 1 ORDER BY nombre_centro ASC");
            $stmtCentros->execute([$clinica_id]);
            $centros = $stmtCentros->fetchAll(PDO::FETCH_ASSOC);

            // Servicios
            $sqlServicios = "SELECT s.id, s.codigo_cups, s.nombre_servicio, mt.valor
                             FROM servicios s
                             INNER JOIN manual_tarifas mt ON s.id = mt.servicio_id
                             INNER JOIN manuales m ON mt.manual_id = m.id
                             WHERE s.clinica_id = :clinica_id AND s.estado = 1 AND m.estado = 1
                             ORDER BY s.nombre_servicio ASC";
            $stmtServ = $pdo->prepare($sqlServicios);
            $stmtServ->execute([':clinica_id' => $clinica_id]);
            $servicios = $stmtServ->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status'            => 'success',
                'rol'               => $rol,
                'es_medico'         => ($rol === 'medico'),
                'usuario_sesion_id' => $usuario_sesion_id,
                'centros'           => $centros,
                'servicios'         => $servicios
            ]);
            break;

        // 2. Obtener Médicos asociados a un Centro/Sede
        case 'medicos_por_centro':
            $centro_id = intval($_GET['centro_id'] ?? 0);
            
            // Buscar médicos con agenda/disponibilidad o asignados a ese centro
            $sqlMedicos = "SELECT DISTINCT u.id, u.nombre 
                           FROM usuarios_clinicas u 
                           INNER JOIN disponibilidad_medica dm ON u.id = dm.usuario_id 
                           WHERE dm.centro_id = ? AND dm.clinica_id = ? AND dm.estado = 1
                           ORDER BY u.nombre ASC";
            $stmtMedicos = $pdo->prepare($sqlMedicos);
            $stmtMedicos->execute([$centro_id, $clinica_id]);
            $medicos = $stmtMedicos->fetchAll(PDO::FETCH_ASSOC);

            // Fallback: Si no hay médicos con disponibilidad activa, traer todos los médicos de la clínica
            if (empty($medicos)) {
                $stmtMedicosAlt = $pdo->prepare("SELECT u.id, u.nombre FROM usuarios_clinicas u INNER JOIN datos_medicos dm ON u.id = dm.usuario_id WHERE u.clinica_id = ? AND u.estado = 1 ORDER BY u.nombre ASC");
                $stmtMedicosAlt->execute([$clinica_id]);
                $medicos = $stmtMedicosAlt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['status' => 'success', 'medicos' => $medicos]);
            break;

        // 3. Obtener Consultorios asignados y verificar agenda del día de hoy
        case 'consultorios_por_medico':
            $centro_id = intval($_GET['centro_id'] ?? 0);
            $medico_id = intval($_GET['medico_id'] ?? 0);

            // Mapear el día actual de la semana en español
            $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
            $num_dia = (int)date('N');
            $texto_dia = $dias[$num_dia];

            // Consultar los consultorios asociados en disponibilidad_medica para ese médico y centro
            $sqlCons = "SELECT DISTINCT c.id, c.nombre_consultorio 
                        FROM consultorios c 
                        INNER JOIN disponibilidad_medica dm ON c.id = dm.consultorio_id 
                        WHERE dm.usuario_id = ? AND dm.centro_id = ? AND dm.estado = 1";
            $stmtCons = $pdo->prepare($sqlCons);
            $stmtCons->execute([$medico_id, $centro_id]);
            $consultorios = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

            // Si no tiene consultorios asignados en la disponibilidad, traer todos los consultorios del centro
            if (empty($consultorios)) {
                $stmtConsAlt = $pdo->prepare("SELECT id, nombre_consultorio FROM consultorios WHERE centro_id = ? AND clinica_id = ? AND estado = 1");
                $stmtConsAlt->execute([$centro_id, $clinica_id]);
                $consultorios = $stmtConsAlt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Verificar si tiene agenda configurada HOY
            $sqlAgendaHoy = "SELECT COUNT(*) FROM disponibilidad_medica 
                             WHERE usuario_id = ? AND centro_id = ? 
                               AND (dia_semana = ? OR dia_semana = ?) AND estado = 1";
            $stmtAgenda = $pdo->prepare($sqlAgendaHoy);
            $stmtAgenda->execute([$medico_id, $centro_id, $num_dia, $texto_dia]);
            $tiene_agenda_hoy = ($stmtAgenda->fetchColumn() > 0);

            echo json_encode([
                'status'           => 'success',
                'consultorios'     => $consultorios,
                'tiene_agenda_hoy' => $tiene_agenda_hoy,
                'dia_actual'       => $texto_dia
            ]);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}