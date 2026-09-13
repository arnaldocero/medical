<?php
session_start();

// 1. Validar seguridad de sesión
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada o no válida.']);
    exit();
}

require_once '../config/db.php';

$action = $_REQUEST['action'] ?? '';

// --- ACCIÓN 1: PROCESAR Y SUBIR EL ARCHIVO ---
if ($action === 'subir_adjunto') {
    $paciente_id = $_POST['paciente_id'] ?? '';
    $historia_id = !empty($_POST['historia_id']) ? $_POST['historia_id'] : null; // Puede ser opcional
    $nombre_personalizado = trim($_POST['nombre_personalizado'] ?? '');
    $tipo_archivo = $_POST['tipo_archivo'] ?? 'Otros';
    $medico_id = $_SESSION['id']; // El usuario médico que está logueado

    // Validaciones básicas de texto
    if (empty($paciente_id) || empty($nombre_personalizado)) {
        echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios (Paciente o Nombre del documento).']);
        exit();
    }

    // Validar si efectivamente se envió un archivo en el formulario
    if (!isset($_FILES['archivo_adjunto']) || $_FILES['archivo_adjunto']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No se ha seleccionado ningún archivo válido o hubo un problema en la carga.']);
        exit();
    }

    $file = $_FILES['archivo_adjunto'];
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_tmp  = $file['tmp_name'];

    // Obtener la extensión real del archivo de forma segura
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // REGLA DE SEGURIDAD 1: Extensiones estrictamente permitidas (Salud estándar)
    $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $extensiones_permitidas)) {
        echo json_encode(['status' => 'error', 'message' => 'Formato no permitido. Solo se aceptan archivos PDF, JPG, JPEG o PNG.']);
        exit();
    }

    // REGLA DE SEGURIDAD 2: Peso máximo (Ejemplo: 5 Megabytes máximo por documento)
    $max_size = 5 * 1024 * 1024; // 5MB en Bytes
    if ($file_size > $max_size) {
        echo json_encode(['status' => 'error', 'message' => 'El archivo es demasiado grande. El peso máximo permitido es de 5 MB.']);
        exit();
    }

    // REGLA DE SEGURIDAD 3: Generar un nombre único aleatorio para que no se sobrescriban
    // Ejemplo de salida: PAC_5_20260601_665b1c3a2f1a4.pdf
    $nuevo_nombre_archivo = "PAC_" . $paciente_id . "_" . date('Ymd_His') . "_" . uniqid() . "." . $ext;
    
    // Ruta física relativa donde se moverá el archivo en el servidor
    $carpeta_destino = "../uploads/atenciones/";
    $ruta_final_archivo = $carpeta_destino . $nuevo_nombre_archivo;

    // Ruta simplificada que guardaremos en la BD para cargarla después en las vistas
    $ruta_para_bd = "uploads/atenciones/" . $nuevo_nombre_archivo;

    // Intentar mover el archivo temporal a la carpeta destino
    if (move_uploaded_file($file_tmp, $ruta_final_archivo)) {
        try {
            // Insertar el registro en la base de datos
            $stmt = $pdo->prepare("
                INSERT INTO pacientes_adjuntos 
                (paciente_id, medico_id, historia_id, nombre_personalizado, tipo_archivo, ruta_archivo) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $paciente_id, 
                $medico_id, 
                $historia_id, 
                $nombre_personalizado, 
                $tipo_archivo, 
                $ruta_para_bd
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Documento adjuntado y guardado correctamente.']);
            exit();

        } catch (PDOException $e) {
            // Si la BD falla, borramos el archivo físico recién subido para no dejar basura en el servidor
            if (file_exists($ruta_final_archivo)) {
                unlink($ruta_final_archivo);
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar en Base de Datos: ' . $e->getMessage()]);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error crítico al mover el archivo al directorio del servidor. Verifique permisos de escritura.']);
        exit();
    }
}

// --- ACCIÓN 2: LISTAR LOS ADJUNTOS DE UN PACIENTE ---
if ($action === 'listar_adjuntos') {
    $paciente_id = $_GET['paciente_id'] ?? '';

    if (empty($paciente_id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID de paciente requerido.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            SELECT pa.*, uc.nombre AS medico_nombre 
            FROM pacientes_adjuntos pa
            INNER JOIN usuarios_clinicas uc ON pa.medico_id = uc.id
            WHERE pa.paciente_id = ? 
            ORDER BY pa.fecha_registro DESC
        ");
        $stmt->execute([$paciente_id]);
        $adjuntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $adjuntos]);
        exit();

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// --- ACCIÓN 3: ELIMINAR UN ADJUNTO ---
if ($action === 'eliminar_adjunto') {
    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID de registro requerido.']);
        exit();
    }

    try {
        // Primero consultamos la ruta del archivo para poder borrarlo del disco duro
        $stmt = $pdo->prepare("SELECT ruta_archivo FROM pacientes_adjuntos WHERE id = ?");
        $stmt->execute([$id]);
        $archivo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($archivo) {
            $ruta_fisica = "../" . $archivo['ruta_archivo'];
            
            // Borrar el archivo físico del servidor si existe
            if (file_exists($ruta_fisica)) {
                unlink($ruta_fisica);
            }

            // Eliminar el registro de la Base de Datos
            $stmt_del = $pdo->prepare("DELETE FROM pacientes_adjuntos WHERE id = ?");
            $stmt_del->execute([$id]);

            echo json_encode(['status' => 'success', 'message' => 'Archivo eliminado correctamente.']);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'El registro no existe.']);
            exit();
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}