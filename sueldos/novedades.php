<?php
// novedades.php
require_once 'include/db.php';
require_once 'include/auth_check.php';

if (!isset($_GET['liq_id'])) {
    die("Falta ID de liquidación");
}

$liq_id = $_GET['liq_id'];

// 1. Obtener datos de la liquidación actual
$stmtLiq = $pdo->prepare("SELECT * FROM liquidaciones WHERE id = ? AND tenant_id = ?");
$stmtLiq->execute([$liq_id, $tenant_id_actual]);
$liquidacion = $stmtLiq->fetch();

if (!$liquidacion) die("Liquidación no encontrada");

// 2. GUARDAR NOVEDAD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = $_POST['empleado_id'];
    $con_id = $_POST['concepto_id'];
    $cantidad = $_POST['cantidad'];
    
    // Insertamos en la tabla de novedades usando el PERIODO de la liquidación
    // (Ajustamos para usar el periodo que ya teníamos en la tabla Novedades)
    $id_nov = bin2hex(random_bytes(16));
    $sql = "INSERT INTO novedades (id, tenant_id, periodo, empleado_id, concepto_id, cantidad) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$id_nov, $tenant_id_actual, $liquidacion['periodo'], $emp_id, $con_id, $cantidad]);
}

// 3. ELIMINAR NOVEDAD
if (isset($_GET['borrar'])) {
    $pdo->prepare("DELETE FROM novedades WHERE id = ? AND tenant_id = ?")
        ->execute([$_GET['borrar'], $tenant_id_actual]);
}

// DATOS PARA LOS SELECTS
$empleados = $pdo->query("SELECT id, nombre FROM empleados WHERE tenant_id = '$tenant_id_actual' AND estado='ACTIVO'")->fetchAll();
// Solo mostramos conceptos que NO son automáticos (opcional, aquí traigo todos para probar)
$conceptos = $pdo->query("SELECT id, nombre, codigo FROM conceptos WHERE (tenant_id = '$tenant_id_actual' OR tenant_id IS NULL)")->fetchAll();

// LISTADO DE NOVEDADES YA CARGADAS
$sqlLista = "SELECT n.id, e.nombre as empleado, c.nombre as concepto, n.cantidad 
             FROM novedades n
             JOIN empleados e ON n.empleado_id = e.id
             JOIN conceptos c ON n.concepto_id = c.id
             WHERE n.tenant_id = ? AND n.periodo = ?
             ORDER BY n.created_at DESC";
$stmtLista = $pdo->prepare($sqlLista);
$stmtLista->execute([$tenant_id_actual, $liquidacion['periodo']]);
$novedades_cargadas = $stmtLista->fetchAll();

include 'include/layout_head.php';
?>

<div class="row mb-3">
    <div class="col">
        <h3>Carga de Novedades <small class="text-muted">| <?= $liquidacion['descripcion'] ?></small></h3>
        <a href="liquidaciones.php" class="btn btn-outline-secondary btn-sm">&larr; Volver</a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm bg-light">
            <div class="card-body">
                <h5 class="card-title mb-3">Nueva Novedad</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label>Empleado</label>
                        <select name="empleado_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($empleados as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= $e['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Concepto</label>
                        <select name="concepto_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($conceptos as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> - <?= $c['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Cantidad / Valor</label>
                        <input type="number" step="0.01" name="cantidad" class="form-control" placeholder="Ej: 2.00" required>
                        <div class="form-text">Si es horas extras, ingrese horas. Si es un bono fijo, ingrese 1.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Agregar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Concepto</th>
                            <th>Cantidad</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($novedades_cargadas as $nov): ?>
                        <tr>
                            <td><?= htmlspecialchars($nov['empleado']) ?></td>
                            <td><?= htmlspecialchars($nov['concepto']) ?></td>
                            <td><?= $nov['cantidad'] ?></td>
                            <td>
                                <a href="novedades.php?liq_id=<?= $liq_id ?>&borrar=<?= $nov['id'] ?>" 
                                   class="btn btn-danger btn-sm text-white" 
                                   onclick="return confirm('¿Eliminar?')">x</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($novedades_cargadas)==0): ?>
                            <tr><td colspan="4" class="text-center text-muted">No hay novedades cargadas para este mes.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'include/layout_foot.php'; ?>
</body>
</html>