<?php
// liquidar_empleado.php
require_once 'include/db.php';
require_once 'Concepto.php';
require_once 'MotorDeCalculo.php';

// --- CONFIGURACIÓN DE LA PRUEBA ---
// En un sistema real, estos ID vendrían de un formulario (GET/POST)
// Asumimos que ya insertaste manualmente un tenant y un empleado en tu BD.
$tenant_id_prueba = 'uuid-tenant-ejemplo-1'; 
$empleado_id_prueba = 'uuid-empleado-ejemplo-1'; 
$periodo_prueba = '202512'; 

echo "<h1>Liquidación Período $periodo_prueba</h1>";

try {
    // 1. OBTENER DATOS DEL EMPLEADO (Contexto Fijo)
    // Hacemos JOIN con categorias para sacar el sueldo básico real
    $sqlEmpleado = "
        SELECT 
            e.id, e.nombre, e.fecha_ingreso,
            c.sueldo_basico as BASICO
        FROM empleados e
        JOIN categorias c ON e.categoria_id = c.id
        WHERE e.id = :id AND e.tenant_id = :tenant_id
    ";
    
    $stmt = $pdo->prepare($sqlEmpleado);
    $stmt->execute(['id' => $empleado_id_prueba, 'tenant_id' => $tenant_id_prueba]);
    $empleadoData = $stmt->fetch();

    if (!$empleadoData) {
        die("Error: Empleado no encontrado.");
    }

    // Calcular antigüedad en años (Lógica simple para PHP)
    $fechaIngreso = new DateTime($empleadoData['fecha_ingreso']);
    $hoy = new DateTime(); // O la fecha de cierre de liquidación
    $antiguedad = $hoy->diff($fechaIngreso)->y;

    // PREPARAR CONTEXTO PARA EL MOTOR
    $contexto = [
        'BASICO' => $empleadoData['BASICO'],
        'ANTIGUEDAD' => $antiguedad
    ];

    // 2. BUSCAR NOVEDADES DEL MES (Variables)
    // Ej: Horas extras cargadas en la tabla 'novedades'
    // Si hay una novedad para un concepto, guardamos la CANTIDAD en el contexto
    // Ej: Si el concepto es 'HORAS_EXTRAS_50', guardamos CANTIDAD = 5
    $sqlNovedades = "
        SELECT c.codigo, n.cantidad
        FROM novedades n
        JOIN conceptos c ON n.concepto_id = c.id
        WHERE n.empleado_id = :emp_id AND n.periodo = :periodo
    ";
    $stmtNov = $pdo->prepare($sqlNovedades);
    $stmtNov->execute(['emp_id' => $empleado_id_prueba, 'periodo' => $periodo_prueba]);
    $novedades = $stmtNov->fetchAll();

    foreach ($novedades as $nov) {
        // Agregamos al contexto. Ej: $contexto['HE_50_CANT'] = 10;
        // Asumimos que tu fórmula usa 'CODIGO_CANT' para saber la cantidad
        $contexto[$nov['codigo'] . '_CANT'] = $nov['cantidad'];
    }

    echo "<pre>Contexto Cargado: " . print_r($contexto, true) . "</pre>";

    // 3. OBTENER CONCEPTOS ACTIVOS
    // Traemos los globales (tenant_id IS NULL) Y los de este cliente
    // Ordenamos por orden_calculo para resolver dependencias (Rem -> No Rem -> Desc)
    $sqlConceptos = "
        SELECT * FROM conceptos 
        WHERE (tenant_id = :tenant_id OR tenant_id IS NULL) AND activo = 1
        ORDER BY orden_calculo ASC
    ";
    
    $stmtConc = $pdo->prepare($sqlConceptos);
    $stmtConc->execute(['tenant_id' => $tenant_id_prueba]);
    $conceptosDB = $stmtConc->fetchAll();

    // 4. INICIALIZAR MOTOR Y EJECUTAR
    $motor = new MotorDeCalculo();
    $motor->cargarContexto($contexto);

    echo "<table border='1' cellpadding='5'>
            <tr><th>Cód</th><th>Concepto</th><th>Unidades</th><th>Haberes</th><th>Deducciones</th></tr>";

    $totalRemunerativo = 0;
    $totalDescuentos = 0;

    foreach ($conceptosDB as $row) {
        // Convertimos la fila de BD a objeto Concepto
        $conceptoObj = new Concepto(
            $row['nombre'],
            $row['codigo'],
            $row['tipo'],
            $row['formula']
        );

        // ¡EL CÁLCULO!
        $valor = $motor->resolver($conceptoObj);

        // Si dio cero, generalmente no se imprime en el recibo (salvo que sea novedad en 0)
        if ($valor == 0) continue;

        // Acumular subtotales según tipo (Importante para Ganancias y Cargas Sociales)
        if ($row['tipo'] == 'REM') {
            $totalRemunerativo += $valor;
        } elseif ($row['tipo'] == 'DES') {
            $totalDescuentos += $valor;
        }

        // Actualizar contexto para fórmulas siguientes (Ej: Jubilación necesita Total Rem)
        // Agregamos el resultado usando el CODIGO del concepto como variable
        // Ej: Si el básico es código 0100, ahora existe la variable $contexto['C0100'] = 500000
        $motor->agregarAlContexto('TOTAL_REMUNERATIVO', $totalRemunerativo); // Actualizamos el acumulador global
        $motor->agregarAlContexto('C' . $row['codigo'], $valor); // Guardamos resultado individual

        // Renderizar fila HTML
        $columnaHaber = ($row['tipo'] != 'DES') ? $valor : "";
        $columnaDesc  = ($row['tipo'] == 'DES') ? $valor : "";
        
        echo "<tr>
                <td>{$row['codigo']}</td>
                <td>{$row['nombre']}</td>
                <td></td> <td align='right'>$columnaHaber</td>
                <td align='right'>$columnaDesc</td>
              </tr>";
    }

    $neto = $totalRemunerativo - $totalDescuentos; // (Falta No Remunerativos en este ejemplo simple)

    echo "<tr><td colspan='3' align='right'><b>NETO:</b></td><td colspan='2' align='center'><b>$ " . number_format($neto, 2) . "</b></td></tr>";
    echo "</table>";

} catch (Exception $e) {
    echo "Error Crítico: " . $e->getMessage();
}
?>