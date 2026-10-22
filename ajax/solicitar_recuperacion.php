<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/app.php';
require_once '../config/correo.php';

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'warning', 'message' => 'Por favor ingrese un correo electrónico válido.']);
    exit();
}

// Mensaje único para no revelar si el correo existe (previene escaneo de correos)
$mensajeGenerico = 'Si el correo está registrado, recibirá un enlace para restablecer su contraseña. Revise también la carpeta de spam.';
$respuesta = ['status' => 'success', 'message' => $mensajeGenerico];

// Buscar el usuario por email
$stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios_clinicas WHERE email = ? AND estado = 1 LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Generar token único y establecer expiración a 1 hora.
    // En BD solo se guarda el hash: si la tabla se filtra, los enlaces no sirven.
    $token = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $update = $pdo->prepare("UPDATE usuarios_clinicas SET token_recuperacion = ?, expira_recuperacion = ? WHERE id = ?");
    $update->execute([hash('sha256', $token), $expira, $user['id']]);

    $enlace = urlBaseApp() . '/restablecer_password.php?token=' . $token;

    // Correo HTML
    $nombre = htmlspecialchars($user['nombre'], ENT_QUOTES, 'UTF-8');
    $enlaceHtml = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
    $cuerpo = "
        <p>Hola <strong>{$nombre}</strong>,</p>
        <p>Recibimos una solicitud para restablecer la contraseña de su cuenta.</p>
        <p><a href=\"{$enlaceHtml}\" style=\"background:#007bff;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;\">Restablecer contraseña</a></p>
        <p>O copie este enlace en su navegador:<br>{$enlaceHtml}</p>
        <p>El enlace vence en 1 hora. Si usted no hizo esta solicitud, ignore este mensaje.</p>
    ";
    enviarCorreo($user['email'], 'Recuperación de contraseña', $cuerpo);

    // Solo en desarrollo local (XAMPP no envía correos) se muestra el enlace en pantalla
    if (esPeticionLocal()) {
        $respuesta['dev_link'] = $enlace;
    }
}

echo json_encode($respuesta);
