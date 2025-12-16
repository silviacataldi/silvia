<?php
// procesar_liquidacion.php
require_once 'include/db.php';
require_once 'include/auth_check.php';
require_once 'include/MotorDeCalculo.php';
require_once 'include/Concepto.php';

if (!isset($_GET['liq_id'])) {
    die("Error: Falta ID de liquidación.");
}

$liq_id = $_GET['liq_id'];

// 1. VALIDACIONES DE SEGURIDAD
// Verificamos que la liquidación pertenezca al tenant y esté abierta
$stmt = $pdo->prepare("SELECT * FROM liquidaciones WHERE id = ? AND tenant_id = ?");
$stmt->execute([$liq_id, $tenant_id_actual]);
$liquidacion = $stmt->fetch();

if (!$liquidacion) die("Liquidación no encontrada.");
if ($liquidacion['estado'] == 'CERRADA') die("Esta liquidación ya está cerrada y no se puede recalcular.");

try {
    // INICIAMOS TRANSACCIÓN (Todo o nada)
    $pdo->beginTransaction();

    // 2. LIMPIEZA PREVIA
    // Borramos detalles anteriores de esta liquidación para evitar duplicados si recalculamos
    $pdo->prepare("DELETE FROM liquidaciones_detalle WHERE liquidacion_id = ?")->execute([$liq_id]);

    // 3. OBTENER DATOS MASIVOS
    
    // A) Conceptos activos (Globales + Tenant), ordenados por prioridad
    $stmtConc = $pdo->prepare("SELECT * FROM conceptos WHERE (tenant_id = ? OR tenant_id IS NULL) ORDER BY orden_calculo ASC");
    $stmtConc->execute([$tenant_id_actual]);
    $conceptosDB = $stmtConc->fetchAll();

    // B) Empleados Activos con su Básico (Join Categorías)
    $sqlEmp = "SELECT e.*, c.sueldo_basico 
               FROM empleados e 
               LEFT JOIN categorias c ON e.categoria_id = c.id
               WHERE e.tenant_id = ? AND e.estado = 'ACTIVO'";
    $stmtEmp = $pdo->prepare($sqlEmp);
    $stmtEmp->execute([$tenant_id_actual]);
    $empleados = $stmtEmp->fetchAll();

    $contador_empleados = 0;

    // 4. BUCLE DE CÁLCULO (POR EMPLEADO)
    foreach ($empleados as $emp) {
        $contador_empleados++;
        
        // --- PREPARAR CONTEXTO DEL MOTOR ---
        $motor = new MotorDeCalculo();
        
        // Variables Fijas
        $fechaIngreso = new DateTime($emp['fecha_ingreso']);
        $antiguedad = (new DateTime())->diff($fechaIngreso)->y; // Años completos
        
        $contexto = [
            'BASICO' => $emp['sueldo_basico'] ?? 0, // Si no tiene categoría, básico 0
            'ANTIGUEDAD' => $antiguedad
        ];

        // Variables Dinámicas (Novedades cargadas en novedades.php)
        // Buscamos novedades para ESTE empleado en ESTE periodo
        $sqlNov = "SELECT c.codigo, n.cantidad 
                   FROM novedades n 
                   JOIN conceptos c ON n.concepto_id = c.id 
                   WHERE n.empleado_id = ? AND n.periodo = ? AND n.tenant_id = ?";
        $stmtNov = $pdo->prepare($sqlNov);
        $stmtNov->execute([$emp['id'], $liquidacion['periodo'], $tenant_id_actual]);
        $novedades = $stmtNov->fetchAll();

        // Inyectamos novedades al contexto (ej: HE_50_CANT = 5)
        // IMPORTANTE: Asumimos que la fórmula usa "CODIGO_CANT" para referirse a la cantidad
        foreach ($novedades as $nov) {
            $contexto[$nov['codigo'] . '_CANT'] = $nov['cantidad'];
        }

        $motor->cargarContexto($contexto);
        
        // Acumuladores temporales para el recibo
        $totalRem = 0;
        $totalNoRem = 0;
        $totalDes = 0;

        // --- EJECUTAR CONCEPTOS ---
        foreach ($conceptosDB as $row) {
            $conceptoObj = new Concepto($row['nombre'], $row['codigo'], $row['tipo'], $row['formula']);
            
            // ¡CALCULAR!
            $importe = $motor->resolver($conceptoObj);

            // Si el importe es 0 y NO es una novedad informada explícitamente, lo saltamos
            // (A menos que quieras mostrar renglones en cero en el recibo)
            if ($importe == 0) continue;

            // Actualizar acumuladores del Motor (para que Jubilación calcule sobre el total actual)
            if ($row['tipo'] == 'REM') $totalRem += $importe;
            if ($row['tipo'] == 'NO_REM') $totalNoRem += $importe;
            if ($row['tipo'] == 'DES') $totalDes += $importe; // Descuentos suelen ser positivos en valor absoluto

            $motor->agregarAlContexto('TOTAL_REMUNERATIVO', $totalRem);
            $motor->agregarAlContexto('TOTAL_NO_REMUNERATIVO', $totalNoRem);
            $motor->agregarAlContexto('TOTAL_DESCUENTOS', $totalDes);
            
            // Guardar resultado individual para uso posterior en fórmulas (ej: PRESENTISMO)
            $motor->agregarAlContexto('C' . $row['codigo'], $importe); 

            // --- GUARDAR EN BASE DE DATOS (DETALLE) ---
            // Recuperar la cantidad si vino de una novedad (solo para mostrar en el recibo)
            $cantidad_imprimir = 0;
            if (isset($contexto[$row['codigo'] . '_CANT'])) {
                $cantidad_imprimir = $contexto[$row['codigo'] . '_CANT'];
            }

            $uuid_detalle = bin2hex(random_bytes(16));
            $sqlInsert = "INSERT INTO liquidaciones_detalle 
                          (id, liquidacion_id, empleado_id, concepto_id, concepto_codigo, concepto_nombre, cantidad, importe, tipo)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $pdo->prepare($sqlInsert)->execute([
                $uuid_detalle,
                $liq_id,
                $emp['id'],
                $row['id'],
                $row['codigo'],
                $row['nombre'],
                $cantidad_imprimir,
                $importe,
                $row['tipo']
            ]);
        }
    }

    $pdo->commit();

    // ÉXITO: Mostrar resumen simple
    include 'include/layout_head.php';
    ?>
    <div class="card border-success mb-3" style="max-width: 600px; margin: 50px auto;">
        <div class="card-header bg-success text-white">Liquidación Exitosa</div>
        <div class="card-body text-center">
            <h5 class="card-title">Se procesaron <?= $contador_empleados ?> empleados.</h5>
            <p class="card-text">Los cálculos se han guardado correctamente.</p>
            <hr>
            <div class="d-grid gap-2">
                <a href="reportes.php?liq_id=<?= $liq_id ?>" class="btn btn-primary">Ver Recibos (Reporte)</a>
                <a href="liquidaciones.php" class="btn btn-outline-secondary">Volver al Menú</a>
            </div>
        </div>
    </div>
    <?php require_once 'include/layout_foot.php'; ?>
    </body></html>
    <?php

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error crítico en la liquidación: " . $e->getMessage());
}
?>