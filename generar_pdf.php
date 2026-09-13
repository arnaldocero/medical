<?php
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { header("Location: login.php"); exit(); }

require_once 'config/db.php';
$factura_id = intval($_GET['id'] ?? 0);
$clinica_id = intval($_SESSION['clinica']);

// Validar que la factura exista, pertenezca a la clínica de la sesión activa y extraer datos médicos del paciente
$stmtF = $pdo->prepare("SELECT 
                            f.*, 
                            u.nombre AS paciente, 
                            pd.documento_identidad, 
                            e.nombre AS eps,
                            pd.fecha_nacimiento,
                            pd.genero,
                            pd.tipo_sangre,
                            pd.documento_identidad,
                            pd.telefono_contacto,
                            pd.direccion,
                            pd.contacto_emergencia_nombre,
                            pd.contacto_emergencia_telefono
                        FROM facturas f
                        INNER JOIN usuarios_clinicas u ON f.paciente_id = u.id
                        LEFT JOIN pacientes_datos pd ON u.id = pd.usuario_id
                        LEFT JOIN contratos c ON f.contrato_id = c.id
                        LEFT JOIN eps e ON c.eps_id = e.id
                        WHERE f.id = ? AND f.clinica_id = ?");
$stmtF->execute([$factura_id, $clinica_id]);
$factura = $stmtF->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
    die("Error: Comprobante no válido o inexistente.");
}

// Obtener detalles de procedimientos
$stmtD = $pdo->prepare("SELECT fd.*, s.codigo_cups, s.nombre_servicio 
                        FROM factura_detalles fd
                        INNER JOIN servicios s ON fd.servicio_id = s.id
                        WHERE fd.factura_id = ?");
$stmtD->execute([$factura_id]);
$detalles = $stmtD->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante_<?= $factura['numero_factura'] ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.4; padding: 20px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 10px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { padding: 5px; vertical-align: top; }
        .title { font-size: 26px; font-bold: true; color: #1f385c; text-transform: uppercase; }
        .info-block { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: #f8f9fa; }
        .info-block td { padding: 10px; font-size: 14px; border: 1px solid #dee2e6; }
        .items-table { width: 100%; border-collapse: collapse; text-align: left; margin-bottom: 20px; }
        .items-table th { background: #1f385c; color: white; padding: 8px; font-size: 13px; text-transform: uppercase; }
        .items-table td { padding: 8px; border-bottom: 1px solid #eee; font-size: 13px; }
        .total-row { text-align: right; font-weight: bold; font-size: 16px; color: #28a745; }
        
        /* Directiva de Impresión: Fuerza la salida limpia hacia el PDF */
        @media print {
            body { padding: 0; }
            .invoice-box { border: none; box-shadow: none; }
            .btn-print { display: none; }
        }
        .btn-print { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-bottom: 15px; font-weight: bold;}
    </style>
</head>
<body>

    <div style="text-align: center;">
        <a href="#" onclick="window.print(); return false;" class="btn-print"><i class="fas fa-print"></i> Imprimir / Guardar como PDF</a>
    </div>

    <div class="invoice-box">
        <table class="header-table">
            <tr>
                <td>
                    <span class="title">RIPS & FACTURACIÓN</span><br>
                    Cuidado Médico Profesional<br>
                    Clínica ID: <?= $clinica_id ?>
                </td>
                <td style="text-align: right;">
                    <strong style="font-size: 18px; color: #1f385c;">COMPROBANTE DE FACTURA</strong><br>
                    <strong>Radicado:</strong> <?= $factura['numero_factura'] ?><br>
                    <strong>Fecha:</strong> <?= date('d/m/Y', strtotime($factura['fecha_emision'])) ?>
                </td>
            </tr>
        </table>

        <table class="info-block">
            <tr>
                <td>
                    <strong>Datos del Paciente:</strong><br>
                    Nombre: <?= htmlspecialchars($factura['paciente']) ?><br>
                    Identificación: <?= htmlspecialchars($factura['cedula'] ?? 'No registrada') ?>
                </td>
                <td>
                    <strong>Entidad Responsable:</strong><br>
                    Entidad / EPS: <?= $factura['eps'] ? htmlspecialchars($factura['eps']) : 'Particular (Venta Directa)' ?><br>
                    Método de Pago: <?= $factura['metodo_pago'] ?>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Código CUPS</th>
                    <th style="width: 50%;">Descripción del Servicio</th>
                    <th style="text-align: center; width: 10%;">Cant.</th>
                    <th style="text-align: right; width: 12%;">Valor U.</th>
                    <th style="text-align: right; width: 13%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $item): ?>
                    <tr>
                        <td><code><?= $item['codigo_cups'] ?></code></td>
                        <td><?= htmlspecialchars($item['nombre_servicio']) ?></td>
                        <td style="text-align: center;"><?= $item['cantidad'] ?></td>
                        <td style="text-align: right;">$<?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                        <td style="text-align: right;">$<?= number_format($item['valor_total'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3"></td>
                    <td style="text-align: right; font-weight: bold; padding-top: 15px;">TOTAL:</td>
                    <td class="total-row" style="padding-top: 15px;">$<?= number_format($factura['total'], 2, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 50px; font-size: 11px; text-align: center; color: #777;">
            Este documento es una representación impresa de los servicios liquidados en el sistema médico institucional.<br>
            Gracias por su confianza.
        </div>
    </div>

    <!-- Lanzar diálogo de impresión automáticamente al cargar la página -->
    <script>
        window.onload = function() {
            // Descomenta la línea de abajo si quieres que se abra el cuadro automáticamente
            // window.print();
        }
    </script>
</body>
</html>