<?php
session_start();
require_once '../config/db.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no activa']);
    exit;
}

$clinica_id = $_SESSION['clinica'];
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'obtener_consultorios':
        $centro_id = $_GET['centro_id'] ?? '';
        try {
            $stmt = $pdo->prepare("SELECT id, nombre_consultorio FROM consultorios WHERE centro_id = ? AND clinica_id = ? AND estado = 1");
            $stmt->execute([$centro_id, $clinica_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

        case 'obtener_horas_disponibles':
            // Asegurar la zona horaria correcta para Colombia / América Latina
            date_default_timezone_set('America/Bogota');
    
            $medico_id = $_GET['medico_id'] ?? '';
            $centro_id = $_GET['centro_id'] ?? '';
            $fecha     = $_GET['fecha'] ?? '';
    
            if(empty($medico_id) || empty($centro_id) || empty($fecha)) {
                echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros de consulta']);
                exit;
            }
    
            // VALIDACIÓN DE FECHA PASADA: Comparación estricta de formato de fecha Y-m-d
            $hoy = date('Y-m-d');
            if ($fecha < $hoy) {
                echo json_encode(['status' => 'no_disponible', 'message' => 'No puedes agendar citas en fechas del pasado.']);
                exit;
            }
    
            try {
                // 1. SOLUCIÓN AL CAMBIO DE DÍA: Forzar lectura de cadena de fecha pura (Evita desfases de zonas horarias)
                $dateObj = new DateTime($fecha);
                $num_dia = (int)$dateObj->format('N'); // 1 = Lunes, 2 = Martes...
    
                // 2. MAPEO DE TRADUCCIÓN: Primera letra Mayúscula y las demás minúsculas
                $dias_texto = [
                    1 => 'Lunes',
                    2 => 'Martes',
                    3 => 'Miercoles',
                    4 => 'Jueves',
                    5 => 'Viernes',
                    6 => 'Sabado',
                    7 => 'Domingo'
                ];
                $texto_dia = $dias_texto[$num_dia];
    
                // 3. DIAGNÓSTICO PREVIO: Validar si la agenda existe sin filtros restrictivos
                $sqlCheckReal = "SELECT centro_id, estado, dia_semana FROM disponibilidad_medica WHERE usuario_id = ? AND (dia_semana = ? OR dia_semana = ?)";
                $stmtCheckReal = $pdo->prepare($sqlCheckReal);
                $stmtCheckReal->execute([$medico_id, $num_dia, $texto_dia]);
                $agendas_existentes = $stmtCheckReal->fetchAll(PDO::FETCH_ASSOC);
    
                if (empty($agendas_existentes)) {
                    echo json_encode([
                        'status' => 'no_disponible', 
                        'message' => 'El médico no tiene configurada ninguna agenda para el día ' . $texto_dia . ' en la base de datos.'
                    ]);
                    exit;
                }
    
                // Rastrear variables de bloqueo
                $encontrado_en_otra_sede = false;
                $encontrado_desactivado = false;
    
                foreach ($agendas_existentes as $agenda) {
                    if ((int)$agenda['centro_id'] !== (int)$centro_id) {
                        $encontrado_en_otra_sede = true;
                    }
                    if ((int)$agenda['estado'] !== 1) {
                        $encontrado_desactivado = true;
                    }
                }
    
                // 4. CONSULTA CON FILTROS DE SEGURIDAD ACTIVOS (Obtiene todas las agendas del día)
                $sqlDisp = "SELECT duracion_cita, hora_inicio, hora_fin 
                            FROM disponibilidad_medica 
                            WHERE usuario_id = ? 
                              AND centro_id = ? 
                              AND (dia_semana = ? OR dia_semana = ?) 
                              AND estado = 1 
                            ORDER BY hora_inicio ASC";
                
                $stmtDisp = $pdo->prepare($sqlDisp);
                $stmtDisp->execute([$medico_id, $centro_id, $num_dia, $texto_dia]);
                $agendas = $stmtDisp->fetchAll(PDO::FETCH_ASSOC);

                if (empty($agendas)) {
                    if ($encontrado_desactivado) {
                        $msg = 'La agenda para el día ' . $texto_dia . ' existe, pero se encuentra INACTIVA (estado = 0).';
                    } elseif ($encontrado_en_otra_sede) {
                        $msg = 'El médico tiene agenda el ' . $texto_dia . ', pero en una SEDE DIFERENTE a la seleccionada.';
                    } else {
                        $msg = 'El médico especialista no tiene agenda parametrizada para el día ' . $texto_dia . ' en esta sede.';
                    }

                    echo json_encode(['status' => 'no_disponible', 'message' => $msg]);
                    exit;
                }

                // Traer citas ya existentes para cruzarlas y quitarlas de la lista de opciones
                $stmtCitas = $pdo->prepare("SELECT hora_inicio, hora_fin FROM citas_medicas WHERE medico_id = ? AND fecha_cita = ? AND estado != 'Cancelada'");
                $stmtCitas->execute([$medico_id, $fecha]);
                $citas_ocupadas = $stmtCitas->fetchAll(PDO::FETCH_ASSOC);

                $horas_disponibles = [];
                $duracion_minutos = (int)$agendas[0]['duracion_cita']; // Mantener valor para la respuesta JSON
                $ahora_timestamp = strtotime(date('H:i:s'));

                // Recorrer cada una de las agendas activas que tenga el médico para el día
                foreach ($agendas as $disp) {
                    $hora_inicio_medico = strtotime($disp['hora_inicio']);
                    $hora_fin_medico    = strtotime($disp['hora_fin']);
                    $duracion_bloque    = (int)$disp['duracion_cita'];
                    $tiempo_actual      = $hora_inicio_medico;

                    while ($tiempo_actual + ($duracion_bloque * 60) <= $hora_fin_medico) {
                        $bloque_inicio = date('H:i:s', $tiempo_actual);
                        $bloque_fin    = date('H:i:s', $tiempo_actual + ($duracion_bloque * 60));

                        // Si la consulta es para el día de HOY, descartar bloques pasados
                        if ($fecha === $hoy && strtotime($bloque_inicio) <= $ahora_timestamp) {
                            $tiempo_actual += $duracion_bloque * 60;
                            continue;
                        }

                        // Evaluar si este bloque interseca con alguna cita ya reservada
                        $cruzado = false;
                        foreach ($citas_ocupadas as $cita) {
                            if ($bloque_inicio < $cita['hora_fin'] && $bloque_fin > $cita['hora_inicio']) {
                                $cruzado = true;
                                break;
                            }
                        }

                        if (!$cruzado) {
                            $horas_disponibles[] = [
                                'valor' => date('H:i', $tiempo_actual),
                                'formato' => date('h:i A', $tiempo_actual)
                            ];
                        }

                        $tiempo_actual += $duracion_bloque * 60;
                    }
                }
    
                if (empty($horas_disponibles)) {
                    echo json_encode(['status' => 'no_disponible', 'message' => 'El médico no tiene más bloques de horario disponibles para lo que resta de este día.']);
                    exit;
                }
    
                // Buscar Servicios / CUPS activos con sus tarifas asociadas
                $sqlServicios = "SELECT s.id, s.codigo_cups, s.nombre_servicio, mt.valor
                                 FROM servicios s
                                 INNER JOIN manual_tarifas mt ON s.id = mt.servicio_id
                                 INNER JOIN manuales m ON mt.manual_id = m.id
                                 WHERE s.clinica_id = :clinica_id AND s.estado = 1 AND m.estado = 1";
                $stmtServ = $pdo->prepare($sqlServicios);
                $stmtServ->execute([':clinica_id' => $clinica_id]);
                $servicios = $stmtServ->fetchAll(PDO::FETCH_ASSOC);
    
                echo json_encode([
                    'status' => 'success',
                    'duracion' => $duracion_minutos,
                    'horas' => $horas_disponibles,
                    'servicios' => $servicios
                ]);
    
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;

    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        $paciente_id    = $_POST['paciente_id'] ?? '';
        $medico_id      = $_POST['medico_id'] ?? '';
        $centro_id      = $_POST['centro_id'] ?? '';
        $consultorio_id = $_POST['consultorio_id'] ?? '';
        $servicio_id    = $_POST['servicio_id'] ?? '';
        $fecha_cita     = $_POST['fecha_cita'] ?? '';
        $hora_inicio    = $_POST['hora_inicio'] ?? '';
        $duracion       = $_POST['duracion_calculada'] ?? 20; 
        $valor_cita     = $_POST['valor_cita'] ?? 0;
        $motivo         = $_POST['motivo_consulta'] ?? '';

        $hora_inicio = date('H:i:s', strtotime($hora_inicio));
        $hora_fin = date('H:i:s', strtotime("+$duracion minutes", strtotime($hora_inicio)));

        try {
            $checkSql = "SELECT COUNT(*) FROM citas_medicas 
                         WHERE medico_id = :medico_id AND fecha_cita = :fecha_cita AND estado != 'Cancelada'
                           AND (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)";
            $stmtCheck = $pdo->prepare($checkSql);
            $stmtCheck->execute([
                ':medico_id'   => (int)$medico_id,
                ':fecha_cita'  => $fecha_cita,
                ':hora_inicio' => $hora_inicio,
                ':hora_fin'    => $hora_fin
            ]);

            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado acaba de ser ocupado. Por favor elija otra hora.']);
                exit;
            }

            $sqlInsert = "INSERT INTO citas_medicas (clinica_id, paciente_id, medico_id, centro_id, consultorio_id, servicio_id, fecha_cita, hora_inicio, hora_fin, motivo_consulta, valor_cita) 
                          VALUES (:clinica_id, :paciente_id, :medico_id, :centro_id, :consultorio_id, :servicio_id, :fecha_cita, :hora_inicio, :hora_fin, :motivo, :valor_cita)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $res = $stmtInsert->execute([
                ':clinica_id'     => $clinica_id,
                ':paciente_id'    => (int)$paciente_id,
                ':medico_id'      => (int)$medico_id,
                ':centro_id'      => (int)$centro_id,
                ':consultorio_id' => (int)$consultorio_id,
                ':servicio_id'    => (int)$servicio_id,
                ':fecha_cita'     => $fecha_cita,
                ':hora_inicio'    => $hora_inicio,
                ':hora_fin'       => $hora_fin,
                ':motivo'         => $motivo,
                ':valor_cita'     => $valor_cita
            ]);

            if ($res) {
                echo json_encode(['status' => 'success', 'message' => '¡Excelente! Cita agendada correctamente.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la cita médica.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
}