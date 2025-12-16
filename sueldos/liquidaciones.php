<?php
// liquidaciones.php
require_once 'include/db.php';
require_once 'include/auth_check.php';

// CREAR NUEVA LIQUIDACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_periodo'])) {
    $periodo = $_POST['periodo']; // 202504
    $desc = $_POST['descripcion'];
    
    // Validar que no exista ya para este tenant
    $stmt = $pdo->prepare("SELECT id FROM liquidaciones WHERE tenant_id = ? AND periodo = ?");
    $stmt->execute([$tenant_id_actual, $periodo]);
    
    if ($stmt->rowCount() == 0) {
        $id = bin2hex(random_bytes(16));
        $sql = "INSERT INTO liquidaciones (id, tenant_id, periodo, descripcion) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$id, $tenant_id_actual, $periodo, $desc]);
    }
}

// LISTAR LIQUIDACIONES EXISTENTES
$stmt = $pdo->prepare("SELECT * FROM liquidaciones WHERE tenant_id = ? ORDER BY periodo DESC");
$stmt->execute([$tenant_id_actual]);
$liquidaciones = $stmt->fetchAll();

include 'include/layout_head.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Liquidaciones</h2>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevaLiq">
        + Nueva Liquidación
    </button>
</div>

<div class="row">
    <?php foreach ($liquidaciones as $liq): ?>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-start border-4 <?= $liq['estado'] == 'ABIERTA' ? 'border-success' : 'border-secondary' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h5 class="card-title"><?= htmlspecialchars($liq['descripcion']) ?></h5>
                    <span class="badge <?= $liq['estado'] == 'ABIERTA' ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $liq['estado'] ?>
                    </span>
                </div>
                <h6 class="text-muted mb-3">Período: <?= $liq['periodo'] ?></h6>
                
                <div class="d-grid gap-2">
                    <?php if($liq['estado'] == 'ABIERTA'): ?>
                        <a href="novedades.php?liq_id=<?= $liq['id'] ?>" class="btn btn-outline-primary btn-sm">
                            1. Cargar Novedades
                        </a>
                        <a href="procesar_liquidacion.php?liq_id=<?= $liq['id'] ?>" class="btn btn-primary btn-sm">
                            2. Calcular / Liquidar
                        </a>
                    <?php else: ?>
                        <a href="#" class="btn btn-secondary btn-sm disabled">Cerrada</a>
                        <a href="reportes.php?liq_id=<?= $liq['id'] ?>" class="btn btn-outline-dark btn-sm">Ver Recibos</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="modalNuevaLiq" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Liquidación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Período (AAAAMM)</label>
                    <input type="number" name="periodo" class="form-control" placeholder="202501" required>
                </div>
                <div class="mb-3">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" class="form-control" placeholder="Ej: Mensual Enero" required>
                </div>
                <input type="hidden" name="crear_periodo" value="1">
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Crear</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
<?php require_once 'include/layout_foot.php'; ?>
</body>
</html>