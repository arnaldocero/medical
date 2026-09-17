<?php
// 1. Configuración de errores y cabecera JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

session_start();

// 2. Control de sesión y clínica asignada
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Acceso denegado o sesión expirada."
    ]);
    exit();
}

require_once '../config/db.php'; 

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']);

if (ob_get_length()) ob_clean();

try {
    // --- ACCIÓN: LISTAR MÉDICOS (DINÁMICO POR CLÍNICA) ---
    if ($action === 'listar') {
        $query = "SELECT 
                    u.id AS usuario_id, 
                    u.nombre AS medico_nombre, 
                    u.email AS medico_email, 
                    u.usuario, 
                    u.rol_id,
                    r.nombre_rol,
                    u.estado AS usuario_estado,
                    m.id AS medico_id,
                    m.licencia_medica, 
                    m.especialidad AS especialidad_id,
                    m.universidad_egreso,
                    m.anos_experiencia,
                    m.estado_medico,
                    e.nombre AS especialidad_nombre
                  FROM usuarios_clinicas u
                  INNER JOIN datos_medicos m ON u.id = m.usuario_id
                  LEFT JOIN roles_clinicas r ON u.rol_id = r.id
                  LEFT JOIN especialidades e ON m.especialidad = e.id
                  WHERE u.clinica_id = ? AND u.estado = 1 AND m.estado_medico = 1
                  ORDER BY u.nombre ASC";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([$clinica_id]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- ACCIÓN: REGISTRAR NUEVO MÉDICO ---
    if ($action === 'registrar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol_id = intval($_POST['rol_id'] ?? 0);
        $licencia_medica = trim($_POST['licencia_medica'] ?? '');
        $especialidad_id = intval($_POST['especialidad_id'] ?? 0);
        $universidad_egreso = trim($_POST['universidad_egreso'] ?? '');
        $anos_experiencia = intval($_POST['anos_experiencia'] ?? 0);

        if (empty($nombre) || empty($email) || empty($usuario) || empty($password) || empty($licencia_medica) || empty($especialidad_id) || empty($rol_id)) {
            echo json_encode(["status" => "warning", "message" => "Todos los campos con (*) y el rol son obligatorios."]);
            exit();
        }

        $pdo->beginTransaction();

        // Validar unicidad de credenciales
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios_clinicas WHERE (usuario = ? OR email = ?) LIMIT 1");
        $stmtCheck->execute([$usuario, $email]);
        if ($stmtCheck->fetch()) {
            $pdo->rollBack();
            echo json_encode(["status" => "warning", "message" => "El usuario o correo electrónico ya existen en el sistema."]);
            exit();
        }

        // Validar unicidad de licencia médica
        $stmtCheckLic = $pdo->prepare("SELECT id FROM datos_medicos WHERE licencia_medica = ? LIMIT 1");
        $stmtCheckLic->execute([$licencia_medica]);
        if ($stmtCheckLic->fetch()) {
            $pdo->rollBack();
            echo json_encode(["status" => "warning", "message" => "El número de licencia médica ya se encuentra registrado."]);
            exit();
        }

        // 1. Insertar cuenta de acceso
        $passHash = password_hash($password, PASSWORD_DEFAULT);
        $stmtUser = $pdo->prepare("
            INSERT INTO usuarios_clinicas (clinica_id, rol_id, nombre, usuario, email, password, estado, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmtUser->execute([$clinica_id, $rol_id, $nombre, $usuario, $email, $passHash]);
        $usuario_id = $pdo->lastInsertId();

        // 2. Insertar ficha técnica médica
        $stmtMedico = $pdo->prepare("
            INSERT INTO datos_medicos (usuario_id, licencia_medica, especialidad, universidad_egreso, anos_experiencia, estado_medico, creado_en) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmtMedico->execute([$usuario_id, $licencia_medica, $especialidad_id, $universidad_egreso, $anos_experiencia]);

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Médico registrado correctamente en el sistema."]);
        exit();
    }

    // --- ACCIÓN: EDITAR MÉDICO ---
    if ($action === 'editar') {
        $usuario_id = intval($_POST['usuario_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol_id = intval($_POST['rol_id'] ?? 0);
        $licencia_medica = trim($_POST['licencia_medica'] ?? '');
        $especialidad_id = intval($_POST['especialidad_id'] ?? 0);
        $universidad_egreso = trim($_POST['universidad_egreso'] ?? '');
        $anos_experiencia = intval($_POST['anos_experiencia'] ?? 0);

        if ($usuario_id === 0 || empty($nombre) || empty($email) || empty($usuario) || empty($licencia_medica) || empty($especialidad_id) || empty($rol_id)) {
            echo json_encode(["status" => "warning", "message" => "Faltan parámetros obligatorios para procesar la edición."]);
            exit();
        }

        $pdo->beginTransaction();

        // Comprobar colisiones de credenciales con otros registros
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios_clinicas WHERE (usuario = ? OR email = ?) AND id != ? LIMIT 1");
        $stmtCheck->execute([$usuario, $email, $usuario_id]);
        if ($stmtCheck->fetch()) {
            $pdo->rollBack();
            echo json_encode(["status" => "warning", "message" => "El usuario o correo ya está en uso por otro registro."]);
            exit();
        }

        // Comprobar colisión de licencia médica con otro médico
        $stmtCheckLic = $pdo->prepare("SELECT id FROM datos_medicos WHERE licencia_medica = ? AND usuario_id != ? LIMIT 1");
        $stmtCheckLic->execute([$licencia_medica, $usuario_id]);
        if ($stmtCheckLic->fetch()) {
            $pdo->rollBack();
            echo json_encode(["status" => "warning", "message" => "La licencia médica ya está asignada a otro profesional."]);
            exit();
        }

        // 1. Actualizar tabla usuarios_clinicas
        if (!empty($password)) {
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUpdateUser = $pdo->prepare("
                UPDATE usuarios_clinicas 
                SET nombre = ?, email = ?, usuario = ?, password = ?, rol_id = ? 
                WHERE id = ? AND clinica_id = ?
            ");
            $stmtUpdateUser->execute([$nombre, $email, $usuario, $passHash, $rol_id, $usuario_id, $clinica_id]);
        } else {
            $stmtUpdateUser = $pdo->prepare("
                UPDATE usuarios_clinicas 
                SET nombre = ?, email = ?, usuario = ?, rol_id = ? 
                WHERE id = ? AND clinica_id = ?
            ");
            $stmtUpdateUser->execute([$nombre, $email, $usuario, $rol_id, $usuario_id, $clinica_id]);
        }

        // 2. Actualizar tabla datos_medicos
        $stmtUpdateMedico = $pdo->prepare("
            UPDATE datos_medicos 
            SET licencia_medica = ?, especialidad = ?, universidad_egreso = ?, anos_experiencia = ? 
            WHERE usuario_id = ?
        ");
        $stmtUpdateMedico->execute([$licencia_medica, $especialidad_id, $universidad_egreso, $anos_experiencia, $usuario_id]);

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Información del médico actualizada exitosamente."]);
        exit();
    }

    // --- ACCIÓN: ELIMINAR (DESACTIVACIÓN SEGURA POR HISTORIAL CLÍNICO) ---
    if ($action === 'eliminar') {
        $usuario_id = intval($_POST['usuario_id'] ?? 0);

        if ($usuario_id === 0) {
            echo json_encode(["status" => "error", "message" => "Identificador de usuario inválido."]);
            exit();
        }

        $pdo->beginTransaction();

        // Se usa borrado lógico en ambas tablas para preservar citas, recetas e historias
        $stmtDelUser = $pdo->prepare("UPDATE usuarios_clinicas SET estado = 0 WHERE id = ? AND clinica_id = ?");
        $stmtDelUser->execute([$usuario_id, $clinica_id]);

        $stmtDelMed = $pdo->prepare("UPDATE datos_medicos SET estado_medico = 0 WHERE usuario_id = ?");
        $stmtDelMed->execute([$usuario_id]);

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "El profesional médico ha sido desactivado del sistema correctamente."]);
        exit();
    }

    echo json_encode(["status" => "error", "message" => "Acción no reconocida por el servidor."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "status" => "error",
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
    exit();
}