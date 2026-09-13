<?php
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    header("Location: login.php");
    exit();
}
include 'layout/header.php';
include 'layout/nav.php';
include 'layout/sidebar.php';
require_once 'config/db.php';

$clinica_id = intval($_SESSION['clinica']);

try {
    // Reporte seguro: Une las tablas de movimientos y artículos condicionando por clinica_id
    $sql = "SELECT m.fecha_movimiento, a.codigo, a.nombre, m.tipo_movimiento, m.cantidad, m.motivo 
            FROM inventario_movimientos m
            INNER JOIN articulos_inventario a ON m.articulo_id = a.id_articulo
            WHERE m.clinica_id = ? 
            ORDER BY m.fecha_movimiento DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clinica_id]);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $movimientos = [];
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-history"></i> Reporte Histórico de Movimientos de Inventario</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-teal">
                <div class="card-header">
                    <h3 class="card-title">Historial de Entradas y Salidas (Su Clínica)</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" onclick="window.print()"><i class="fas fa-print"></i> Imprimir Reporte</button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Código</th>
                                <th>Artículo</th>
                                <th>Tipo de Movimiento</th>
                                <th>Cantidad</th>
                                <th>Motivo / Justificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($movimientos) > 0): ?>
                                <?php foreach ($movimientos as $mov): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mov['fecha_movimiento']); ?></td>
                                        <td><code><?php echo htmlspecialchars($mov['codigo']); ?></code></td>
                                        <td><?php echo htmlspecialchars($mov['nombre']); ?></td>
                                        <td>
                                            <?php if ($mov['tipo_movimiento'] === 'ENTRADA'): ?>
                                                <span class="badge badge-success"><i class="fas fa-arrow-up"></i> ENTRADA</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger"><i class="fas fa-arrow-down"></i> SALIDA</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($mov['cantidad']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($mov['motivo']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No se registran movimientos para su clínica.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>