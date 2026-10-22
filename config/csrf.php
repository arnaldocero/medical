<?php
/**
 * Protección CSRF (Cross-Site Request Forgery).
 *
 * Cada sesión tiene un token aleatorio. Toda petición POST debe enviarlo:
 *  - AJAX (jQuery): cabecera "X-CSRF-Token" (se agrega sola desde layout/footer.php)
 *  - Formularios normales: campo oculto "csrf_token" (usar campoCSRF())
 * Así otra página web no puede enviar acciones en nombre de un usuario con sesión abierta.
 */

function tokenCSRF() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto para formularios HTML que se envían sin AJAX.
 */
function campoCSRF() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(tokenCSRF()) . '">';
}

/**
 * Etiqueta <meta> que lee el JS para enviar el token en las peticiones AJAX.
 */
function metaCSRF() {
    return '<meta name="csrf-token" content="' . htmlspecialchars(tokenCSRF()) . '">';
}

function csrfValido() {
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    return !empty($_SESSION['csrf_token']) && is_string($enviado) && hash_equals($_SESSION['csrf_token'], $enviado);
}

/**
 * Detiene las peticiones POST sin token válido (respuesta JSON 403).
 */
function validarCSRF() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || csrfValido()) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json', true, 403);
    }
    echo json_encode([
        'status'  => 'error',
        'message' => 'La sesión de seguridad expiró. Recargue la página (F5) e intente de nuevo.'
    ]);
    exit();
}
