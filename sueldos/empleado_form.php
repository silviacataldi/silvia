<?php
// empleado_form.php
require_once 'include/db.php';
require_once 'include/auth_check.php';

$empleado = [
    'id' => '', 'cuil' => '', 'nombre' => '', 
    'fecha_ingreso' => date('Y-m-d'), 'categoria_id' => '', 'cbu' => ''
];
$titulo = "Nuevo Empleado";

// 1. SI VIENE UN ID POR GET, ESTAMOS EDITANDO
if (isset($_GET['id'])) {
    $titulo = "Editar Empleado";
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_GET['id'], $tenant_id_actual]);
    $data = $stmt->fetch();
    if ($data) { $empleado = $data; }
}

// 2. CARGAR CATEGORÍAS PARA EL SELECT (Dropdown)
$stmtCat = $pdo->prepare("SELECT id, nombre FROM categorias WHERE tenant_id = ?");
$stmtCat->execute([$tenant_id_actual]);
$categorias = $stmtCat->fetchAll();

// 3. PROCESAR FORMULARIO (GUARDAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $cuil = $_POST['cuil'];
    $fecha = $_POST['fecha_ingreso'];
    $cat_id = $_POST['categoria_id'];
    $cbu = $_POST['cbu'];
    
    // Validación básica
    if ($empleado['id']) {
        // UPDATE
        $sql = "UPDATE empleados SET nombre=?, cuil=?, fecha_ingreso=?, categoria_id=?, cbu=? 
                WHERE id=? AND tenant_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $cuil, $fecha, $cat_id, $cbu, $empleado['id'], $tenant_id_actual]);
    } else {
        // INSERT (Generamos UUID manualmente o dejamos que MySQL lo haga si configuraste triggers/funciones. 
        // Aquí uso una función simple de UUID para compatibilidad)
        $newId = bin2hex(random_bytes(16)); // Simplificación de UUID para el ejemplo
        $sql = "INSERT INTO empleados (id, tenant_id, nombre, cuil, fecha_ingreso, categoria_id, cbu) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newId, $tenant_id_actual, $nombre, $cuil, $fecha, $cat_id, $cbu]);
    }
    
    // Redirigir al listado
    header("Location: index.php");
    exit;
}

include 'include/layout_head.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-white">
                <h4 class="mb-0"><?= $titulo ?></h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">CUIL *</label>
                            <input type="text" name="cuil" class="form-control" required 
                                   value="<?= htmlspecialchars($empleado['cuil']) ?>" placeholder="20-12345678-9">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre" class="form-control" required 
                                   value="<?= htmlspecialchars($empleado['nombre']) ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Ingreso *</label>
                            <input type="date" name="fecha_ingreso" class="form-control" required 
                                   value="<?= htmlspecialchars($empleado['fecha_ingreso']) ?>">
                            <div class="form-text">Crítico para calcular antigüedad.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoría (Básico)</label>
                            <select name="categoria_id" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $empleado['categoria_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">CBU (Para exportación bancaria)</label>
                        <input type="text" name="cbu" class="form-control" 
                               value="<?= htmlspecialchars($empleado['cbu']) ?>">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Guardar Legajo</button>
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once 'include/layout_foot.php'; ?>
</body>
</html>