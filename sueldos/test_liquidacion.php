<?php
// test_liquidacion.php
require_once 'Concepto.php';
require_once 'MotorDeCalculo.php';

// 1. Simulamos datos que vendrían de la DB (Tablas empleados y categorias)
$datosEmpleado = [
    'BASICO' => 850000.00,  // Sueldo Básico de Convenio
    'ANTIGUEDAD' => 5,      // Años de antigüedad
    'PRESENTISMO_PCT' => 0.0833 // 8.33% (Comercio)
];

// 2. Instanciamos el Motor
$motor = new MotorDeCalculo();
$motor->cargarContexto($datosEmpleado);

echo "--- Iniciando Liquidación de Prueba ---\n";
echo "Contexto Inicial: Básico $" . number_format($datosEmpleado['BASICO'], 2) . "\n\n";

// 3. Definimos Conceptos (Simulando tabla 'conceptos')
// Fíjate cómo la fórmula es TEXTO plano.

$c1_antiguedad = new Concepto(
    "Antigüedad (1% por año)", 
    "0200", 
    "REM", 
    "BASICO * 0.01 * ANTIGUEDAD" // Fórmula Lógica [cite: 20]
);

$c2_presentismo = new Concepto(
    "Presentismo (s/ Básico + Antig)", 
    "0300", 
    "REM", 
    "(BASICO + TOTAL_ANTIGUEDAD) * PRESENTISMO_PCT" // Dependencia compleja [cite: 33]
);

$c3_jubilacion = new Concepto(
    "Jubilación (11%)", 
    "0400", 
    "DES", 
    "TOTAL_REMUNERATIVO * 0.11" // Usa un acumulador [cite: 30]
);

// --- EJECUCIÓN (El Loop de Liquidación) ---

// Paso 1: Calcular Antigüedad
$valorAntiguedad = $motor->resolver($c1_antiguedad);
echo "1. " . $c1_antiguedad->nombre . ": \t\t$ " . number_format($valorAntiguedad, 2) . "\n";

// IMPORTANTE: Agregamos el resultado al contexto para que el siguiente concepto lo pueda usar
$motor->agregarAlContexto('TOTAL_ANTIGUEDAD', $valorAntiguedad);


// Paso 2: Calcular Presentismo (Que depende de Antigüedad)
$valorPresentismo = $motor->resolver($c2_presentismo);
echo "2. " . $c2_presentismo->nombre . ": \t$ " . number_format($valorPresentismo, 2) . "\n";


// Paso 3: Calcular Subtotales para Retenciones
$totalRemunerativo = $datosEmpleado['BASICO'] + $valorAntiguedad + $valorPresentismo;
$motor->agregarAlContexto('TOTAL_REMUNERATIVO', $totalRemunerativo);

echo "----------------------------------------\n";
echo "   SUBTOTAL REMUNERATIVO: \t$ " . number_format($totalRemunerativo, 2) . "\n";
echo "----------------------------------------\n";


// Paso 4: Calcular Descuentos (Jubilación)
$valorJubilacion = $motor->resolver($c3_jubilacion);
echo "3. " . $c3_jubilacion->nombre . ": \t\t$ -" . number_format($valorJubilacion, 2) . "\n";


// Resultado Final
$neto = $totalRemunerativo - $valorJubilacion;
echo "\n========================================\n";
echo "   NETO A COBRAR: \t\t$ " . number_format($neto, 2) . "\n";
echo "========================================\n";
?>