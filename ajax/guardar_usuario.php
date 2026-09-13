<?php
session_start();
require_once '../config/db.php';
require_once '../config/security.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Obtenemos el ID del usuario si viene en la petición (Modo Edición)
        $idUsuario = !empty($_POST['idUsuario']) ? $_POST['idUsuario'] : null;

        // --- PROTECCIÓN SEGÚN ACCIÓN DE USUARIO ---
        if ($idUsuario) {
            requerirPermisoAJAX('usuarios.editar');
        } else {
            requerirPermisoAJAX('usuarios.crear');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        // Tratamiento de valores opcionales para evitar cadenas vacías o nulas
        $cargo = !empty($_POST['cargo']) ? $_POST['cargo'] : null;
        $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
        $genero = !empty($_POST['genero']) ? $_POST['genero'] : null;
        $tipo_doc = !empty($_POST['tipo_doc']) ? $_POST['tipo_doc'] : null;
        $documento = !empty($_POST['documento']) ? $_POST['documento'] : null;
        $telefono = !empty($_POST['telefono']) ? $_POST['telefono'] : null;
        $direccion = !empty($_POST['direccion']) ? $_POST['direccion'] : null;

        if ($idUsuario) {
            // --- MODO EDICIÓN (UPDATE) ---

            // 1. Actualizar datos esenciales de autenticación
            $sql1 = "UPDATE usuarios_clinicas SET rol_id = ?, nombre = ?, usuario = ?, email = ? WHERE id = ?";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([$_POST['rol_id'], $_POST['nombre'], $_POST['usuario'], $_POST['email'], $idUsuario]);

            // 2. Password opcional (solo se actualiza si el usuario la escribió)
            if (!empty($_POST['password'])) {
                $passHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sqlPass = "UPDATE usuarios_clinicas SET password = ? WHERE id = ?";
                $pdo->prepare($sqlPass)->execute([$passHash, $idUsuario]);
            }

            // 3. Actualización robusta del Perfil (¡Aquí estaba la falla!)
            // Verificamos si ya existe una fila en 'perfiles_usuarios' para este ID
            $checkPerfil = $pdo->prepare("SELECT COUNT(*) FROM perfiles_usuarios WHERE usuario_id = ?");
            $checkPerfil->execute([$idUsuario]);
            $perfilExiste = $checkPerfil->fetchColumn() > 0;

            if ($perfilExiste) {
                // Si existe, actualizamos los datos existentes
                $sql2 = "UPDATE perfiles_usuarios 
                         SET tipo_documento = ?, documento_identidad = ?, telefono = ?, direccion = ?, cargo = ?, fecha_nacimiento = ?, genero = ? 
                         WHERE usuario_id = ?";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([$tipo_doc, $documento, $telefono, $direccion, $cargo, $fecha_nacimiento, $genero, $idUsuario]);
            } else {
                // Si por alguna razón la fila no existía en la base de datos, la creamos
                $sql2 = "INSERT INTO perfiles_usuarios (usuario_id, tipo_documento, documento_identidad, telefono, direccion, cargo, fecha_nacimiento, genero) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([$idUsuario, $tipo_doc, $documento, $telefono, $direccion, $cargo, $fecha_nacimiento, $genero]);
            }

            $mensaje = 'Registro modificado exitosamente';

        } else {
            // --- MODO NUEVO (INSERT) ---
            $passHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $sql1 = "INSERT INTO usuarios_clinicas (clinica_id, rol_id, nombre, usuario, email, password, estado, fecha_creacion) 
                     VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([$_SESSION['clinica'], $_POST['rol_id'], $_POST['nombre'], $_POST['usuario'], $_POST['email'], $passHash]);

            $idUsuarioCreado = $pdo->lastInsertId();

            // Insertar datos del perfil
            $sql2 = "INSERT INTO perfiles_usuarios (usuario_id, tipo_documento, documento_identidad, telefono, direccion, cargo, fecha_nacimiento, genero) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$idUsuarioCreado, $tipo_doc, $documento, $telefono, $direccion, $cargo, $fecha_nacimiento, $genero]);

            $mensaje = 'Registro guardado exitosamente';
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => $mensaje]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Error en el servidor: ' . $e->getMessage()]);
    }
}