<?php
/**
 * Utilidades de autenticación: inicio de sesión, "Recordarme" y cierre de sesión.
 * Requiere que $pdo (config/db.php) y config/security.php estén cargados.
 */
require_once __DIR__ . '/security.php';

define('REMEMBER_COOKIE', 'c_remember');
define('REMEMBER_DIAS', 30);

/**
 * Indica si la petición actual llega por HTTPS (para marcar la cookie como Secure).
 */
function esConexionSegura() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/**
 * Crea o borra la cookie de "Recordarme" con parámetros seguros.
 */
function escribirCookieRecordarme($valor, $expira) {
    setcookie(REMEMBER_COOKIE, $valor, [
        'expires'  => $expira,
        'path'     => '/',
        'secure'   => esConexionSegura(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Registra al usuario en la sesión y carga sus permisos.
 */
function iniciarSesionUsuario($pdo, array $usuario) {
    // Nuevo ID de sesión para evitar fijación de sesión
    session_regenerate_id(true);

    $_SESSION['id'] = $usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['rol'] = $usuario['rol_id'];
    $_SESSION['clinica'] = $usuario['clinica_id'];

    cargarPermisosUsuario($pdo, $usuario['rol_id']);
}

/**
 * Genera un token nuevo, guarda su hash en BD y lo envía en la cookie "Recordarme".
 */
function crearTokenRecordarme($pdo, $usuarioId) {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE usuarios_clinicas SET remember_token = ? WHERE id = ?")
        ->execute([hash('sha256', $token), $usuarioId]);

    escribirCookieRecordarme($usuarioId . ':' . $token, time() + REMEMBER_DIAS * 24 * 60 * 60);
}

/**
 * Invalida el token "Recordarme" del usuario y borra la cookie del navegador.
 */
function borrarTokenRecordarme($pdo, $usuarioId = null) {
    if ($usuarioId) {
        $pdo->prepare("UPDATE usuarios_clinicas SET remember_token = NULL WHERE id = ?")
            ->execute([$usuarioId]);
    }
    escribirCookieRecordarme('', time() - 3600);
    unset($_COOKIE[REMEMBER_COOKIE]);
}

/**
 * Si no hay sesión activa pero existe una cookie "Recordarme" válida, restaura la sesión.
 * El token se rota en cada uso.
 *
 * @return bool True si el usuario quedó autenticado.
 */
function restaurarSesionDesdeCookie($pdo) {
    if (isset($_SESSION['id'])) {
        return true;
    }

    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (strpos($cookie, ':') === false) {
        return false;
    }

    list($usuarioId, $token) = explode(':', $cookie, 2);
    $usuarioId = intval($usuarioId);

    if ($usuarioId <= 0 || $token === '') {
        borrarTokenRecordarme($pdo);
        return false;
    }

    $stmt = $pdo->prepare("SELECT * FROM usuarios_clinicas WHERE id = ? AND estado = 1 LIMIT 1");
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || empty($usuario['remember_token'])
        || !hash_equals($usuario['remember_token'], hash('sha256', $token))) {
        borrarTokenRecordarme($pdo);
        return false;
    }

    iniciarSesionUsuario($pdo, $usuario);
    crearTokenRecordarme($pdo, $usuario['id']);
    return true;
}
