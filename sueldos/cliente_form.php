<?php
// cliente_form.php
require_once 'include/db.php';
require_once 'include/auth_check.php'; // Ahora nos dejará pasar gracias a la modificación

$titulo = "Nueva Empresa";
$cliente = ['id' => '', 'cuit' => '', 'razon_social' => '', 'tipo_empleador' => 1];

// 1. GUARDAR DATOS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cuit = $_POST['cuit'];
    $razon = $_POST['razon_social'];
    $tipo = $_POST['tipo_empleador'];

    // Validación simple: Verificar si el CUIT ya existe
    $stmtCheck = $pdo->prepare("SELECT id FROM tenants WHERE cuit = ?");
    $stmtCheck->execute([$cuit]);
    if ($stmtCheck->rowCount() > 0) {
        $error = "El CUIT $cuit ya está registrado en el sistema.";
    } else {
        // INSERTAR
        $newId = bin2hex(random_bytes(16));
        $sql = "INSERT INTO tenants (id, cuit, razon_social, tipo_empleador) VALUES (?, ?, ?, ?)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newId, $cuit, $razon, $tipo]);
            
            // Éxito: Volvemos al dashboard para seleccionarla
            header("Location: dashboard.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Usamos un layout simplificado (sin menú de módulos) porque aún no "entramos" a una empresa
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alta de Cliente - Silvia Cataldi Sistemas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    
    <nav class="navbar navbar-dark bg-dark mb-5">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">&larr; Volver al Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Registrar Nuevo Cliente</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Razón Social *</label>
                                <input type="text" name="razon_social" class="form-control" required 
                                       placeholder="Ej: Maderas del Sur S.A.">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">CUIT *</label>
                                <input type="text" name="cuit" class="form-control" required 
                                       placeholder="20123456789" maxlength="11" pattern="\d{11}">
                                <div class="form-text">Sin guiones (11 dígitos).</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Empleador</label>
                                <select name="tipo_empleador" class="form-select">
                                    <option value="1">Régimen General (PyME)</option>
                                    <option value="2">Construcción (UOCRA)</option>
                                    <option value="3">Agrario</option>
                                    <option value="4">Privado no PyME</option>
                                </select>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-success btn-lg">Crear Empresa</button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'include/layout_foot.php'; ?>
</body>
</html>