<?php 
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { header("Location: login.php"); exit(); }

require_once 'config/db.php';
$clinica_id = intval($_SESSION['clinica']);

// Precarga de Pacientes de la clínica filtrando estructuralmente por rol_id = 5
$stmtPacientes = $pdo->prepare("SELECT id, nombre FROM usuarios_clinicas WHERE clinica_id = ? AND rol_id = 5 AND estado = 1 ORDER BY nombre ASC");
$stmtPacientes->execute([$clinica_id]);
$pacientes = $stmtPacientes->fetchAll(PDO::FETCH_ASSOC);

// Precarga de Servicios / CUPS disponibles
$stmtServicios = $pdo->prepare("SELECT id, codigo_cups, nombre_servicio FROM servicios WHERE clinica_id = ? AND estado = 1 ORDER BY codigo_cups ASC");
$stmtServicios->execute([$clinica_id]);
$servicios = $stmtServicios->fetchAll(PDO::FETCH_ASSOC);

// NUEVO: Buscar la resolución DIAN activa para la clínica
$stmtDian = $pdo->prepare("
    SELECT id, numero_resolucion, prefijo, consecutivo_actual, numero_final, fecha_vencimiento 
    FROM dian_resoluciones 
    WHERE clinica_id = ? AND estado = 'activa' 
    LIMIT 1
");
$stmtDian->execute([$clinica_id]);
$resolucionActiva = $stmtDian->fetch(PDO::FETCH_ASSOC);

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<!-- ENLACES DE SELECT2 INSERTADOS DIRECTAMENTE PARA EVITAR CAÍDAS DEL SCRIPT -->
<link rel="stylesheet" href="plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-calculator"></i> Nueva Factura de Servicios Médicos</h1>
        </div>
    </section>

    <section class="content">
        <div class="row">
            <!-- BLOQUE IZQUIERDO: SELECCIÓN DE ENTORNO Y DATOS DIAN -->
            <div class="col-md-4">
                
                <!-- NUEVO SUB-BLOQUE: INFORMACIÓN DE LA RESOLUCIÓN DIAN -->
                <div class="card card-outline card-navy">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-file-invoice"></i> Control DIAN Activo</h3></div>
                    <div class="card-body p-2">
                        <?php if ($resolucionActiva): ?>
                            <input type="hidden" id="dian_resolucion_id" value="<?= $resolucionActiva['id'] ?>">
                            <table class="table table-sm table-noborder mb-0" style="font-size: 0.9rem;">
                                <tr><td><strong>Resolución:</strong></td><td><?= htmlspecialchars($resolucionActiva['numero_resolucion']) ?></td></tr>
                                <tr><td><strong>Prefijo - Próximo:</strong></td><td><span class="badge badge-info"><?= htmlspecialchars($resolucionActiva['prefijo'] ?? 'Ninguno') ?> - <?= $resolucionActiva['consecutivo_actual'] + 1 ?></span></td></tr>
                                <tr><td><strong>Límite:</strong></td><td>Hasta No. <?= $resolucionActiva['numero_final'] ?></td></tr>
                                <tr><td><strong>Vence:</strong></td><td><?= $resolucionActiva['fecha_vencimiento'] ?></td></tr>
                            </table>
                        <?php else: ?>
                            <input type="hidden" id="dian_resolucion_id" value="">
                            <div class="alert alert-danger mb-0 p-2">
                                <i class="fas fa-exclamation-triangle"></i> No hay una resolución DIAN activa configurada para esta clínica. No podrá facturar.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card card-navy">
                    <div class="card-header"><h3 class="card-title">1. Datos del Cliente</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Paciente *</label>
                            <select id="paciente_id" class="form-control select2" style="width:100%;">
                                <option value="">-- Seleccione el paciente --</option>
                                <?php foreach($pacientes as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Convenio / Contrato Aplicado</label>
                            <input type="hidden" id="contrato_id" value="">
                            <input type="hidden" id="manual_id" value="">
                            <input type="text" id="txtContratoInfo" class="form-control" readonly placeholder="Particular (Venta Directa)">
                        </div>
                        <div class="form-group">
                            <label>Método de Pago</label>
                            <select id="metodo_pago" class="form-control">
                                <option value="EFECTIVO">Efectivo</option>
                                <option value="TARJETA">Tarjeta Débito / Crédito</option>
                                <option value="CREDITO_EPS">Garantía / Crédito EPS</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BLOQUE DERECHO: LIQUIDACIÓN DE ÍTEMS -->
            <div class="col-md-8">
                <div class="card card-success">
                    <div class="card-header"><h3 class="card-title">2. Agregar Procedimientos (CUPS)</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-7 form-group">
                                <label>Servicio / CUPS</label>
                                <select id="servicio_id" class="form-control select2" style="width:100%;">
                                    <option value="">-- Busque el código o nombre del procedimiento --</option>
                                    <?php foreach($servicios as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= $s['codigo_cups'] ?> - <?= htmlspecialchars($s['nombre_servicio']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 form-group">
                                <label>Cantidad</label>
                                <input type="number" id="txtCantidad" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>&nbsp;</label>
                                <button type="button" id="btnAgregarItem" class="btn btn-success btn-block"><i class="fas fa-plus"></i> Añadir</button>
                            </div>
                        </div>

                        <table id="tablaConceptos" class="table table-bordered mt-3">
                            <thead class="bg-light">
                                <tr>
                                    <th>Concepto</th>
                                    <th style="width:15%;" class="text-center">Cantidad</th>
                                    <th style="width:20%;">V. Unitario</th>
                                    <th style="width:20%;">V. Total</th>
                                    <th style="width:10%;" class="text-center">Quitar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="5" class="text-center text-muted">Ningún procedimiento añadido a esta orden</td></tr>
                            </tbody>
                        </table>

                        <div class="row mt-4">
                            <div class="col-md-6 offset-md-6 text-right">
                                <h3>Total Factura: <span class="text-success text-bold" id="lblTotalFactura">$0.00</span></h3>
                                <hr>
                                <button type="button" id="btnProcesarFactura" class="btn btn-primary btn-lg btn-block"><i class="fas fa-save"></i> Generar y Guardar Factura</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>