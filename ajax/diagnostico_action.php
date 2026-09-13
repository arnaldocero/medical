<?php
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

// Validación de seguridad de sesión general (Cualquier clínica activa puede consumir esta maestra)
if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Acceso denegado."]);
    exit();
}

require_once '../config/db.php'; 

$action = $_POST['action'] ?? '';

if (ob_get_length()) ob_clean();

try {
    // --- ACCIÓN: LISTAR CON FILTRADO EN TIEMPO REAL (SERVER-SIDE LIGERO) ---
    if ($action === 'listar') {
        $search = trim($_POST['search'] ?? '');
        
        // Si no han escrito nada, limitamos a los primeros 100 por rendimiento
        if (empty($search)) {
            $query = "SELECT id, codigo_cie, descripcion, version, estado FROM diagnosticos ORDER BY codigo_cie ASC LIMIT 100";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
        } else {
            // Buscamos coincidencia por código o por descripción de la enfermedad
            $query = "SELECT id, codigo_cie, descripcion, version, estado 
                      FROM diagnosticos 
                      WHERE codigo_cie LIKE ? OR descripcion LIKE ? 
                      ORDER BY codigo_cie ASC LIMIT 150";
            $stmt = $pdo->prepare($query);
            $term = "%$search%";
            $stmt->execute([$term, $term]);
        }
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- ACCIÓN: REGISTRAR DIAGNÓSTICO NUEVO ---
    if ($action === 'registrar') {
        $codigo_cie = strtoupper(trim($_POST['codigo_cie'] ?? ''));
        $descripcion = trim($_POST['descripcion'] ?? '');
        $version = trim($_POST['version'] ?? 'CIE-10');

        if (empty($codigo_cie) || empty($descripcion)) {
            echo json_encode(["status" => "warning", "message" => "Código y Descripción son obligatorios."]);
            exit();
        }

        // Verificar duplicidad global
        $stmtCheck = $pdo->prepare("SELECT id FROM diagnosticos WHERE codigo_cie = ?");
        $stmtCheck->execute([$codigo_cie]);
        if ($stmtCheck->fetch()) {
            echo json_encode(["status" => "warning", "message" => "Este código de diagnóstico ya existe en la base de datos global."]);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO diagnosticos (codigo_cie, descripcion, version, estado) VALUES (?, ?, ?, 1)");
        $stmt->execute([$codigo_cie, $descripcion, $version]);

        echo json_encode(["status" => "success", "message" => "Diagnóstico incorporado exitosamente."]);
        exit();
    }

    // --- ACCIÓN: EDITAR DIAGNÓSTICO ---
    if ($action === 'editar') {
        $id = intval($_POST['diagnostico_id'] ?? 0);
        $codigo_cie = strtoupper(trim($_POST['codigo_cie'] ?? ''));
        $descripcion = trim($_POST['descripcion'] ?? '');
        $version = trim($_POST['version'] ?? 'CIE-10');

        if ($id === 0 || empty($codigo_cie) || empty($descripcion)) {
            echo json_encode(["status" => "warning", "message" => "Parámetros insuficientes."]);
            exit();
        }

        // Evitar colisión de códigos con otros registros
        $stmtCheck = $pdo->prepare("SELECT id FROM diagnosticos WHERE codigo_cie = ? AND id != ?");
        $stmtCheck->execute([$codigo_cie, $id]);
        if ($stmtCheck->fetch()) {
            echo json_encode(["status" => "warning", "message" => "El código ingresado ya está en uso por otro diagnóstico."]);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE diagnosticos SET codigo_cie = ?, descripcion = ?, version = ? WHERE id = ?");
        $stmt->execute([$codigo_cie, $descripcion, $version, $id]);

        echo json_encode(["status" => "success", "message" => "Diagnóstico actualizado correctamente."]);
        exit();
    }

    // --- ACCIÓN: CAMBIAR ESTADO ---
    if ($action === 'estado') {
        $id = intval($_POST['diagnostico_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);

        $stmt = $pdo->prepare("UPDATE diagnosticos SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $id]);

        echo json_encode(["status" => "success", "message" => "Estado del diagnóstico actualizado."]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
    exit();
}