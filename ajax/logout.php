<?php
// 1. Iniciar el entorno de sesión para poder destruirla
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// 2. Invalidar el "Recordarme" (token en BD y cookie en el navegador)
borrarTokenRecordarme($pdo, $_SESSION['id'] ?? null);

// 3. Limpiar el array global de sesión en memoria
$_SESSION = array();

// 4. Destruir la cookie de sesión en el navegador del usuario
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 5. Destruir la sesión por completo en el almacenamiento del servidor
session_destroy();

// 6. REDIRECCIÓN RELATIVA: sube un nivel desde /ajax/ hasta login.php,
// funciona igual en /Medical (local) que en /HMS (servidor) o en la raíz del dominio.
header("Location: ../login.php");
exit();
