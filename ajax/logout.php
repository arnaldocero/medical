<?php
// 1. Iniciar el entorno de sesión para poder destruirla
session_start();

// 2. Limpiar el array global de sesión en memoria
$_SESSION = array();

// 3. Destruir la cookie de sesión en el navegador del usuario
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

// 4. Destruir la sesión por completo en el almacenamiento del servidor
session_destroy();

// 5. REDIRECCIÓN ABSOLUTA CORREGIDA:
// Forzamos al navegador a ir a la raíz de la aplicación, ignorando que estamos dentro de /ajax/
header("Location: /HMS/login.php");
exit();