<?php
// 1. Blindaje contra salidas de texto plano accidentales
ini_set('display_errors', 0); 
error_reporting(E_ALL);

// Forzamos que la cabecera de respuesta sea estrictamente JSON
header('Content-Type: application/json; charset=utf-8');

session_start();

// 2. Validación de seguridad de la sesión
if (!isset($_SESSION['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Acceso denegado. Sesión no iniciada."
    ]);
    exit();
}

// Ajuste automático de ruta hacia la conexión de la base de datos
require_once '../config/db.php'; 

$action = $_POST['action'] ?? '';

// Limpiamos cualquier espacio en blanco o buffer previo en el servidor
if (ob_get_length()) ob_clean();

try {
    // --- ACCIÓN: LISTAR MÉDICOS ---
    if ($action === 'listar') {
        $query = "SELECT 
                    u.id AS usuario_id, 
                    u.nombre AS medico_nombre, 
                    u.email AS medico_email, 
                    u.usuario, 
                    u.rol_id,
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
                  LEFT JOIN especialidades e ON m.especialidad = e.id
                  WHERE u.rol_id IN (2, 3) 
                  ORDER BY u.nombre ASC";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute();
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
        $rol_id = intval($_POST['rol_id'] ?? 2);
        $licencia_medica = trim($_POST['licencia_medica'] ?? '');
        $especialidad_id = intval($_POST['especialidad_id'] ?? 0);
        $universidad_egreso = trim($_POST['universidad_egreso'] ?? '');
        $anos_experiencia = intval($_POST['anos_experiencia'] ?? 0);
        $clinica_id = $_SESSION['clinica'] ?? null; 

        if (empty($nombre) || empty($email) || empty($usuario) || empty($password) || empty($licencia_medica) || empty($especialidad_id)) {
            echo json_encode(["status" => "warning", "message" => "Todos los campos marcados con (*) son obligatorios."]);
            exit();
        }

        $pdo->beginTransaction();

        // Verificar si el usuario o correo ya existen
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios_clinicas WHERE usuario = ? OR email = ?");
        $stmtCheck->execute([$usuario, $email]);
        if ($stmtCheck->fetch()) {
            echo json_encode(["status" => "warning", "message" => "El nombre de usuario o correo electrónico ya se encuentran registrados."]);
            exit();
        }

        // Insertar en tabla usuarios_clinicas (Campos reales)
        $passHash = password_hash($password, PASSWORD_BCRYPT);
        $stmtUser = $pdo->prepare("INSERT INTO usuarios_clinicas (clinica_id, rol_id, nombre, usuario, email, password, estado) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmtUser->execute([$clinica_id, $rol_id, $nombre, $usuario, $email, $passHash]);
        $usuario_id = $pdo->lastInsertId();

        // Insertar en tabla datos_medicos (Campos reales)
        $stmtMedico = $pdo->prepare("INSERT INTO datos_medicos (usuario_id, licencia_medica, especialidad, universidad_egreso, anos_experiencia, estado_medico) VALUES (?, ?, ?, ?, ?, 1)");
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
        $rol_id = intval($_POST['rol_id'] ?? 2);
        $licencia_medica = trim($_POST['licencia_medica'] ?? '');
        $especialidad_id = intval($_POST['especialidad_id'] ?? 0);
        $universidad_egreso = trim($_POST['universidad_egreso'] ?? '');
        $anos_experiencia = intval($_POST['anos_experiencia'] ?? 0);

        if ($usuario_id === 0 || empty($nombre) || empty($email) || empty($usuario) || empty($licencia_medica) || empty($especialidad_id)) {
            echo json_encode(["status" => "warning", "message" => "Faltan parámetros obligatorios para procesar la edición."]);
            exit();
        }

        $pdo->beginTransaction();

        // Actualizar datos del usuario base
        if (!empty($password)) {
            $passHash = password_hash($password, PASSWORD_BCRYPT);
            $stmtUpdateUser = $pdo->prepare("UPDATE usuarios_clinicas SET nombre = ?, email = ?, usuario = ?, password = ?, rol_id = ? WHERE id = ?");
            $stmtUpdateUser->execute([$nombre, $email, $usuario, $passHash, $rol_id, $usuario_id]);
        } else {
            $stmtUpdateUser = $pdo->prepare("UPDATE usuarios_clinicas SET nombre = ?, email = ?, usuario = ?, rol_id = ? WHERE id = ?");
            $stmtUpdateUser->execute([$nombre, $email, $usuario, $rol_id, $usuario_id]);
        }

        // Actualizar datos del perfil médico en datos_medicos
        $stmtUpdateMedico = $pdo->prepare("UPDATE datos_medicos SET licencia_medica = ?, especialidad = ?, universidad_egreso = ?, anos_experiencia = ? WHERE usuario_id = ?");
        $stmtUpdateMedico->execute([$licencia_medica, $especialidad_id, $universidad_egreso, $anos_experiencia, $usuario_id]);

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Información del médico actualizada exitosamente."]);
        exit();
    }

    // --- ACCIÓN: ELIMINAR MÉDICO ---
    if ($action === 'eliminar') {
        $usuario_id = intval($_POST['usuario_id'] ?? 0);

        if ($usuario_id === 0) {
            echo json_encode(["status" => "error", "message" => "ID de usuario inválido."]);
            exit();
        }

        $pdo->beginTransaction();

        // Eliminar de datos_medicos primero por integridad referencial
        $stmtDelMedico = $pdo->prepare("DELETE FROM datos_medicos WHERE usuario_id = ?");
        $stmtDelMedico->execute([$usuario_id]);

        // Luego eliminar de usuarios_clinicas
        $stmtDelUser = $pdo->prepare("DELETE FROM usuarios_clinicas WHERE id = ?");
        $stmtDelUser->execute([$usuario_id]);

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "El profesional médico ha sido eliminado del sistema de manera permanente."]);
        exit();
    }

    echo json_encode(["status" => "error", "message" => "Acción no definida en el controlador backend."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "status" => "error",
        "message" => "Excepción interna del servidor: " . $e->getMessage()
    ]);
    exit();
}