<?php
/**
 * Límite de intentos de inicio de sesión.
 *
 *  - Cuenta: 3 intentos fallidos seguidos => bloqueo de 30 minutos + correo de alerta al usuario.
 *  - IP: 10 intentos fallidos en 30 minutos => esa IP no puede intentar más hasta que pasen.
 * Todas las horas se calculan con el reloj de la base de datos (NOW()) para evitar
 * diferencias de zona horaria entre PHP y MySQL.
 */
require_once __DIR__ . '/correo.php';

define('LOGIN_MAX_INTENTOS', 3);
define('LOGIN_MINUTOS_BLOQUEO', 30);
define('LOGIN_MAX_INTENTOS_IP', 10);

// "Olvidé mi contraseña": máximo de solicitudes por conexión (IP) y por correo
define('RECUPERACION_MAX_IP', 5);          // en 30 minutos
define('RECUPERACION_MAX_CORREO', 3);      // en 60 minutos

// Registros de login_intentos que se conservan (días)
define('LOGIN_DIAS_HISTORIAL', 90);

function ipCliente() {
    return substr($_SERVER['REMOTE_ADDR'] ?? 'desconocida', 0, 45);
}

function registrarIntentoLogin($pdo, $usuarioId, $usuarioIngresado, $exitoso, $motivo = null) {
    $pdo->prepare("INSERT INTO login_intentos (usuario_id, usuario_ingresado, ip, exitoso, motivo) VALUES (?, ?, ?, ?, ?)")
        ->execute([$usuarioId, mb_substr($usuarioIngresado, 0, 150), ipCliente(), $exitoso ? 1 : 0, $motivo]);
}

/**
 * True si la IP actual superó el máximo de intentos fallidos en la ventana de bloqueo.
 */
function ipBloqueada($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_intentos
                           WHERE ip = ? AND exitoso = 0 AND fecha > NOW() - INTERVAL " . LOGIN_MINUTOS_BLOQUEO . " MINUTE
                             AND (motivo IS NULL OR motivo <> 'recuperacion')");
    $stmt->execute([ipCliente()]);
    return $stmt->fetchColumn() >= LOGIN_MAX_INTENTOS_IP;
}

/**
 * Si la cuenta está bloqueada devuelve ['minutos' => restantes, 'hasta' => 'hh:mm AM'], si no null.
 */
