<?php
// conceptos.php
require_once 'include/db.php';



// Consulta: Traemos conceptos PROPIOS y los GLOBALES (tenant_id IS NULL)
// Los ordenamos por 'orden_calculo' para ver la secuencia lógica de liquidación
$sql = "SELECT * FROM conceptos 
        WHERE (tenant_id = :tenant_id OR tenant_id IS NULL) 
        ORDER BY orden_calculo ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['tenant_id' => $tenant_id_actual]);
$conceptos = $stmt->fetchAll();

include 'include/layout_head.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Conceptos de Liquidación</h2>
    <a href="concepto_form.php" class="btn btn-success">+ Nuevo Concepto</a>
</div>

<div class="alert alert-info py-2">
    <small><b>Nota:</b> Los conceptos con <span class="badge bg-secondary">Global</span> son del sistema. Los demás son específicos de este cliente.</small>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Orden</th>
                    <th>Cód.</th>
                    <th>Descripción</th>
                    <th>Tipo</th>
                    <th>Fórmula</th>
                    <th>AFIP</th> <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conceptos as $con): 
                    // Lógica visual para las etiquetas
                    $bgClass = match($con['tipo']) {
                        'REM' => 'bg-success',      // Remunerativo (Verde)
                        'NO_REM' => 'bg-warning text-dark', // No Rem (Amarillo)
                        'DES' => 'bg-danger',       // Descuento (Rojo)
                    };
                    $tipoLabel = match($con['tipo']) {
                        'REM' => 'Remunerativo',
                        'NO_REM' => 'No Remun.',
                        'DES' => 'Retención',
                    };
                ?>
                <tr>
                    <td><?= $con['orden_calculo'] ?></td>
                    <td><b><?= htmlspecialchars($con['codigo']) ?></b></td>
                    <td>
                        <?= htmlspecialchars($con['nombre']) ?>
                        <?php if(is_null($con['tenant_id'])): ?>
                            <span class="badge bg-secondary ms-1" style="font-size: 0.7em;">Global</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $bgClass ?>"><?= $tipoLabel ?></span></td>
                    
                    <td class="text-monospace text-muted small">
                        <?= htmlspecialchars(substr($con['formula'], 0, 40)) ?>...
                    </td>
                    
                    <td><?= htmlspecialchars($con['codigo_afip'] ?? '-') ?></td>
                    
                    <td>
                        <?php if($con['tenant_id'] == $tenant_id_actual): ?>
                            <a href="concepto_form.php?id=<?= $con['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary" disabled title="Concepto del Sistema">Bloqueado</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>