<?php
// imprimir_historial.php
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    die("Acceso denegado.");
}

require_once 'config/db.php'; // Ajusta la ruta relativa

$paciente_id = intval($_GET['id'] ?? 0);
if ($paciente_id === 0) {
    die("Paciente no válido.");
}

// 1. Obtener Datos Generales del Paciente
$sqlPac = "SELECT p.*, u.nombre, u.email, u.usuario 
           FROM pacientes_datos p 
           INNER JOIN usuarios_clinicas u ON p.usuario_id = u.id 
           WHERE p.id = ?";
$stmt = $pdo->prepare($sqlPac);
$stmt->execute([$paciente_id]);
$paciente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paciente) {
    die("Paciente no encontrado.");
}

// 2. Obtener Historial Clínico
$sqlHC = "SELECT hc.*, u.nombre AS nombre_medico, d.codigo_cie, d.descripcion AS descripcion_cie
          FROM historias_clinicas hc
          INNER JOIN usuarios_clinicas u ON hc.medico_id = u.id
          LEFT JOIN diagnosticos d ON hc.diagnostico_id = d.id
          WHERE hc.paciente_id = ? 
          ORDER BY hc.id DESC";
$stmtHC = $pdo->prepare($sqlHC);
$stmtHC->execute([$paciente['usuario_id']]);
$historias = $stmtHC->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial Clínico - <?= htmlspecialchars($paciente['nombre']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #fff; color: #000; font-size: 12px; }
        .print-header { border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
        .ficha-paciente { background-color: #f8f9fa; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .card-atencion { border: 1px solid #666; margin-bottom: 20px; page-break-inside: avoid; }
        .card-header { background-color: #eee !important; color: #000 !important; font-weight: bold; }
        
        /* Estilos de Impresión */
        @media print {
            .no-print { display: none; }
            body { font-size: 11px; }
            .card-atencion { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="text-right mb-4 no-print">
        <button onclick="window.print();" class="btn btn-primary"><i class="fas fa-print"></i> Ejecutar Impresión</button>
        <button onclick="window.close();" class="btn btn-secondary">Cerrar Ventana</button>
    </div>

    <div class="row print-header align-items-center">
        <div class="col-8">
            <h3>HISTORIAL CLÍNICO GENERAL</h3>
            <p class="text-muted m-0">Generado el: <?= date('d/m/Y g:i A') ?></p>
        </div>
        <div class="col-4 text-right">
            <h5>Centro Médico</h5>
        </div>
    </div>

    <div class="ficha-paciente">
        <h5>Información Demográfica del Paciente</h5>
        <div class="row">
            <div class="col-4"><strong>Nombre:</strong> <?= htmlspecialchars($paciente['nombre']) ?></div>
            <div class="col-4"><strong>Documento:</strong> <?= htmlspecialchars($paciente['documento_identidad']) ?></div>
            <div class="col-4"><strong>F. Nacimiento:</strong> <?= htmlspecialchars($paciente['fecha_nacimiento']) ?></div>
            <div class="col-4"><strong>Género:</strong> <?= htmlspecialchars($paciente['genero']) ?></div>
            <div class="col-4"><strong>Tipo Sangre:</strong> <?= htmlspecialchars($paciente['tipo_sangre']) ?></div>
            <div class="col-4"><strong>Teléfono:</strong> <?= htmlspecialchars($paciente['telefono_contacto']) ?></div>
        </div>
    </div>

    <h4>Evoluciones Médicas / Odontológicas</h4>
    <?php if (empty($historias)): ?>
        <p>No se registran atenciones para este paciente.</p>
    <?php else: ?>
        <?php foreach ($historias as $hc): ?>
            <div class="card card-atencion">
                <div class="card-header">
                    Atención Médica por: <?= htmlspecialchars($hc['nombre_medico']) ?>
                </div>
                <div class="card-body">
                    <p><strong>Motivo de consulta:</strong> <?= htmlspecialchars($hc['motivo_consulta']) ?></p>
                    <p><strong>Enfermedad actual:</strong> <?= htmlspecialchars($hc['enfermedad_actual'] ?: 'No especificada') ?></p>
                    
                    <table class="table table-sm table-bordered text-center small my-2">
                        <thead>
                            <tr class="bg-light">
                                <th>P. Arterial</th>
                                <th>F. Cardíaca</th>
                                <th>Temp.</th>
                                <th>Saturación</th>
                                <th>IMC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= htmlspecialchars($hc['presion_arterial'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($hc['frecuencia_cardiaca'] ?: '-') ?> lpm</td>
                                <td><?= htmlspecialchars($hc['temperatura'] ?: '-') ?> °C</td>
                                <td><?= htmlspecialchars($hc['saturacion_oxigeno'] ?: '-') ?>%</td>
                                <td><?= htmlspecialchars($hc['imc'] ?: '-') ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p><strong>Diagnóstico (CIE-10):</strong> [<?= htmlspecialchars($hc['codigo_cie']) ?>] <?= htmlspecialchars($hc['descripcion_cie']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    // Ejecuta de manera automática el cuadro de diálogo de impresión al cargar el archivo
    window.onload = function() { window.print(); }
</script>
</body>
</html>