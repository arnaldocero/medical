<?php
/**
 * Envío de correos del sistema (usa la función mail() del servidor).
 * Remitente configurado en .env: MAIL_FROM y MAIL_FROM_NAME.
 */
require_once __DIR__ . '/app.php';

/**
 * Envía un correo HTML. Devuelve true si el servidor aceptó el envío.
 */
function enviarCorreo($para, $asunto, $contenidoHtml) {
    $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto . ' - ' . MAIL_FROM_NAME) . '?=';
    $cuerpo = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;max-width:560px;">'
            . '<h2 style="color:#007bff;margin-bottom:16px;">' . htmlspecialchars(MAIL_FROM_NAME) . '</h2>'
            . $contenidoHtml
            . '<hr style="border:none;border-top:1px solid #ddd;margin-top:24px;">'
            . '<p style="font-size:12px;color:#888;">Este es un mensaje automático, por favor no lo responda.</p></div>';
    $cabeceras = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . '>',
    ]);

    $enviado = @mail($para, $asuntoCodificado, $cuerpo, $cabeceras, '-f' . MAIL_FROM);
    if (!$enviado) {
        error_log("No se pudo enviar el correo \"$asunto\" a $para");
    }
    return $enviado;
}
