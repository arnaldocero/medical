<?php
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(["status" => "error", "message" => "Acceso denegado."]);
    exit();
}

require_once '../config/db.php'; 

$action = $_POST['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']);

if (ob_get_length()) ob_clean();

try {
    // --- ACCIÓN: LISTAR MANUALES ---
    if ($action === 'listar') {
        $query = "SELECT id, nombre_manual, tipo_base, año_vigencia, estado 
                  FROM manuales 
                  WHERE clinica_id = ? 
                  ORDER BY año_vigencia DESC, nombre_manual ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$clinica_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- ACCIÓN: REGISTRAR MANUAL ---
    if ($action === 'registrar') {
        $nombre_manual = trim($_POST['nombre_manual'] ?? '');
        $tipo_base = trim($_POST['tipo_base'] ?? 'PROPIO');
        $año_vigencia = intval($_POST['año_vigencia'] ?? date('Y'));

        if (empty($nombre_manual) || $año_vigencia === 0) {
            echo json_encode(["status" => "warning", "message" => "El nombre y el año son obligatorios."]);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO manuales (clinica_id, nombre_manual, tipo_base, año_vigencia, estado) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$clinica_id, $nombre_manual, $tipo_base, $año_vigencia]);

        echo json_encode(["status" => "success", "message" => "Manual tarifario creado con éxito."]);
        exit();
    }

    // --- ACCIÓN: DETALLE DE TARIFAS (LISTAR CUPS Y SU PRECIO EN ESTE MANUAL) ---
    if ($action === 'listar_tarifas') {
        $manual_id = intval($_POST['manual_id'] ?? 0);

        // Traemos TODOS los servicios de la clínica, cruzados con el valor configurado en este manual (si existe)
        $query = "SELECT s.id AS servicio_id, s.codigo_cups, s.nombre_servicio, COALESCE(t.valor, 0.00) AS valor
                  FROM servicios s
                  LEFT JOIN manual_tarifas t ON t.servicio_id = s.id AND t.manual_id = ?
                  WHERE s.clinica_id = ? AND s.estado = 1
                  ORDER BY s.codigo_cups ASC";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([$manual_id, $clinica_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- ACCIÓN: GUARDAR/ACTUALIZAR TARIFA INDIVIDUAL ---
    if ($action === 'guardar_tarifa') {
        $manual_id = intval($_POST['manual_id'] ?? 0);
        $servicio_id = intval($_POST['servicio_id'] ?? 0);
        $valor = floatval($_POST['valor'] ?? 0);

        // Verificamos propiedad del manual por seguridad
        $stmtCheck = $pdo->prepare("SELECT id FROM manuales WHERE id = ? AND clinica_id = ?");
        $stmtCheck->execute([$manual_id, $clinica_id]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(["status" => "error", "message" => "Operación no permitida."]);
            exit();
        }

        // Usamos la sentencia ON DUPLICATE KEY UPDATE de MySQL gracias al índice único compuesto
        $query = "INSERT INTO manual_tarifas (manual_id, servicio_id, valor) 
                  VALUES (?, ?, ?) 
                  ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$manual_id, $servicio_id, $valor]);

        echo json_encode(["status" => "success", "message" => "Tarifa actualizada."]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    exit();
}