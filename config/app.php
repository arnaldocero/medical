<?php
/**
 * Configuración general de la aplicación.
 */

// URL pública de la aplicación, SIN barra final (ej. 'https://midominio.com/HMS').
// Se usa para construir los enlaces que se envían por correo.
// Si se deja vacía se detecta automáticamente a partir de la petición actual
// (funciona en /Medical local y en /HMS del servidor), pero en producción es
// más seguro fijarla para que nadie pueda alterar el dominio del enlace.
define('APP_URL', '');

// Remitente de los correos del sistema (debe ser un buzón del dominio del servidor)
define('MAIL_FROM', 'no-reply@medicalcreationsbusinesses.com');
define('MAIL_FROM_NAME', 'Medical Creations');

/**
 * Devuelve la URL base de la aplicación (sin barra final).
 */
function urlBaseApp() {
    if (APP_URL !== '') {
        return rtrim(APP_URL, '/');
    }
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $protocolo = $esHttps ? 'https' : 'http';

    // Carpeta raíz de la app = carpeta padre de /config
    $raizDocumento = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $raizApp = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $ruta = ($raizDocumento !== '' && strpos($raizApp, $raizDocumento) === 0)
        ? substr($raizApp, strlen($raizDocumento))
        : '';

    return $protocolo . '://' . $_SERVER['HTTP_HOST'] . rtrim($ruta, '/');
}

/**
 * Indica si la petición viene de la propia máquina (entorno de desarrollo local).
 * Usa REMOTE_ADDR, que el cliente no puede falsificar.
 */
function esPeticionLocal() {
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}
