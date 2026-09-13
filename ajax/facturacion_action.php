<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(["status" => "error", "message" => "Acceso denegado."]);
    exit();
}

require_once '../config/db.php';
$action = $_POST['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']);

if (ob_get_length()) ob_clean();

try {
    // --- ACCIÓN 1: DETECTAR CONTRATO Y MANUAL DEL PACIENTE ---
    if ($action === 'obtener_cobertura_paciente') {
        $paciente_id = intval($_POST['paciente_id'] ?? 0);
        
        // Buscamos el paciente, su EPS cruzando de manera segura mediante la tabla relacional pacientes_datos
        $query = "SELECT c.id AS contrato_id, c.numero_contrato, m.id AS manual_id, m.nombre_manual, e.nombre AS nombre_eps
                  FROM usuarios_clinicas u
                  INNER JOIN pacientes_datos pd ON pd.usuario_id = u.id
                  INNER JOIN eps e ON pd.eps_id = e.id
                  INNER JOIN contratos c ON c.eps_id = e.id 
                    AND c.estado = 1 
                    AND NOW() BETWEEN c.fecha_inicio AND c.fecha_fin
                  INNER JOIN manuales m ON c.manual_id = m.id
                  WHERE u.id = ? 
                    AND c.clinica_id = ? 
                  LIMIT 1";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([$paciente_id, $clinica_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            echo json_encode(["status" => "success", "data" => $resultado]);
        } else {
            echo json_encode(["status" => "particular", "message" => "Paciente sin contrato EPS vigente. Se liquidará como Particular."]);
        }
        exit();
    }

    // --- ACCIÓN 2: OBTENER PRECIO CONFIGURADO DEL CUPS ---
    if ($action === 'obtener_precio_servicio') {
        $servicio_id = intval($_POST['servicio_id'] ?? 0);
        $manual_id = intval($_POST['manual_id'] ?? 0);

        if ($manual_id > 0) {
            // Buscar precio en el manual asignado al contrato de la EPS
            $stmt = $pdo->prepare("SELECT valor FROM manual_tarifas WHERE manual_id = ? AND servicio_id = ?");
            $stmt->execute([$manual_id, $servicio_id]);
            $tarifa = $stmt->fetch(PDO::FETCH_ASSOC);
            $precio = $tarifa ? floatval($tarifa['valor']) : 0.00;
        } else {
            // NOTA: Si es particular, buscamos el costo base directo de tu tabla servicios.
            // (Si tu columna de precio en la tabla servicios se llama distinto, p. ej. 'precio', cámbiala aquí)
            $stmt = $pdo->prepare("SELECT precio_base FROM servicios WHERE id = ? AND clinica_id = ?");
            $stmt->execute([$servicio_id, $clinica_id]);
            $tarifaBase = $stmt->fetch(PDO::FETCH_ASSOC);
            $precio = $tarifaBase ? floatval($tarifaBase['precio_base']) : 0.00; 
        }

        echo json_encode(["status" => "success", "precio" => $precio]);
        exit();
    }

    
    // --- ACCIÓN 3: PROCESAR / GUARDAR FACTURA COMPLETA ---
    if ($action === 'guardar_factura') {
        $paciente_id = intval($_POST['paciente_id'] ?? 0);
        $dian_resolucion_id = intval($_POST['dian_resolucion_id'] ?? 0); // Recibimos el ID enviado desde el JS
        $contrato_id = !empty($_POST['contrato_id']) ? intval($_POST['contrato_id']) : null;
        $metodo_pago = trim($_POST['metodo_pago'] ?? 'EFECTIVO');
        $detalles = $_POST['detalles'] ?? []; 

        if ($paciente_id === 0 || empty($detalles)) {
            echo json_encode(["status" => "warning", "message" => "Datos de facturación incompletos (Falta paciente o servicios)."]);
            exit();
        }

        if ($dian_resolucion_id === 0) {
            echo json_encode(["status" => "error", "message" => "No se especificó una resolución DIAN válida para emitir la factura."]);
            exit();
        }

        try {
            // 1. Iniciamos la transacción atómica de manera inmediata
            $pdo->beginTransaction();

            // 2. BLOQUEO SEGURO (FOR UPDATE): Consultamos y bloqueamos la resolución elegida
            // Esto evita que dos cajeros tomen el mismo consecutivo al mismo milisegundo.
            $stmtDian = $pdo->prepare("
                SELECT id, prefijo, consecutivo_actual, numero_final, fecha_vencimiento, estado 
                FROM dian_resoluciones 
                WHERE id = ? AND clinica_id = ? 
                FOR UPDATE
            ");
            $stmtDian->execute([$dian_resolucion_id, $clinica_id]);
            $resolucion = $stmtDian->fetch(PDO::FETCH_ASSOC);

            // Validaciones estrictas de la resolución DIAN antes de quemar folios
            if (!$resolucion) {
                throw new Exception("La resolución DIAN seleccionada no existe o no pertenece a esta clínica.");
            }
            if ($resolucion['estado'] !== 'activa') {
                throw new Exception("La resolución seleccionada ya no está activa (Estado actual: " . $resolucion['estado'] . ").");
            }
            if (date('Y-m-d') > $resolucion['fecha_vencimiento']) {
                throw new Exception("La resolución DIAN está vencida desde el " . $resolucion['fecha_vencimiento']);
            }

            // Calcular el número que le corresponde a esta venta
            $nuevo_consecutivo = intval($resolucion['consecutivo_actual']) + 1;

            if ($nuevo_consecutivo > intval($resolucion['numero_final'])) {
                throw new Exception("Rango de numeración agotado. Límite máximo autorizado: " . $resolucion['numero_final']);
            }

            // Construcción del número legal (Ej: "SETT-1001" o "FAC-1" si no lleva prefijo)
            $prefijo = !empty($resolucion['prefijo']) ? $resolucion['prefijo'] . "-" : "";
            $numero_factura = $prefijo . $nuevo_consecutivo;


            // 3. Recalcular totales en backend (Capa de Seguridad indispensable)
            $subtotal = 0.00;
            foreach ($detalles as $item) {
                $subtotal += intval($item['cantidad']) * floatval($item['precio']);
            }
            $total = $subtotal; 


            // 4. Insertar Cabecera de Factura (Guardando la relación a la resolución que la generó)
            // Agregamos la columna 'dian_resolucion_id' a la factura para trazabilidad y auditorías futuras
            $queryFactura = "INSERT INTO facturas (clinica_id, dian_resolucion_id, paciente_id, contrato_id, numero_factura, subtotal, total, metodo_pago, estado, fecha_emision) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'EMITIDA', NOW())";
            $stmtFactura = $pdo->prepare($queryFactura);
            $stmtFactura->execute([$clinica_id, $dian_resolucion_id, $paciente_id, $contrato_id, $numero_factura, $subtotal, $total, $metodo_pago]);
            $factura_id = $pdo->lastInsertId();


            // 5. Insertar Detalle de Factura
            $queryDetalle = "INSERT INTO factura_detalles (factura_id, servicio_id, cantidad, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?)";
            $stmtDetalle = $pdo->prepare($queryDetalle);

            foreach ($detalles as $item) {
                $s_id = intval($item['servicio_id']);
                $cant = intval($item['cantidad']);
                $v_uni = floatval($item['precio']);
                $v_tot = $cant * $v_uni;
                
                $stmtDetalle->execute([$factura_id, $s_id, $cant, $v_uni, $v_tot]);
            }


            // 6. ACTUALIZAR RESOLUCIÓN DIAN: Avanzar el consecutivo actual en la base de datos
            // Si el nuevo consecutivo llega al límite exacto, la marcamos de una vez como 'agotada'
            $nuevo_estado = ($nuevo_consecutivo === intval($resolucion['numero_final'])) ? 'agotada' : 'activa';
            
            $stmtUpdateDian = $pdo->prepare("
                UPDATE dian_resoluciones 
                SET consecutivo_actual = ?, estado = ? 
                WHERE id = ?
            ");
            $stmtUpdateDian->execute([$nuevo_consecutivo, $nuevo_estado, $dian_resolucion_id]);


            // Si todo salió bien, guardamos los cambios de forma permanente
            $pdo->commit();

            echo json_encode([
                "status" => "success", 
                "message" => "Factura $numero_factura generada correctamente conforme a la ley.", 
                "factura_id" => $factura_id
            ]);
            exit();

        } catch (Exception $e) {
            // Si algo falla en cualquier punto, revertimos todo y la base de datos queda limpia e intacta
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo procesar el documento: " . $e->getMessage()
            ]);
            exit();
        }
    }
    // --- NUEVA ACCIÓN 4: VER DETALLE EN LÍNEA (HTML SEGURO DENTRO DE JSON) ---
    if ($action === 'ver_detalle_linea') {
        $factura_id = intval($_POST['factura_id'] ?? 0);

        // 1. Obtener la cabecera
        $stmtF = $pdo->prepare("SELECT f.*, u.nombre AS paciente, u.email, e.nombre AS eps 
                                FROM facturas f
                                INNER JOIN usuarios_clinicas u ON f.paciente_id = u.id
                                LEFT JOIN contratos c ON f.contrato_id = c.id
                                LEFT JOIN eps e ON c.eps_id = e.id
                                WHERE f.id = ?");
        $stmtF->execute([$factura_id]);
        $factura = $stmtF->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            echo json_encode(["status" => "error", "message" => "Factura no encontrada."]);
            exit();
        }

        // 2. Obtener los ítems
        $stmtD = $pdo->prepare("SELECT fd.*, s.codigo_cups, s.nombre_servicio 
                                FROM factura_detalles fd
                                INNER JOIN servicios s ON fd.servicio_id = s.id
                                WHERE fd.factura_id = ?");
        $stmtD->execute([$factura_id]);
        $detalles = $stmtD->fetchAll(PDO::FETCH_ASSOC);

        // Activamos el almacenamiento en búfer para capturar el diseño
        ob_start();
        ?>
        <div class="row mb-3">
            <div class="col-6">
                <strong>Nº Factura:</strong> <span class="text-navy"><?= $factura['numero_factura'] ?></span><br>
                <strong>Fecha Emisión:</strong> <?= date('d/m/Y h:i A', strtotime($factura['fecha_emision'])) ?><br>
                <strong>Método de Pago:</strong> <?= $factura['metodo_pago'] ?>
            </div>
            <div class="col-6 text-right">
                <strong>Paciente:</strong> <?= htmlspecialchars($factura['paciente']) ?><br>
                <strong>Cobertura:</strong> <?= $factura['eps'] ? htmlspecialchars($factura['eps']) : 'Particular' ?><br>
                <strong>Estado:</strong> <span class="badge badge-success"><?= $factura['estado'] ?></span>
            </div>
        </div>
        
        <table class="table table-sm table-bordered">
            <thead class="bg-light">
                <tr>
                    <th>CUPS / Código</th>
                    <th>Descripción Procedimiento</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-right">V. Unitario</th>
                    <th class="text-right">V. Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $item): ?>
                    <tr>
                        <td><code><?= $item['codigo_cups'] ?></code></td>
                        <td><?= htmlspecialchars($item['nombre_servicio']) ?></td>
                        <td class="text-center"><?= $item['cantidad'] ?></td>
                        <td class="text-right">$ <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                        <td class="text-right">$ <?= number_format($item['valor_total'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-right">Total Liquidado:</th>
                    <th class="text-right text-success">$ <?= number_format($factura['total'], 2, ',', '.') ?></th>
                </tr>
            </tfoot>
        </table>
        <?php
        // Guardamos el contenido del búfer en una variable y lo limpiamos
        $html_output = ob_get_clean();

        // Enviamos la respuesta respetando estrictamente el formato JSON de la cabecera
        echo json_encode([
            "status" => "success",
            "html" => $html_output
        ]);
        exit();
    }

} catch (Exception $e) {
    // CORRECCIÓN: PDO no tiene isActive(). Solo validamos si hay una transacción activa.
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Devolvemos el error real para saber exactamente qué línea falló en la base de datos
    echo json_encode([
        "status" => "error", 
        "message" => "Error crítico en el servidor: " . $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
    exit();
}