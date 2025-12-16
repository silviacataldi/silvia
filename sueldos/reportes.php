<?php
// reportes.php
require_once 'include/db.php';
require_once 'include/auth_check.php';

if (!isset($_GET['liq_id'])) {
    die("Falta ID de liquidación");
}
$liq_id = $_GET['liq_id'];

// 1. OBTENER DATOS DE LA LIQUIDACIÓN
$stmtLiq = $pdo->prepare("SELECT * FROM liquidaciones WHERE id = ? AND tenant_id = ?");
$stmtLiq->execute([$liq_id, $tenant_id_actual]);
$liq = $stmtLiq->fetch();

if (!$liq) die("Liquidación no encontrada");

// 2. OBTENER LISTA DE EMPLEADOS LIQUIDADOS EN ESTE PERIODO
// Hacemos un DISTINCT sobre la tabla de detalles para saber quiénes participaron
$sqlEmp = "SELECT DISTINCT e.id, e.nombre, e.cuil, e.fecha_ingreso, c.nombre as categoria
           FROM liquidaciones_detalle ld
           JOIN empleados e ON ld.empleado_id = e.id
           LEFT JOIN categorias c ON e.categoria_id = c.id
           WHERE ld.liquidacion_id = ?
           ORDER BY e.nombre ASC";
$stmtEmp = $pdo->prepare($sqlEmp);
$stmtEmp->execute([$liq_id]);
$empleados = $stmtEmp->fetchAll();

// --- INICIO DEL HTML ---
// No incluimos 'layout_head.php' completo porque queremos una vista limpia para impresión
// Pero sí necesitamos Bootstrap y la seguridad.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibos - <?= htmlspecialchars($liq['descripcion']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .recibo-container {
            background: white;
            border: 1px solid #ccc;
            padding: 30px;
            margin-bottom: 30px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .recibo-header { border-bottom: 2px solid #333; margin-bottom: 15px; padding-bottom: 10px; }
        .table-recibo th { background-color: #eee; font-size: 0.9rem; }
        .table-recibo td { font-size: 0.9rem; }
        .total-row { font-weight: bold; background-color: #f0f0f0; }
        .neto-box { 
            border: 2px solid #333; 
            padding: 10px; 
            text-align: right; 
            font-size: 1.2rem; 
            font-weight: bold; 
            background: #e9ecef;
        }

        /* ESTILOS SOLO PARA IMPRESIÓN */
        @media print {
            body { background-color: white; padding: 0; }
            .no-print { display: none !important; }
            .recibo-container {
                box-shadow: none;
                border: 1px solid #000;
                margin-bottom: 0;
                page-break-after: always; /* ¡Truco! Salto de página después de cada recibo */
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="d-flex justify-content-between mb-4 no-print container">
        <div>
            <a href="liquidaciones.php" class="btn btn-secondary">&larr; Volver</a>
            <h4 class="d-inline-block ms-3">Recibos: <?= htmlspecialchars($liq['descripcion']) ?></h4>
        </div>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir Todo</button>
    </div>

    <?php foreach ($empleados as $emp): 
        // Traer los renglones calculados para este empleado
        $sqlDet = "SELECT * FROM liquidaciones_detalle 
                   WHERE liquidacion_id = ? AND empleado_id = ? 
                   ORDER BY concepto_codigo ASC"; // Ordenamos por código (primero sueldo, ultimo descuentos)
        $stmtDet = $pdo->prepare($sqlDet);
        $stmtDet->execute([$liq_id, $emp['id']]);
        $items = $stmtDet->fetchAll();

        // Inicializar acumuladores visuales
        $totalRem = 0;
        $totalNoRem = 0;
        $totalDes = 0;
    ?>

    <div class="recibo-container">
        <div class="recibo-header">
            <div class="row">
                <div class="col-6">
                    <h5 class="mb-0"><?= htmlspecialchars($_SESSION['tenant_nombre']) ?></h5> <small>CUIT: (Recuperar de tabla tenants)</small>
                </div>
                <div class="col-6 text-end">
                    <h6>Período: <b><?= $liq['periodo'] ?></b></h6>
                    <small>Liquidación N°: <?= substr($liq_id, 0, 8) ?></small>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <strong>Empleado:</strong><br>
                    <?= htmlspecialchars($emp['nombre']) ?>
                </div>
                <div class="col-md-4">
                    <strong>CUIL:</strong><br>
                    <?= htmlspecialchars($emp['cuil']) ?>
                </div>
                <div class="col-md-2">
                    <strong>Ingreso:</strong><br>
                    <?= date('d/m/Y', strtotime($emp['fecha_ingreso'])) ?>
                </div>
                <div class="col-md-2">
                    <strong>Cat:</strong><br>
                    <?= htmlspecialchars($emp['categoria'] ?? '-') ?>
                </div>
            </div>
        </div>

        <table class="table table-sm table-recibo">
            <thead>
                <tr>
                    <th style="width: 50px;">Cód</th>
                    <th>Concepto</th>
                    <th class="text-center" style="width: 80px;">Unid.</th>
                    <th class="text-end" style="width: 120px;">Hab. c/Ap.</th>
                    <th class="text-end" style="width: 120px;">Hab. s/Ap.</th>
                    <th class="text-end" style="width: 120px;">Deducc.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $monto = (float)$item['importe'];
                    
                    // Asignar columnas según tipo
                    $colRem = ($item['tipo'] == 'REM') ? $monto : 0;
                    $colNoRem = ($item['tipo'] == 'NO_REM') ? $monto : 0;
                    $colDes = ($item['tipo'] == 'DES') ? $monto : 0;

                    // Sumar totales
                    $totalRem += $colRem;
                    $totalNoRem += $colNoRem;
                    $totalDes += $colDes;
                ?>
                <tr>
                    <td><?= $item['concepto_codigo'] ?></td>
                    <td><?= htmlspecialchars($item['concepto_nombre']) ?></td>
                    <td class="text-center">
                        <?= ($item['cantidad'] > 0) ? floatval($item['cantidad']) : '' ?>
                    </td>
                    <td class="text-end"><?= ($colRem > 0) ? number_format($colRem, 2, ',', '.') : '' ?></td>
                    <td class="text-end"><?= ($colNoRem > 0) ? number_format($colNoRem, 2, ',', '.') : '' ?></td>
                    <td class="text-end"><?= ($colDes > 0) ? number_format($colDes, 2, ',', '.') : '' ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php for($i=count($items); $i<8; $i++): ?>
                    <tr><td colspan="6">&nbsp;</td></tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-end">SUBTOTALES:</td>
                    <td class="text-end"><?= number_format($totalRem, 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($totalNoRem, 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($totalDes, 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="row align-items-center mt-3">
            <div class="col-8">
                <small class="text-muted">
                    Lugar y Fecha de Pago: ................................................................<br>
                    Recibí el importe neto de esta liquidación a mi entera satisfacción.
                </small>
            </div>
            <div class="col-4">
                <?php $neto = $totalRem + $totalNoRem - $totalDes; ?>
                <div class="neto-box">
                    <small style="font-weight: normal; font-size: 0.8rem; display:block;">NETO A COBRAR</small>
                    $ <?= number_format($neto, 2, ',', '.') ?>
                </div>
            </div>
        </div>
        
        <div class="mt-4 row">
            <div class="col-6 text-center">
                <br><br>
                <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;">Firma Empleador</div>
            </div>
            <div class="col-6 text-center">
                <br><br>
                <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;">Firma Empleado</div>
            </div>
        </div>
    </div>

    <?php endforeach; ?>

</body>
</html>