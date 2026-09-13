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
    // --- ACCIÓN: LISTAR CONTRATOS VINCULADOS ---
    if ($action === 'listar') {
        $query = "SELECT c.id, c.numero_contrato, e.nombre AS nombre_eps, m.nombre_manual, 
                         c.fecha_inicio, c.fecha_fin, c.estado 
                  FROM contratos c
                  INNER JOIN eps e ON c.eps_id = e.id
                  INNER JOIN manuales m ON c.manual_id = m.id
                  WHERE c.clinica_id = ? 
                  ORDER BY c.estado DESC, c.fecha_fin ASC";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([$clinica_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- ACCIÓN: REGISTRAR CONTRATO ---
    if ($action === 'registrar') {
        $eps_id = intval($_POST['eps_id'] ?? 0);
        $manual_id = intval($_POST['manual_id'] ?? 0);
        $numero_contrato = trim($_POST['numero_contrato'] ?? '');
        $objeto_contrato = trim($_POST['objeto_contrato'] ?? '');
        $fecha_inicio = $_POST['fecha_inicio'] ?? '';
        $fecha_fin = $_POST['fecha_fin'] ?? '';
        $porcentaje = floatval($_POST['porcentaje_cobertura'] ?? 100);

        if ($eps_id === 0 || $manual_id === 0 || empty($numero_contrato) || empty($fecha_inicio) || empty($fecha_fin)) {
            echo json_encode(["status" => "warning", "message" => "Por favor, completa todos los campos mandatorios."]);
            exit();
        }

        // Validación lógica de fechas
        if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
            echo json_encode(["status" => "warning", "message" => "La fecha de inicio no puede ser posterior a la fecha de finalización."]);
            exit();
        }

        // Inserción segura parametrizada
        $query = "INSERT INTO contratos (clinica_id, eps_id, manual_id, numero_contrato, objeto_contrato, fecha_inicio, fecha_fin, porcentaje_cobertura, estado) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$clinica_id, $eps_id, $manual_id, $numero_contrato, $objeto_contrato, $fecha_inicio, $fecha_fin, $porcentaje]);

        echo json_encode(["status" => "success", "message" => "Contrato legalizado e integrado al sistema con éxito."]);
        exit();
    }

    // --- ACCIÓN: CAMBIAR ESTADO ---
    if ($action === 'estado') {
        $id = intval($_POST['contrato_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);

        $stmt = $pdo->prepare("UPDATE contratos SET estado = ? WHERE id = ? AND clinica_id = ?");
        $stmt->execute([$nuevo_estado, $id, $clinica_id]);

        echo json_encode(["status" => "success", "message" => "El estado del contrato ha sido actualizado."]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error interno en la operación: " . $e->getMessage()]);
    exit();
}