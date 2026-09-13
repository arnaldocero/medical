<?php 
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { header("Location: login.php"); exit(); }

require_once 'config/db.php';
$clinica_id = intval($_SESSION['clinica']);

// Consulta avanzada para listar cabeceras de facturas con nombres de pacientes y EPS
$query = "SELECT f.id, f.numero_factura, f.subtotal, f.total, f.metodo_pago, f.estado, f.fecha_emision,
                 u.nombre AS nombre_paciente,
                 e.nombre AS nombre_eps
          FROM facturas f
          INNER JOIN usuarios_clinicas u ON f.paciente_id = u.id
          LEFT JOIN contratos c ON f.contrato_id = c.id
          LEFT JOIN eps e ON c.eps_id = e.id
          WHERE f.clinica_id = ?
          ORDER BY f.fecha_emision DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$clinica_id]);
$facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-history"></i> Historial de Facturación</h1>
        </div>
    </section>

    <section class="content">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title">Facturas Emitidas</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped" id="tablaHistorial">
                    <thead>
                        <tr>
                            <th>Nº Factura</th>
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>Cobertura</th>
                            <th>Método Pago</th>
                            <th>Total</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($facturas)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No se han registrado facturas en esta clínica.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($facturas as $f): ?>
                                <tr>
                                    <td class="text-bold text-navy"><?= $f['numero_factura'] ?></td>
                                    <td><?= date('d/m/Y g:i A', strtotime($f['fecha_emision'])) ?></td>
                                    <td><?= htmlspecialchars($f['nombre_paciente']) ?></td>
                                    <td><?= $f['nombre_eps'] ? htmlspecialchars($f['nombre_eps']) : '<span class="badge badge-secondary">Particular</span>' ?></td>
                                    <td><span class="badge badge-light"><?= $f['metodo_pago'] ?></span></td>
                                    <td class="text-bold text-success">$ <?= number_format($f['total'], 2, ',', '.') ?></td>
                                    <td class="text-center">
                                        <!-- Botón Ver en Línea -->
                                        <button type="button" class="btn btn-info btn-sm btnVerDetalles" data-id="<?= $f['id'] ?>" title="Ver en línea">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <!-- Botón Imprimir / PDF -->
                                        <a href="generar_pdf.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-danger btn-sm" title="Generar PDF / Imprimir">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- VENTANA MODAL PARA VISUALIZACIÓN EN LÍNEA -->
<div class="modal fade" id="modalDetalleFactura" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title" id="modalFacturaTitulo">Detalles de Factura</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalFacturaCuerpo">
                <!-- El contenido dinámico se inyectará aquí vía AJAX -->
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Cargando auditoría de conceptos...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="0" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
