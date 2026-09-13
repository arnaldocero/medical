<?php 
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { header("Location: login.php"); exit(); }

require_once 'config/db.php';
$clinica_id = intval($_SESSION['clinica']);

// Precarga de EPS de la clínica para el select del formulario modal
$stmtEps = $pdo->prepare("SELECT id, nombre FROM eps WHERE clinica_id = ? AND estado = 1 ORDER BY nombre ASC");
$stmtEps->execute([$clinica_id]);
$listaEps = $stmtEps->fetchAll(PDO::FETCH_ASSOC);

// Precarga de Manuales de la clínica para el select del formulario modal
$stmtManuales = $pdo->prepare("SELECT id, nombre_manual FROM manuales WHERE clinica_id = ? AND estado = 1 ORDER BY nombre_manual ASC");
$stmtManuales->execute([$clinica_id]);
$listaManuales = $stmtManuales->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-file-signature"></i> Gestión de Contratos y Convenios EPS</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" data-toggle="modal" data-target="#modalContrato" id="btnNuevoContrato">
                        <i class="fas fa-plus"></i> Legalizar Nuevo Contrato
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card card-outline card-success">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaContratos" class="table table-bordered table-striped w-100">
                        <thead>
                            <tr>
                                <th>N° Contrato</th>
                                <th>Entidad (EPS)</th>
                                <th>Manual Tarifario Asignado</th>
                                <th>Vigencia Inicial</th>
                                <th>Vigencia Final</th>
                                <th>Estado</th>
                                <th style="width: 12%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL DE CONTRATACIÓN -->
<div class="modal fade" id="modalContrato" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formContrato">
                <input type="hidden" name="action" value="registrar">

                <div class="modal-header bg-success text-white">
                    <h4 class="modal-title"><i class="fas fa-file-contract"></i> Registrar Acuerdo Contractual</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Seleccionar EPS Administradora *</label>
                            <select name="eps_id" class="form-control select2" style="width: 100%;" required>
                                <option value="">-- Seleccione una entidad --</option>
                                <?php foreach ($listaEps as $eps): ?>
                                    <option value="<?= $eps['id'] ?>"><?= htmlspecialchars($eps['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Asignar Libro Tarifario (Precios) *</label>
                            <select name="manual_id" class="form-control select2" style="width: 100%;" required>
                                <option value="">-- Seleccione un manual --</option>
                                <?php foreach ($listaManuales as $manual): ?>
                                    <option value="<?= $manual['id'] ?>"><?= htmlspecialchars($manual['nombre_manual']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Código / Número de Contrato *</label>
                            <input type="text" name="numero_contrato" class="form-control" placeholder="Ej: CTR-2026-SAN01" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Porcentaje Cobertura EPS (%)</label>
                            <input type="number" name="porcentaje_cobertura" class="form-control" value="100" min="0" max="100">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Fecha Inicio Vigencia *</label>
                            <input type="date" name="fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Fecha Fin Vigencia *</label>
                            <input type="date" name="fecha_fin" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Objeto del Contrato / Notas Adicionales</label>
                        <textarea name="objeto_contrato" class="form-control" rows="3" placeholder="Detalles de topes presupuestales, exclusiones, cápitas..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success">Activar Contrato</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
