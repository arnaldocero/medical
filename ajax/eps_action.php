<?php
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

session_start();

// Validación de seguridad de la sesión y existencia de la clínica activa
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Acceso denegado. Sesión o clínica no válida."
    ]);
    exit();
}

require_once '../config/db.php'; 

$action = $_POST['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']); // Captura el ID de la clínica del usuario actual

if (ob_get_length()) ob_clean();

try {
    // --- ACCIÓN: LISTAR EPS (FILTRADO POR CLÍNICA) ---
    if ($action === 'listar') {
        // Filtramos estrictamente por la clínica del usuario en sesión
        $query = "SELECT id, nombre, codigo_minsalud, estado, created_at 
                  FROM eps 
                  WHERE clinica_id = ? 
                  ORDER BY nombre ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$clinica_id]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- ACCIÓN: REGISTRAR NUEVA EPS ---
    if ($action === 'registrar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo_minsalud = trim($_POST['codigo_minsalud'] ?? '');

        if (empty($nombre) || empty($codigo_minsalud)) {
            echo json_encode(["status" => "warning", "message" => "Todos los campos obligatorios son requeridos."]);
            exit();
        }

        // Verificar duplicados del código de MinSalud SOLO dentro de esta clínica
        $stmtCheck = $pdo->prepare("SELECT id FROM eps WHERE codigo_minsalud = ? AND clinica_id = ?");
        $stmtCheck->execute([$codigo_minsalud, $clinica_id]);
        if ($stmtCheck->fetch()) {
            echo json_encode(["status" => "warning", "message" => "El código de MinSalud ya está registrado en tu clínica."]);
            exit();
        }

        // Insertamos asociando la entidad a la clínica activa
        $stmt = $pdo->prepare("INSERT INTO eps (clinica_id, nombre, codigo_minsalud, estado) VALUES (?, ?, ?, 1)");
        $stmt->execute([$clinica_id, $nombre, $codigo_minsalud]);

        echo json_encode(["status" => "success", "message" => "EPS registrada correctamente en el sistema."]);
        exit();
    }

    // --- ACCIÓN: EDITAR EPS ---
    if ($action === 'editar') {
        $id = intval($_POST['eps_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo_minsalud = trim($_POST['codigo_minsalud'] ?? '');

        if ($id === 0 || empty($nombre) || empty($codigo_minsalud)) {
            echo json_encode(["status" => "warning", "message" => "Faltan parámetros obligatorios para procesar la edición."]);
            exit();
        }

        // Verificar que el código MinSalud no pertenezca a otra EPS de la MISMA clínica
        $stmtCheck = $pdo->prepare("SELECT id FROM eps WHERE codigo_minsalud = ? AND clinica_id = ? AND id != ?");
        $stmtCheck->execute([$codigo_minsalud, $clinica_id, $id]);
        if ($stmtCheck->fetch()) {
            echo json_encode(["status" => "warning", "message" => "El código de MinSalud ya está asignado a otra EPS en esta clínica."]);
            exit();
        }

        // Validamos por seguridad que la EPS que se intenta editar pertenezca a su clínica
        $stmtUpdate = $pdo->prepare("UPDATE eps SET nombre = ?, codigo_minsalud = ? WHERE id = ? AND clinica_id = ?");
        $stmtUpdate->execute([$nombre, $codigo_minsalud, $id, $clinica_id]);

        echo json_encode(["status" => "success", "message" => "Información de la EPS actualizada exitosamente."]);
        exit();
    }

    // --- ACCIÓN: CAMBIAR ESTADO (ACTIVAR / INACTIVAR) ---
    if ($action === 'estado') {
        $id = intval($_POST['eps_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);

        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID de EPS inválido."]);
            exit();
        }

        // Agregamos clinica_id en el WHERE por seguridad (Evita manipulación cruzada de IDs de otras clínicas)
        $stmt = $pdo->prepare("UPDATE eps SET estado = ? WHERE id = ? AND clinica_id = ?");
        $stmt->execute([$nuevo_estado, $id, $clinica_id]);

        $msg = ($nuevo_estado === 1) ? "EPS activada correctamente." : "EPS inactivada correctamente.";
        echo json_encode(["status" => "success", "message" => $msg]);
        exit();
    }

    // --- ACCIÓN: ELIMINAR EPS ---
    if ($action === 'eliminar') {
        $id = intval($_POST['eps_id'] ?? 0);

        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID de EPS inválido."]);
            exit();
        }

        try {
            // Aseguramos que solo pueda eliminar EPS de su propia clínica
            $stmt = $pdo->prepare("DELETE FROM eps WHERE id = ? AND clinica_id = ?");
            $stmt->execute([$id, $clinica_id]);
            echo json_encode(["status" => "success", "message" => "La EPS ha sido eliminada del sistema permanentemente."]);
        } catch (PDOException $ex) {
            echo json_encode(["status" => "error", "message" => "No se puede eliminar la EPS porque tiene registros de pacientes asociados."]);
        }
        exit();
    }

    echo json_encode(["status" => "error", "message" => "Acción no definida en el controlador."]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Excepción interna del servidor: " . $e->getMessage()
    ]);
    exit();
}