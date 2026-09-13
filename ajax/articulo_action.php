<?php
session_start();
header('Content-Type: application/json');

// Validación de seguridad de sesión activa
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada o inválida.']);
    exit();
}

require_once '../config/db.php';
$clinica_id = intval($_SESSION['clinica']); // Forzar conversión a entero por seguridad
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'listar':
        try {
            // Filtrar estrictamente por la clínica autenticada
            $stmt = $pdo->prepare("SELECT * FROM articulos_inventario WHERE clinica_id = ? ORDER BY id_articulo DESC");
            $stmt->execute([$clinica_id]);
            $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['data' => $articulos]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;

    case 'obtener':
        $id = intval($_POST['id_articulo'] ?? 0);
        try {
            // Validación cruzada: id_articulo + clinica_id
            $stmt = $pdo->prepare("SELECT * FROM articulos_inventario WHERE id_articulo = ? AND clinica_id = ?");
            $stmt->execute([$id, $clinica_id]);
            $articulo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($articulo) {
                echo json_encode(['status' => 'success', 'data' => $articulo]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Artículo no encontrado o no pertenece a su clínica.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar':
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $unidad_medida = trim($_POST['unidad_medida'] ?? 'Unidad');
        $stock_actual = intval($_POST['stock_actual'] ?? 0);
        $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
        $precio_compra = floatval($_POST['precio_compra'] ?? 0.00);
        $precio_venta = floatval($_POST['precio_venta'] ?? 0.00);
        $proveedor = trim($_POST['proveedor'] ?? '');
        $fecha_ingreso = $_POST['fecha_ingreso'] ?? date('Y-m-d');
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

        if (empty($codigo) || empty($nombre) || empty($categoria)) {
            echo json_encode(['status' => 'error', 'message' => 'Por favor complete todos los campos obligatorios (*).']);
            exit();
        }

        try {
            // Validar que el código no esté duplicado en la misma clínica
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM articulos_inventario WHERE codigo = ? AND clinica_id = ?");
            $stmtCheck->execute([$codigo, $clinica_id]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'El código ingresado ya está asignado a otro artículo en esta clínica.']);
                exit();
            }

            // Inserción inyectando automáticamente el clinica_id de la sesión
            $sql = "INSERT INTO articulos_inventario (
                clinica_id, codigo, nombre, categoria, descripcion, unidad_medida, 
                stock_actual, stock_minimo, precio_compra, precio_venta, 
                proveedor, fecha_ingreso, fecha_vencimiento
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $clinica_id, $codigo, $nombre, $categoria, $descripcion, $unidad_medida,
                $stock_actual, $stock_minimo, $precio_compra, $precio_venta,
                $proveedor, $fecha_ingreso, $fecha_vencimiento
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Artículo registrado correctamente en el inventario de su clínica.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al insertar: ' . $e->getMessage()]);
        }
        break;

    case 'actualizar':
        $id_articulo = intval($_POST['id_articulo'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $unidad_medida = trim($_POST['unidad_medida'] ?? 'Unidad');
        $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
        $precio_compra = floatval($_POST['precio_compra'] ?? 0.00);
        $precio_venta = floatval($_POST['precio_venta'] ?? 0.00);
        $proveedor = trim($_POST['proveedor'] ?? '');
        $fecha_ingreso = $_POST['fecha_ingreso'] ?? date('Y-m-d');
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

        if ($id_articulo === 0 || empty($codigo) || empty($nombre)) {
            echo json_encode(['status' => 'error', 'message' => 'Datos insuficientes para realizar la actualización.']);
            exit();
        }

        try {
            // Verificar pertenencia del artículo antes de actualizar
            $stmtVerify = $pdo->prepare("SELECT COUNT(*) FROM articulos_inventario WHERE id_articulo = ? AND clinica_id = ?");
            $stmtVerify->execute([$id_articulo, $clinica_id]);
            if ($stmtVerify->fetchColumn() == 0) {
                echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Este artículo no pertenece a su clínica.']);
                exit();
            }

            // Validar duplicados de código dentro de la misma clínica
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM articulos_inventario WHERE codigo = ? AND id_articulo != ? AND clinica_id = ?");
            $stmtCheck->execute([$codigo, $id_articulo, $clinica_id]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'El código de artículo ya se encuentra en uso por otro de sus productos.']);
                exit();
            }

            $sql = "UPDATE articulos_inventario SET 
                codigo = ?, nombre = ?, categoria = ?, descripcion = ?, unidad_medida = ?, 
                stock_minimo = ?, precio_compra = ?, precio_venta = ?, 
                proveedor = ?, fecha_ingreso = ?, fecha_vencimiento = ?
                WHERE id_articulo = ? AND clinica_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $codigo, $nombre, $categoria, $descripcion, $unidad_medida,
                $stock_minimo, $precio_compra, $precio_venta,
                $proveedor, $fecha_ingreso, $fecha_vencimiento, $id_articulo, $clinica_id
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Artículo actualizado correctamente.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id = intval($_POST['id_articulo'] ?? 0);
        try {
            // Validación de pertenencia antes del DELETE
            $stmtVerify = $pdo->prepare("SELECT COUNT(*) FROM articulos_inventario WHERE id_articulo = ? AND clinica_id = ?");
            $stmtVerify->execute([$id, $clinica_id]);
            if ($stmtVerify->fetchColumn() == 0) {
                echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Este artículo no pertenece a su clínica.']);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM articulos_inventario WHERE id_articulo = ? AND clinica_id = ?");
            $stmt->execute([$id, $clinica_id]);
            echo json_encode(['status' => 'success', 'message' => 'Artículo eliminado del almacén con éxito.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'No es posible eliminar este artículo porque posee movimientos asociados en el historial.']);
        }
        break;

    case 'ajustar_stock':
        $id_articulo = intval($_POST['stock_articulo_id'] ?? 0);
        $tipo = trim($_POST['tipo_movimiento'] ?? '');
        $cantidad = intval($_POST['cantidad'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $usuario_id = $_SESSION['id'];

        if ($id_articulo === 0 || empty($tipo) || $cantidad <= 0 || empty($motivo)) {
            echo json_encode(['status' => 'error', 'message' => 'Datos de ajuste de stock incompletos.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Bloquear fila y validar pertenencia estricta por clínica
            $stmtLock = $pdo->prepare("SELECT stock_actual, nombre FROM articulos_inventario WHERE id_articulo = ? AND clinica_id = ? FOR UPDATE");
            $stmtLock->execute([$id_articulo, $clinica_id]);
            $art = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$art) {
                throw new Exception("El artículo seleccionado no existe o no pertenece a su clínica.");
            }

            $nuevo_stock = $art['stock_actual'];

            if ($tipo === 'ENTRADA') {
                $nuevo_stock += $cantidad;
            } elseif ($tipo === 'SALIDA') {
                if ($art['stock_actual'] < $cantidad) {
                    throw new Exception("Existencias insuficientes. No puedes retirar más unidades de las disponibles en bodega.");
                }
                $nuevo_stock -= $cantidad;
            } else {
                throw new Exception("Operación de stock inválida.");
            }

            // Actualizar tabla maestra de forma segura restringiendo por clinica_id
            $stmtUpdate = $pdo->prepare("UPDATE articulos_inventario SET stock_actual = ? WHERE id_articulo = ? AND clinica_id = ?");
            $stmtUpdate->execute([$nuevo_stock, $id_articulo, $clinica_id]);

            // Guardar el clinica_id en el historial de movimientos
            $stmtHistorial = $pdo->prepare("INSERT INTO inventario_movimientos (clinica_id, articulo_id, tipo_movimiento, cantidad, motivo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtHistorial->execute([$clinica_id, $id_articulo, $tipo, $cantidad, $motivo, $usuario_id]);

            $pdo->commit();
            echo json_encode([
                'status' => 'success', 
                'message' => "Stock de '{$art['nombre']}' ajustado correctamente. Nuevo saldo: {$nuevo_stock} unidades."
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no soportada.']);
        break;
}