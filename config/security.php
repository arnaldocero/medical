<?php
/**
 * Motor de Seguridad y Autorización RBAC
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Control de acceso por sede (centro de atención)
require_once __DIR__ . '/sede.php';

// Protección CSRF para peticiones POST
require_once __DIR__ . '/csrf.php';

/**
 * Mensaje seguro para mostrar al usuario cuando ocurre una excepción.
 *  - Avisos de negocio lanzados por el propio código (new Exception("...")): se muestran tal cual.
 *  - Errores de base de datos o del sistema (PDOException, etc.): se registran en el log del
 *    servidor con un código de referencia y al usuario solo se le muestra un mensaje genérico,
 *    sin revelar tablas, columnas ni consultas SQL.
 */
function mensajeErrorInterno($e) {
    if (get_class($e) === 'Exception') {
        return $e->getMessage();
    }
    $codigo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    error_log("[ERR-$codigo] " . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    return "Ocurrió un error interno al procesar la solicitud (código $codigo). Intente de nuevo o contacte al administrador.";
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
 * Protección AJAX: exige una sesión activa (usuario y clínica).
 * Responde 401 en JSON y detiene la ejecución si la sesión no es válida.
 */
function requerirSesionAJAX() {
    if (!isset($_SESSION['id'], $_SESSION['clinica'])) {
        header('Content-Type: application/json', true, 401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesión expirada. Inicie sesión nuevamente.'
        ]);
        exit();
    }
    if (!sedeSeleccionada()) {
        header('Content-Type: application/json', true, 401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Seleccione la sede de trabajo para continuar.'
        ]);
        exit();
    }
    // Toda petición POST de un usuario autenticado debe traer el token CSRF
    validarCSRF();
}

/**
 * Variante de protección exclusiva para peticiones AJAX / API.
 * Exige sesión activa y el permiso indicado. Retorna JSON estructurado
 * y detiene la ejecución para no exponer lógica interna.
 *
 * @param string|array $permisoSlug Slug requerido, o lista de slugs (basta con tener uno).
 */
function requerirPermisoAJAX($permisoSlug) {
    requerirSesionAJAX();

    $permitido = false;
    foreach ((array) $permisoSlug as $slug) {
        if (tienePermiso($slug)) {
            $permitido = true;
            break;
        }
    }

    if (!$permitido) {
        header('Content-Type: application/json', true, 403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Acceso denegado: Carece de los privilegios necesarios para realizar esta acción.'
        ]);
        exit();
    }
}