<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
require_once 'config/db.php';

$clinica_id = $_SESSION['clinica'];

// CONSULTA: Sedes para filtrar
$stmtCentros = $pdo->prepare("SELECT id, nombre_centro FROM centros_medicos WHERE clinica_id = ? AND estado = 1 ORDER BY nombre_centro ASC");
$stmtCentros->execute([$clinica_id]);
$centros = $stmtCentros->fetchAll();
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-tasks text-info"></i> Control de Estados e Ingreso de Citas</h1>
        </div>
    </section>

    <section class="content">
        <div class="card card-info card-outline mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label>Filtrar por Fecha</label>
                        <input type="date" id="filtroFecha" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-5 mb-2">
                        <label>Sede / Centro Médico</label>
                        <select id="filtroCentro" class="form-control">
                            <option value="">-- Todas las sedes --</option>
                            <?php foreach($centros as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_centro']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2 d-flex align-items-end">
                        <button type="button" id="btnBuscarCitas" class="btn btn-info btn-block">
                            <i class="fas fa-search"></i> Actualizar Listado
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light">
                <h3 class="card-title"><i class="fas fa-clock text-secondary"></i> Pacientes Programados</h3>
            </div>
            <div class="card-body p-0" id="divTablaCitasControl">
                </div>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>

