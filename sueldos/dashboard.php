<?php
// dashboard.php
require_once 'include/db.php';
// Incluimos auth_check, que validará login pero ignorará la falta de tenant aquí
require_once 'include/auth_check.php'; 

// Lógica para SELECCIONAR empresa
if (isset($_GET['seleccionar_id'])) {
    $id = $_GET['seleccionar_id'];
    
    // Validamos que exista (y aquí podrías validar permisos si tuvieras usuarios por empresa)
    $stmt = $pdo->prepare("SELECT id, razon_social FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();

    if ($tenant) {
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['tenant_nombre'] = $tenant['razon_social'];
        header("Location: index.php"); // Vamos al listado de empleados
        exit;
    }
}

// Listar todas las empresas disponibles
$stmt = $pdo->query("SELECT * FROM tenants ORDER BY razon_social");
$empresas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Selección de Empresa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    
    <nav class="navbar navbar-dark bg-dark mb-5">
        <div class="container">
            <span class="navbar-brand">Estudio Contable</span>
            <span class="navbar-text text-white">
                Hola, <?= htmlspecialchars($_SESSION['user_nombre']) ?> | <a href="include/logout.php" class="text-white">Salir</a>
            </span>
        </div>
    </nav>

    <div class="container">
        <h2 class="mb-4">Mis Clientes (Empresas)</h2>
        
        <div class="row">
            <?php foreach($empresas as $emp): ?>
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($emp['razon_social']) ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted">CUIT: <?= $emp['cuit'] ?></h6>
                        <p class="card-text">
                            <span class="badge bg-secondary">
                                <?= $emp['tipo_empleador'] == 1 ? 'PyME' : 'General' ?>
                            </span>
                        </p>
                        <a href="dashboard.php?seleccionar_id=<?= $emp['id'] ?>" class="btn btn-primary w-100">
                            Gestionar Liquidación &rarr;
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-dashed bg-transparent d-flex align-items-center justify-content-center" style="border: 2px dashed #ccc;">
                    <div class="card-body text-center">
                        <h5 class="text-muted">Nueva Empresa</h5>
                        <a href="cliente_form.php" class="btn btn-outline-success mt-2">+ Crear Cliente</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php require_once 'include/layout_foot.php'; ?>
</body>
</html>