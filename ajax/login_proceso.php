<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';
// Motor de seguridad + utilidades de sesión / "Recordarme"
require_once '../config/auth.php';
// Límite de intentos (3 fallos = bloqueo 30 min + correo de alerta)
require_once '../config/login_seguridad.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userOrEmail = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $recordarme = !empty($_POST['recordarme']);

    // Token CSRF del formulario de login
    if (!csrfValido()) {
        echo json_encode(['status' => 'error', 'message' => 'La página expiró. Recárguela (F5) e intente de nuevo.']);
        exit();
    }

    if ($userOrEmail === '' || $password === '') {
        echo json_encode(['status' => 'error', 'message' => 'Llene todos los campos.']);
        exit();
    }

    // Límite por IP: frena a quien prueba muchos usuarios distintos desde el mismo equipo
    if (ipBloqueada($pdo)) {
        echo json_encode(['status' => 'error', 'message' => 'Demasiados intentos fallidos desde esta conexión. Espere ' . LOGIN_MINUTOS_BLOQUEO . ' minutos e intente de nuevo.']);
        exit();
    }

    // Buscamos por usuario o email y que esté ACTIVO (estado = 1)
    $stmt = $pdo->prepare("SELECT * FROM usuarios_clinicas WHERE (usuario = ? OR email = ?) AND estado = 1 LIMIT 1");
    $stmt->execute([$userOrEmail, $userOrEmail]);
    $usuario = $stmt->fetch();

    // Cuenta bloqueada: no se verifica la contraseña hasta que termine el bloqueo
    if ($usuario && ($bloqueo = estadoBloqueoCuenta($pdo, $usuario['id']))) {
        registrarIntentoLogin($pdo, $usuario['id'], $userOrEmail, false, 'cuenta_bloqueada');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Cuenta bloqueada por intentos fallidos. Podrá intentar de nuevo en ' . $bloqueo['minutos']
                       . ' minuto(s) (' . $bloqueo['hasta'] . '), o restablecer su contraseña con "Olvidé mi contraseña".'
        ]);
        exit();
    }

    if ($usuario && password_verify($password, $usuario['password'])) {
        limpiarFallosCuenta($pdo, $usuario['id']);
        registrarIntentoLogin($pdo, $usuario['id'], $userOrEmail, true);

        // Creamos la sesión y cargamos los permisos del rol
        iniciarSesionUsuario($pdo, $usuario);

        // SEDES: el personal sin sedes asignadas no puede entrar (el Admin siempre puede)
        $sedes = obtenerSedesUsuario($pdo);
        if (!esAdmin() && count($sedes) === 0) {
            $_SESSION = [];
            echo json_encode(['status' => 'error', 'message' => 'Su usuario no tiene sedes asignadas. Contacte al administrador.']);
            exit();
        }

        // LÓGICA DE RECORDARME: cookie de 30 días con token rotativo
        if ($recordarme) {
            crearTokenRecordarme($pdo, $usuario['id']);
        } else {
            borrarTokenRecordarme($pdo, $usuario['id']);
        }

        // Una sola sede: entra directo. Varias (o Admin): paso 2, elegir sede
        $redirigir = autoseleccionarSede($pdo) ? 'index.php' : 'seleccionar_sede.php';

        echo json_encode(['status' => 'success', 'message' => 'Acceso concedido', 'redirect' => $redirigir]);
        exit();
    }

    // Credenciales incorrectas
    if ($usuario) {
        registrarIntentoLogin($pdo, $usuario['id'], $userOrEmail, false, 'clave_incorrecta');
        if (registrarFalloCuenta($pdo, $usuario)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Superó los ' . LOGIN_MAX_INTENTOS . ' intentos permitidos. Su cuenta quedó bloqueada '
                           . LOGIN_MINUTOS_BLOQUEO . ' minutos y se envió un aviso a su correo.'
            ]);
            exit();
        }
    } else {
        registrarIntentoLogin($pdo, null, $userOrEmail, false, 'usuario_inexistente');
    }

    // Mismo mensaje exista o no el usuario (no revela qué cuentas existen)
    echo json_encode([
        'status'  => 'error',
        'message' => 'Credenciales incorrectas o usuario inactivo. Después de ' . LOGIN_MAX_INTENTOS
                   . ' intentos fallidos la cuenta se bloquea ' . LOGIN_MINUTOS_BLOQUEO . ' minutos.'
    ]);
}
