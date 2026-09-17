<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada o no válida.']);
    exit();
}

require_once '../config/db.php';
require_once '../config/security.php';

// Comprobación de seguridad
if ((!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) && !tienePermiso('roles.administrar')) {
    echo json_encode(['status' => 'error', 'message' => 'No posee permisos administrativos para esta acción.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (ob_get_length()) ob_clean();

try {
    // 1. LISTAR ROLES CON CONTEO DE USUARIOS Y PERMISOS
    if ($action === 'listar') {
        $sql = "SELECT 
                    r.id, 
                    r.nombre_rol, 
                    r.descripcion,
                    (SELECT COUNT(*) FROM usuarios_clinicas u WHERE u.rol_id = r.id AND u.estado = 1) AS total_usuarios,
                    (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.rol_id = r.id) AS total_permisos
                FROM roles_clinicas r
                ORDER BY r.id ASC";

        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. OBTENER UN ROL Y SUS PERMISOS ASIGNADOS
    if ($action === 'obtener') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de rol inválido.']);
            exit();
        }

        $stmtR = $pdo->prepare("SELECT id, nombre_rol, descripcion FROM roles_clinicas WHERE id = ?");
        $stmtR->execute([$id]);
        $rol = $stmtR->fetch(PDO::FETCH_ASSOC);

        if (!$rol) {
            echo json_encode(['status' => 'error', 'message' => 'El rol no existe.']);
            exit();
        }

        // Obtener array de IDs de permisos asignados
        $stmtP = $pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id = ?");
        $stmtP->execute([$id]);
        $permisos = $stmtP->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'status' => 'success',
            'rol' => $rol,
            'permisos' => array_map('intval', $permisos)
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 3. GUARDAR (CREAR O EDITAR) ROL Y SINCRONIZAR PERMISOS
    if ($action === 'guardar') {
        $idRol = !empty($_POST['idRol']) ? intval($_POST['idRol']) : null;
        $nombre_rol = trim($_POST['nombre_rol'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $permisos = isset($_POST['permisos']) && is_array($_POST['permisos']) ? array_map('intval', $_POST['permisos']) : [];

        if (empty($nombre_rol)) {
            echo json_encode(['status' => 'warning', 'message' => 'El nombre del rol es obligatorio.']);
            exit();
        }

        $pdo->beginTransaction();

        // Verificar duplicados de nombre
        if ($idRol) {
            $stmtCheck = $pdo->prepare("SELECT id FROM roles_clinicas WHERE nombre_rol = ? AND id != ?");
            $stmtCheck->execute([$nombre_rol, $idRol]);
        } else {
            $stmtCheck = $pdo->prepare("SELECT id FROM roles_clinicas WHERE nombre_rol = ?");
            $stmtCheck->execute([$nombre_rol]);
        }

        if ($stmtCheck->fetch()) {
            $pdo->rollBack();
            echo json_encode(['status' => 'warning', 'message' => 'Ya existe un rol con ese nombre.']);
            exit();
        }

        if ($idRol) {
            // Actualizar rol existente
            $stmtU = $pdo->prepare("UPDATE roles_clinicas SET nombre_rol = ?, descripcion = ? WHERE id = ?");
            $stmtU->execute([$nombre_rol, $descripcion, $idRol]);
            $targetId = $idRol;
            $msg = 'Rol actualizado exitosamente.';
        } else {
            // Insertar nuevo rol
            $stmtI = $pdo->prepare("INSERT INTO roles_clinicas (nombre_rol, descripcion) VALUES (?, ?)");
            $stmtI->execute([$nombre_rol, $descripcion]);
            $targetId = intval($pdo->lastInsertId());
            $msg = 'Rol creado exitosamente.';
        }

        // Sincronizar permisos en rol_permisos
        $stmtDel = $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id = ?");
        $stmtDel->execute([$targetId]);

        if (!empty($permisos)) {
            $stmtInsPerm = $pdo->prepare("INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)");
            foreach ($permisos as $permisoId) {
                if ($permisoId > 0) {
                    $stmtInsPerm->execute([$targetId, $permisoId]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => $msg]);
        exit();
    }

    // 4. ELIMINAR ROL CON PROTECCIÓN DE INTEGRIDAD REFERENCIAL
    if ($action === 'eliminar') {
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido.']);
            exit();
        }

        // Proteger el rol principal del sistema (Admin)
        if ($id === 1) {
            echo json_encode(['status' => 'warning', 'message' => 'El rol Administrador Principal está protegido y no puede eliminarse.']);
            exit();
        }

        // Validar si existen usuarios asignados a este rol
        $stmtCheckUsers = $pdo->prepare("SELECT COUNT(*) FROM usuarios_clinicas WHERE rol_id = ?");
        $stmtCheckUsers->execute([$id]);
        $totalUsuarios = intval($stmtCheckUsers->fetchColumn());

        if ($totalUsuarios > 0) {
            echo json_encode([
                'status' => 'warning', 
                'message' => "No se puede eliminar el rol porque tiene {$totalUsuarios} usuario(s) asignado(s). Reasigne a los usuarios antes de continuar."
            ]);
            exit();
        }

        $pdo->beginTransaction();

        // Eliminar permisos asociados y el rol
        $stmtDelP = $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id = ?");
        $stmtDelP->execute([$id]);

        $stmtDelR = $pdo->prepare("DELETE FROM roles_clinicas WHERE id = ?");
        $stmtDelR->execute([$id]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Rol eliminado exitosamente.']);
        exit();
    }

    echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Error en el servidor: ' . $e->getMessage()]);
    exit();
}