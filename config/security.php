<?php
/**
 * Motor de Seguridad y Autorización RBAC
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Carga todos los permisos del rol del usuario en la sesión.
 * Llama a esta función inmediatamente después de que el usuario inicie sesión con éxito.
 */
function cargarPermisosUsuario($pdo, $rol_id) {
    $_SESSION['permisos'] = [];
    try {
        $sql = "SELECT p.slug FROM permisos p
                INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
                WHERE rp.rol_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([intval($rol_id)]);
        $_SESSION['permisos'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $_SESSION['permisos'] = [];
    }
}

/**
 * Comprueba si el usuario autenticado posee un permiso específico.
 * 
 * @param string $permisoSlug El slug del permiso (ej. 'medico.panel')
 * @return bool True si tiene acceso, False en caso contrario.
 */
function tienePermiso($permisoSlug) {
    // Si no hay sesión de permisos activa, denegar por defecto
    if (!isset($_SESSION['permisos']) || !is_array($_SESSION['permisos'])) {
        return false;
    }
    
    // El administrador tiene pase libre a todo el sistema
    if (isset($_SESSION['rol']) && intval($_SESSION['rol']) === 1) {
        return true;
    }
    
    return in_array($permisoSlug, $_SESSION['permisos']);
}

/**
 * Bloquea el acceso a una vista PHP si el usuario no tiene el permiso correspondiente.
 * Redirecciona al dashboard con una alerta.
 * 
 * @param string $permisoSlug El slug del permiso requerido.
 */
function requerirPermiso($permisoSlug) {
    if (!tienePermiso($permisoSlug)) {
        // Almacenar mensaje de error temporal en sesión
        $_SESSION['security_error'] = "Acceso denegado: No cuenta con el permiso '$permisoSlug' para visualizar esta pantalla.";
        header("Location: index.php"); // Redirección segura al Dashboard
        exit();
    }
}

/**
 * Variante de protección exclusiva para peticiones AJAX / API.
 * Retorna JSON estructurado y detiene la ejecución para no exponer lógica interna.
 * 
 * @param string $permisoSlug El slug del permiso requerido.
 */
function requerirPermisoAJAX($permisoSlug) {
    if (!tienePermiso($permisoSlug)) {
        header('Content-Type: application/json', true, 430); // Código de estado personalizado o 403
        echo json_encode([
            'status' => 'error',
            'message' => 'Acceso denegado: Carece de los privilegios necesarios para realizar esta acción.'
        ]);
        exit();
    }
}