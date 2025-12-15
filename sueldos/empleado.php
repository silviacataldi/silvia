<?php
// empleado.php
require_once 'include/db.php';



// Consulta para traer empleados junto con el nombre de su categoría
$sql = "SELECT e.*, c.nombre as categoria_nombre 
        FROM empleados e 
        LEFT JOIN categorias c ON e.categoria_id = c.id
        WHERE e.tenant_id = :tenant_id AND e.estado = 'ACTIVO'";

$stmt = $pdo->prepare($sql);
$stmt->execute(['tenant_id' => $tenant_id_actual]);
$empleados = $stmt->fetchAll();

include 'include/layout_head.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Nómina de Empleados</h2>
    <a href="empleado_form.php" class="btn btn-success">+ Nuevo Legajo</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>CUIL</th>
                    <th>Nombre</th>
                    <th>Ingreso</th>
                    <th>Categoría</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleados as $emp): ?>
                <tr>
                    <td><?= htmlspecialchars($emp['cuil']) ?></td>
                    <td><?= htmlspecialchars($emp['nombre']) ?></td>
                    <td><?= date('d/m/Y', strtotime($emp['fecha_ingreso'])) ?></td>
                    <td><?= htmlspecialchars($emp['categoria_nombre'] ?? 'Sin Categoría') ?></td>
                    <td>
                        <a href="empleado_form.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>