function estadoBloqueoCuenta($pdo, $usuarioId) {
    $stmt = $pdo->prepare("SELECT GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) / 60)) AS minutos,
                                  DATE_FORMAT(bloqueado_hasta, '%h:%i %p') AS hasta
                           FROM usuarios_clinicas WHERE id = ? AND bloqueado_hasta > NOW()");
    $stmt->execute([$usuarioId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Suma un intento fallido. Al llegar al máximo bloquea la cuenta y avisa por correo.
 * @return bool True si la cuenta quedó bloqueada con este intento.
 */
function registrarFalloCuenta($pdo, array $usuario) {
    $pdo->prepare("UPDATE usuarios_clinicas SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?")
        ->execute([$usuario['id']]);

    $stmt = $pdo->prepare("SELECT intentos_fallidos FROM usuarios_clinicas WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    if ($stmt->fetchColumn() < LOGIN_MAX_INTENTOS) {
        return false;
    }

    $pdo->prepare("UPDATE usuarios_clinicas
                   SET intentos_fallidos = 0, bloqueado_hasta = NOW() + INTERVAL " . LOGIN_MINUTOS_BLOQUEO . " MINUTE
                   WHERE id = ?")->execute([$usuario['id']]);

    notificarBloqueo($pdo, $usuario);
    return true;
}

function limpiarFallosCuenta($pdo, $usuarioId) {
    $pdo->prepare("UPDATE usuarios_clinicas SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?")
        ->execute([$usuarioId]);
}

/**
 * Correo de alerta al dueño de la cuenta bloqueada.
 */
function notificarBloqueo($pdo, array $usuario) {
    if (empty($usuario['email'])) {
        return;
    }
    $momento = $pdo->query("SELECT DATE_FORMAT(NOW(), '%d/%m/%Y %h:%i %p')")->fetchColumn();
    $bloqueo = estadoBloqueoCuenta($pdo, $usuario['id']);
    $enlace = htmlspecialchars(urlBaseApp() . '/recuperar_password.php');

    $html = '<p>Hola <strong>' . htmlspecialchars($usuario['nombre']) . '</strong>,</p>'
          . '<p>Detectamos <strong>' . LOGIN_MAX_INTENTOS . ' intentos fallidos</strong> de inicio de sesión en su cuenta, '
          . 'por lo que fue <strong>bloqueada temporalmente por ' . LOGIN_MINUTOS_BLOQUEO . ' minutos</strong>.</p>'
          . '<table style="font-size:14px;margin:12px 0;">'
          . '<tr><td style="padding:4px 12px 4px 0;color:#666;">Fecha y hora:</td><td>' . htmlspecialchars($momento) . '</td></tr>'
          . '<tr><td style="padding:4px 12px 4px 0;color:#666;">Dirección IP:</td><td>' . htmlspecialchars(ipCliente()) . '</td></tr>'
          . '<tr><td style="padding:4px 12px 4px 0;color:#666;">Desbloqueo:</td><td>' . htmlspecialchars($bloqueo['hasta'] ?? '') . '</td></tr>'
          . '</table>'
          . '<p>Si fue usted, espere a que termine el bloqueo e intente de nuevo.</p>'
          . '<p><strong>Si NO fue usted</strong>, cambie su contraseña de inmediato. Al restablecerla la cuenta se desbloquea:</p>'
          . '<p><a href="' . $enlace . '" style="background:#dc3545;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;">Restablecer contraseña</a></p>';

    enviarCorreo($usuario['email'], 'Alerta de seguridad: cuenta bloqueada', $html);
}

/**
 * Desbloqueo manual de una cuenta por un administrador (no espera los 30 minutos).
 * Queda registrado en login_intentos con motivo 'desbloqueo_admin'.
 */
function desbloquearCuenta($pdo, $usuarioId, $quienDesbloquea) {
    limpiarFallosCuenta($pdo, $usuarioId);
    $pdo->prepare("INSERT INTO login_intentos (usuario_id, usuario_ingresado, ip, exitoso, motivo) VALUES (?, ?, ?, 1, 'desbloqueo_admin')")
        ->execute([$usuarioId, mb_substr('Desbloqueado por: ' . $quienDesbloquea, 0, 150), ipCliente()]);
}

/**
 * Límite de "Olvidé mi contraseña". Devuelve un mensaje de espera si se superó, o null si puede continuar.
 * El conteo por correo se hace exista o no el correo, para no revelar qué cuentas existen.
 */
function limiteRecuperacion($pdo, $email) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_intentos WHERE motivo = 'recuperacion' AND ip = ? AND fecha > NOW() - INTERVAL 30 MINUTE");
    $stmt->execute([ipCliente()]);
    if ($stmt->fetchColumn() >= RECUPERACION_MAX_IP) {
        return 'Demasiadas solicitudes desde esta conexión. Espere 30 minutos e intente de nuevo.';
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_intentos WHERE motivo = 'recuperacion' AND usuario_ingresado = ? AND fecha > NOW() - INTERVAL 60 MINUTE");
    $stmt->execute([mb_substr($email, 0, 150)]);
    if ($stmt->fetchColumn() >= RECUPERACION_MAX_CORREO) {
        return 'Ya se enviaron varias solicitudes para este correo. Revise su bandeja de entrada (y spam) o espere una hora.';
    }
    return null;
}

/**
 * Registra una solicitud de recuperación (cuenta para el límite; no cuenta como intento fallido de login).
 */
function registrarSolicitudRecuperacion($pdo, $usuarioId, $email) {
    registrarIntentoLogin($pdo, $usuarioId, $email, false, 'recuperacion');
}

/**
 * Borra los registros de login_intentos de más de LOGIN_DIAS_HISTORIAL días.
 * Se ejecuta de vez en cuando (aprox. 1 de cada 50 inicios de sesión): no necesita tareas programadas.
 */
function limpiarIntentosAntiguos($pdo, $probabilidad = 50) {
    if (random_int(1, $probabilidad) !== 1) {
        return 0;
    }
    return $pdo->exec("DELETE FROM login_intentos WHERE fecha < NOW() - INTERVAL " . LOGIN_DIAS_HISTORIAL . " DAY");
}

/**
 * Columnas SQL para mostrar en los listados si la cuenta está bloqueada.
 */
function sqlEstadoBloqueo($alias = 'u') {
    return "($alias.bloqueado_hasta IS NOT NULL AND $alias.bloqueado_hasta > NOW()) AS bloqueado,
            DATE_FORMAT($alias.bloqueado_hasta, '%h:%i %p') AS bloqueado_hasta_txt";
}
