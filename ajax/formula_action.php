<?php
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada o no válida.']);
    exit();
}

require_once '../config/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']);
$usuario_sesion_id = intval($_SESSION['id']); // ID de la tabla usuarios_clinicas

switch ($action) {
    case 'buscar_medicamentos':
        $term = trim($_GET['q'] ?? '');
        try {
            $stmt = $pdo->prepare("
                SELECT id, nombre_generico, nombre_comercial, presentacion, stock_actual 
                FROM medicamentos 
                WHERE clinica_id = ? AND estado = 'activo' 
                AND (nombre_generico LIKE ? OR nombre_comercial LIKE ?) 
                LIMIT 10
            ");
            $stmt->execute([$clinica_id, "%$term%", "%$term%"]);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $items = [];
            foreach ($resultados as $row) {
                $comercial = $row['nombre_comercial'] ? " [{$row['nombre_comercial']}]" : "";
                $items[] = [
                    'id' => $row['id'],
                    'text' => "{$row['nombre_generico']}{$comercial} - {$row['presentacion']} (Stock: {$row['stock_actual']})"
                ];
            }
            echo json_encode(['results' => $items]);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'guardar_formula':
        $paciente_datos_id = intval($_POST['paciente_id'] ?? 0); // ID de la tabla pacientes_datos
        $diagnostico = trim($_POST['diagnostico_cie10'] ?? '') ?: null;
        $observaciones = trim($_POST['observaciones'] ?? '') ?: null;
        $medicamentos = $_POST['med_items'] ?? [];

        if ($paciente_datos_id === 0 || empty($medicamentos)) {
            echo json_encode(['status' => 'warning', 'message' => 'Información incompleta. Seleccione paciente y agregue fármacos.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            // AJUSTE CLAVE: Obtener el ID real del médico en la tabla datos_medicos
            $stmtMed = $pdo->prepare("SELECT id FROM datos_medicos WHERE usuario_id = ? LIMIT 1");
            $stmtMed->execute([$usuario_sesion_id]);
            $medico = $stmtMed->fetch(PDO::FETCH_ASSOC);

            if (!$medico) {
                throw new Exception("El usuario actual no está configurado como personal médico autorizado.");
            }

            $medico_datos_id = intval($medico['id']); // El ID correcto para la FK

            // 1. Insertar Cabecera de la Fórmula
            $queryCabecera = "INSERT INTO formulas_medicas (clinica_id, paciente_id, medico_id, diagnostico_cie10, observaciones, estado) 
                              VALUES (?, ?, ?, ?, ?, 'PENDIENTE')";
            $stmtCabecera = $pdo->prepare($queryCabecera);
            $stmtCabecera->execute([$clinica_id, $paciente_datos_id, $medico_datos_id, $diagnostico, $observaciones]);
            $formula_id = $pdo->lastInsertId();

            // 2. Insertar Detalle de la Fórmula
            $queryDetalle = "INSERT INTO formula_detalles (formula_id, medicamento_id, cantidad_solicitada, dosificacion) 
                             VALUES (?, ?, ?, ?)";
            $stmtDetalle = $pdo->prepare($queryDetalle);

            foreach ($medicamentos as $item) {
                $med_id = intval($item['medicamento_id']);
                $cant = intval($item['cantidad']);
                $dosis = trim($item['dosificacion']);

                if ($med_id > 0 && $cant > 0 && !empty($dosis)) {
                    $stmtDetalle->execute([$formula_id, $med_id, $cant, $dosis]);
                } else {
                    throw new Exception("Hay renglones con información incompleta en la prescripción.");
                }
            }

            $pdo->commit();
            echo json_encode([
                'status' => 'success', 
                'message' => 'Fórmula médica generada exitosamente por el profesional.',
                'formula_id' => $formula_id
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        // === NUEVO CASO: BUSCADOR DINÁMICO DE DIAGNÓSTICOS CIE-10 ===
    case 'buscar_diagnosticos':
        $term = trim($_GET['q'] ?? '');
        try {
            // Buscamos coincidencia tanto en el código CIE como en la descripción larga
            $stmt = $pdo->prepare("
                SELECT id, codigo_cie, descripcion 
                FROM diagnosticos 
                WHERE estado = 1 -- O el valor con el que manejes los activos (ej: 1)
                AND (codigo_cie LIKE ? OR descripcion LIKE ?) 
                LIMIT 15
            ");
            $stmt->execute(["%$term%", "%$term%"]);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $items = [];
            foreach ($resultados as $row) {
                $items[] = [
                    'id' => $row['codigo_cie'], // Guardamos el código estándar CIE-10 en la fórmula
                    'text' => "{$row['codigo_cie']} - {$row['descripcion']}"
                ];
            }
            echo json_encode(['results' => $items]);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no permitida.']);
        break;
}