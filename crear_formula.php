<?php 
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { header("Location: login.php"); exit(); }
include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<link rel="stylesheet" href="plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-file-medical"></i> Nueva Fórmula Médica</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <form id="formNuevaFormula">
                <div class="row">
                    
                    <!-- PANEL IZQUIERDO: DATOS DE CONTROL Y PACIENTE -->
                    <div class="col-md-4">
                        <div class="card card-outline card-primary">
                            <div class="card-header"><h3 class="card-title">Información de la Consulta</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Paciente *</label>
                                    <select name="paciente_id" id="selectPaciente" class="form-control select2" required>
                                        <option value="">Seleccione un paciente...</option>
                                        <?php
                                        require_once 'config/db.php';
                                        // Ajuste Relacional: Traemos el ID de pacientes_datos uniendo con usuarios_clinicas
                                        $stmtP = $pdo->prepare("
                                            SELECT pd.id AS paciente_datos_id, pd.documento_identidad, uc.nombre 
                                            FROM pacientes_datos pd
                                            INNER JOIN usuarios_clinicas uc ON pd.usuario_id = uc.id
                                            WHERE uc.clinica_id = ? AND uc.estado = 1
                                        ");
                                        $stmtP->execute([$_SESSION['clinica']]);
                                        while($p = $stmtP->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='{$p['paciente_datos_id']}'>{$p['documento_identidad']} - {$p['nombre']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Diagnóstico Principal (CIE-10) *</label>
                                    <select name="diagnostico_cie10" id="selectDiagnostico" class="form-control" required>
                                        <option value="">Escribe el código o nombre del diagnóstico...</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Observaciones / Recomendaciones</label>
                                    <textarea name="observaciones" class="form-control" rows="4" placeholder="Ej: Tomar abundante agua, reposo..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PANEL DERECHO: MEDICAMENTOS -->
                    <div class="col-md-8">
                        <div class="card card-primary">
                            <div class="card-header"><h3 class="card-title"><i class="fas fa-pills"></i> Prescripción de Medicamentos</h3></div>
                            <div class="card-body">
                                
                                <div class="row align-items-end mb-4 bg-light p-3 rounded border">
                                    <div class="col-md-6 form-group">
                                        <label>Buscar Medicamento (Catálogo Activo)</label>
                                        <select id="buscadorMedicamento" class="form-control"></select>
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label>Cantidad</label>
                                        <input type="number" id="tempCantidad" class="form-control" min="1" value="1">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <button type="button" id="btnAgregarMedicamento" class="btn btn-success btn-block">
                                            <i class="fas fa-plus-circle"></i> Agregar a la Fórmula
                                        </button>
                                    </div>
                                </div>

                                <table id="tablaFormula" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Medicamento</th>
                                            <th style="width: 100px;">Cant.</th>
                                            <th>Dosificación e Instrucciones *</th>
                                            <th style="width: 50px;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpoFormula">
                                        <tr id="filaVacia"><td colspan="4" class="text-center text-muted">No se han recetado medicamentos aún.</td></tr>
                                    </tbody>
                                </table>

                            </div>
                            <div class="card-footer text-right">
                                <button type="submit" class="btn btn-primary btn-lg shadow"><i class="fas fa-save"></i> Guardar y Firmar Fórmula</button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>
