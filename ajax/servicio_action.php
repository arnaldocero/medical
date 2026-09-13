<?php
// Desactivamos salida de errores crudos para no romper el JSON
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

// Validación de seguridad multi-inquilino
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(["status" => "error", "message" => "Acceso denegado. Sesión inválida."]);
    exit();
}

require_once '../config/db.php'; 

$action = $_POST['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']);

if (ob_get_length()) ob_clean();

try {
    // --- ACCIÓN: LISTAR SERVICIOS/CUPS ---
    if ($action === 'listar') {
        $query = "SELECT id, codigo_cups, nombre_servicio, estado, created_at 
                  FROM servicios 
                  WHERE clinica_id = ? 
                  ORDER BY codigo_cups ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$clinica_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- ACCIÓN: REGISTRAR SERVICIO ---
    if ($action === 'registrar') {
        $codigo_cups = strtoupper(trim($_POST['codigo_cups'] ?? ''));
        $nombre_servicio = trim($_POST['nombre_servicio'] ?? '');

        if (empty($codigo_cups) || empty($nombre_servicio)) {
            echo json_encode(["status" => "warning", "message" => "Todos los campos son obligatorios."]);
            exit();
        }

        // Validar duplicado de CUPS en la misma clínica
        $stmtCheck = $pdo->prepare("SELECT id FROM servicios WHERE codigo_cups = ? AND clinica_id = ?");
        $stmtCheck->execute([$codigo_cups, $clinica_id]);
        if ($stmtCheck->fetch()) {
            echo json_encode(["status" => "warning", "message" => "El código CUPS ya está registrado en esta clínica."]);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO servicios (clinica_id, codigo_cups, nombre_servicio, estado) VALUES (?, ?, ?, 1)");
        $stmt->execute([$clinica_id, $codigo_cups, $nombre_servicio]);

        echo json_encode(["status" => "success", "message" => "Servicio CUPS registrado con éxito."]);
        exit();
    }

    // --- ACCIÓN: EDITAR SERVICIO ---
    if ($action === 'editar') {
        $id = intval($_POST['servicio_id'] ?? 0);
        $codigo_cups = strtoupper(trim($_POST['codigo_cups'] ?? ''));
        $nombre_servicio = trim($_POST['nombre_servicio'] ?? '');

        if ($id === 0 || empty($codigo_cups) || empty($nombre_servicio)) {
            echo json_encode(["status" => "warning", "message" => "Parámetros incompletos."]);
            exit();
        }

        // Validar que el CUPS no lo tenga otro servicio de la misma clínica
        $stmtCheck = $pdo->prepare("SELECT id FROM servicios WHERE codigo_cups = ? AND clinica_id = ? AND id != ?");
        $stmtCheck->execute([$codigo_cups, $clinica_id, $id]);
        if ($stmtCheck->fetch()) {
            echo json_encode(["status" => "warning", "message" => "El código CUPS ya está asignado a otro servicio."]);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE servicios SET codigo_cups = ?, nombre_servicio = ? WHERE id = ? AND clinica_id = ?");
        $stmt->execute([$codigo_cups, $nombre_servicio, $id, $clinica_id]);

        echo json_encode(["status" => "success", "message" => "Servicio actualizado correctamente."]);
        exit();
    }

    // --- ACCIÓN: CAMBIAR ESTADO ---
    if ($action === 'estado') {
        $id = intval($_POST['servicio_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);

        $stmt = $pdo->prepare("UPDATE servicios SET estado = ? WHERE id = ? AND clinica_id = ?");
        $stmt->execute([$nuevo_estado, $id, $clinica_id]);

        $msg = ($nuevo_estado === 1) ? "Servicio activado." : "Servicio inactivado.";
        echo json_encode(["status" => "success", "message" => $msg]);
        exit();
    }

    // --- ACCIÓN: ELIMINAR ---
    if ($action === 'eliminar') {
        $id = intval($_POST['servicio_id'] ?? 0);

        try {
            $stmt = $pdo->prepare("DELETE FROM servicios WHERE id = ? AND clinica_id = ?");
            $stmt->execute([id, $clinica_id]);
            echo json_encode(["status" => "success", "message" => "Servicio eliminado del portafolio."]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "No se puede eliminar. Este servicio ya cuenta con un historial transaccional o tarifas asociadas."]);
        }
        exit();
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
    exit();
}