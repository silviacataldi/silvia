<?php
// concepto_form.php
require_once 'include/db.php';


$concepto = [
    'id' => '', 'codigo' => '', 'nombre' => '', 
    'tipo' => 'REM', 'formula' => '', 'codigo_afip' => '', 'orden_calculo' => 10
];
$titulo = "Nuevo Concepto";

// 1. CARGAR DATOS SI ES EDICIÓN
if (isset($_GET['id'])) {
    $titulo = "Editar Concepto";
    // Solo permitimos editar conceptos que pertenezcan al tenant (seguridad)
    $stmt = $pdo->prepare("SELECT * FROM conceptos WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_GET['id'], $tenant_id_actual]);
    $data = $stmt->fetch();
    if ($data) { $concepto = $data; } else { die("Concepto no encontrado o no tienes permiso."); }
}

// 2. GUARDAR (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recolección de datos
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $tipo = $_POST['tipo'];
    $formula = $_POST['formula'];
    $cod_afip = $_POST['codigo_afip'];
    $orden = $_POST['orden_calculo'];

    if ($concepto['id']) {
        // UPDATE
        $sql = "UPDATE conceptos SET codigo=?, nombre=?, tipo=?, formula=?, codigo_afip=?, orden_calculo=? 
                WHERE id=? AND tenant_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codigo, $nombre, $tipo, $formula, $cod_afip, $orden, $concepto['id'], $tenant_id_actual]);
    } else {
        // INSERT
        $newId = bin2hex(random_bytes(16));
        $sql = "INSERT INTO conceptos (id, tenant_id, codigo, nombre, tipo, formula, codigo_afip, orden_calculo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newId, $tenant_id_actual, $codigo, $nombre, $tipo, $formula, $cod_afip, $orden]);
    }
    header("Location: conceptos.php");
    exit;
}

include 'include/layout_head.php';
?>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-white">
                <h4 class="mb-0"><?= $titulo ?></h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Código Interno *</label>
                            <input type="text" name="codigo" class="form-control font-monospace" required 
                                   value="<?= htmlspecialchars($concepto['codigo']) ?>" placeholder="0100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre del Concepto *</label>
                            <input type="text" name="nombre" class="form-control" required 
                                   value="<?= htmlspecialchars($concepto['nombre']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Orden Calc. *</label>
                            <input type="number" name="orden_calculo" class="form-control" required 
                                   value="<?= htmlspecialchars($concepto['orden_calculo']) ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Concepto</label>
                            <select name="tipo" class="form-select">
                                <option value="REM" <?= $concepto['tipo'] == 'REM' ? 'selected' : '' ?>>Remunerativo (Sujeto a Aportes)</option>
                                <option value="NO_REM" <?= $concepto['tipo'] == 'NO_REM' ? 'selected' : '' ?>>No Remunerativo</option>
                                <option value="DES" <?= $concepto['tipo'] == 'DES' ? 'selected' : '' ?>>Descuento / Retención</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Código AFIP (LSD)</label>
                            <input type="text" name="codigo_afip" class="form-control" 
                                   value="<?= htmlspecialchars($concepto['codigo_afip']) ?>" placeholder="Ej: 110000">
                            <div class="form-text text-muted">Requerido para el Libro de Sueldos Digital.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Fórmula Matemática *</label>
                        <textarea name="formula" class="form-control font-monospace" rows="4" required 
                                  style="background-color: #f8f9fa;"><?= htmlspecialchars($concepto['formula']) ?></textarea>
                        <div class="form-text">
                            Use punto para decimales (ej 0.03). No use signo $.
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="conceptos.php" class="btn btn-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary">Guardar Concepto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h5 class="card-title text-primary">Variables Disponibles</h5>
                <p class="small text-muted">Puede usar estas variables en su fórmula. El sistema las reemplazará automáticamente.</p>
                
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item bg-transparent">
                        <code>BASICO</code><br>Sueldo básico de la categoría.
                    </li>
                    <li class="list-group-item bg-transparent">
                        <code>ANTIGUEDAD</code><br>Años de antigüedad calculados.
                    </li>
                    <li class="list-group-item bg-transparent">
                        <code>TOTAL_REMUNERATIVO</code><br>Suma acumulada de haberes REM. Útil para calcular Jubilación (11%).
                    </li>
                    <li class="list-group-item bg-transparent">
                        <code>TOTAL_ANTIGUEDAD</code><br>Resultado del concepto Antigüedad (si ya se calculó).
                    </li>
                </ul>

                <hr>
                <h6 class="text-primary">Ejemplos</h6>
                <div class="mb-2">
                    <small><b>Adicional 5% del Básico:</b></small><br>
                    <code>BASICO * 0.05</code>
                </div>
                <div class="mb-2">
                    <small><b>Jubilación (11%):</b></small><br>
                    <code>TOTAL_REMUNERATIVO * 0.11</code>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'include/layout_foot.php'; ?>
</body>
</html>