<?php
// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS ---
include("config/db.php");

$conexion = new mysqli($servidor, $usuario, $contrasena, $base_de_datos);
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");

// --- 2. OBTENER Y VALIDAR FILTROS ---
// Usamos $_GET para que los filtros se mantengan en la URL y al exportar
$filtro_factura = $_GET['factura'] ?? 'todos'; // 'todos', 'si', 'no'
$filtro_desde = $_GET['desde'] ?? '';
$filtro_hasta = $_GET['hasta'] ?? '';

// --- 3. CONSTRUIR CONSULTA SQL DINÁMICA ---
// Usamos 1=1 para poder añadir cláusulas AND fácilmente
$sql = "SELECT venta, fecha_venta, detalle, precio_unitario, cantidad, s_total, factura 
        FROM ventas 
        WHERE 1=1";

$tipos = ""; // Para el bind_param
$params = []; // Para el bind_param

// Añadir filtro de Factura
if ($filtro_factura == 'si') {
    $sql .= " AND factura = ?";
    $tipos .= "s";
    $params[] = "Si";
} elseif ($filtro_factura == 'no') {
    $sql .= " AND factura = ?";
    $tipos .= "s";
    $params[] = "No";
}
// (Si es 'todos', no se añade filtro)

// Añadir filtro de Fechas
if (!empty($filtro_desde)) {
    $sql .= " AND fecha_venta >= ?";
    $tipos .= "s";
    $params[] = $filtro_desde;
}
if (!empty($filtro_hasta)) {
    $sql .= " AND fecha_venta <= ?";
    $tipos .= "s";
    $params[] = $filtro_hasta;
}

// Ordenar: por Nro de Venta (más nuevas primero), y luego por ID (orden de items)
$sql .= " ORDER BY venta DESC, id ASC";

// Preparar y ejecutar la consulta
$stmt = $conexion->prepare($sql);
if (!empty($tipos)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();

$ventas_data = [];
$total_general = 0;
$total_cantidad = 0;

while ($fila = $resultado->fetch_assoc()) {
    $ventas_data[] = $fila;
    $total_general += $fila['s_total'];
    $total_cantidad += $fila['cantidad'];
}
$stmt->close();

// --- 4. ACCIÓN DE EXPORTAR A CSV (EXCEL) ---
// Comprobamos si se pasó el parámetro 'exportar' en la URL
if (isset($_GET['exportar']) && $_GET['exportar'] == 'true') {
    
    // 1. Definir cabeceras para forzar la descarga
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_ventas_' . date('Y-m-d') . '.csv"');
    
    // 2. Abrir el "archivo" de salida (la respuesta al navegador)
    $output = fopen('php://output', 'w');
    
    // 3. Poner la fila de cabecera (en español)
    // Usamos punto y coma (;) como delimitador, que Excel en español suele leer mejor
    fputcsv($output, ['Nro. Venta', 'Fecha', 'Detalle', 'P. Unitario', 'Cantidad', 'Subtotal', 'Factura'], ';');
    
    // 4. Recorrer los mismos datos que filtramos y escribirlos en el CSV
    foreach ($ventas_data as $venta) {
        // Convertir puntos a comas para decimales, como en tus archivos
        $venta['precio_unitario'] = str_replace('.', ',', $venta['precio_unitario']);
        $venta['s_total'] = str_replace('.', ',', $venta['s_total']);
        
        fputcsv($output, [
            $venta['venta'],
            date('d/m/Y', strtotime($venta['fecha_venta'])), // Formatear fecha
            $venta['detalle'],
            $venta['precio_unitario'],
            $venta['cantidad'],
            $venta['s_total'],
            $venta['factura']
        ], ';');
    }
    
    // 5. Cerrar el archivo y terminar el script (IMPORTANTE: no debe imprimir HTML)
    fclose($output);
    $conexion->close();
    exit;
}

// Si no se está exportando, continuamos y mostramos el HTML
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Ventas - Cotillón Nubes Blancas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                },
            },
        }
    </script>
</head>
<body class="bg-gray-100 font-sans">

    <div class="container mx-auto max-w-7xl p-4 md:p-8">
        
        <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
            <h1 class="text-3xl font-bold text-blue-900">Control de Ventas</h1>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors">&larr; Volver al inicio</a>
        </header>

        <!-- Formulario de Filtros -->
        <div class="bg-white p-6 rounded-xl shadow-lg mb-6">
            <form method="GET" action="control.php">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    
                    <div>
                        <label for="desde" class="block text-sm font-medium text-gray-700">Desde</label>
                        <input type="date" name="desde" id="desde" 
                               value="<?php echo htmlspecialchars($filtro_desde); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    
                    <div>
                        <label for="hasta" class="block text-sm font-medium text-gray-700">Hasta</label>
                        <input type="date" name="hasta" id="hasta" 
                               value="<?php echo htmlspecialchars($filtro_hasta); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    
                    <div>
                        <label for="factura" class="block text-sm font-medium text-gray-700">Factura</label>
                        <select name="factura" id="factura"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="todos" <?php echo ($filtro_factura == 'todos') ? 'selected' : ''; ?>>Todas</option>
                            <option value="si" <?php echo ($filtro_factura == 'si') ? 'selected' : ''; ?>>Sí</option>
                            <option value="no" <?php echo ($filtro_factura == 'no') ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    
                    <button type"submit" 
                            class="col-span-1 md:col-span-1 bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all">
                        Filtrar
                    </button>

                    <!-- Botón de Exportar: es un enlace (<a>) que añade 'exportar=true' a la URL actual con los filtros -->
                    <a href="control.php?exportar=true&amp;desde=<?php echo htmlspecialchars($filtro_desde); ?>&amp;hasta=<?php echo htmlspecialchars($filtro_hasta); ?>&amp;factura=<?php echo htmlspecialchars($filtro_factura); ?>"
                       class="col-span-1 md:col-span-1 bg-green-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all text-center">
                        Exportar (CSV)
                    </a>
                    
                </div>
            </form>
        </div>

        <!-- Tabla de Resultados -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nro. Venta</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle</th>
                            <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">P. Unit.</th>
                            <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                            <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                            <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Factura</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($ventas_data)): ?>
                            <tr>
                                <td colspan="7" class="p-6 text-center text-gray-500">No se encontraron ventas con los filtros seleccionados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ventas_data as $venta): ?>
                                <tr>
                                    <td class="py-4 px-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $venta['venta']; ?>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?>
                                    </td>
                                    <td class="py-4 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($venta['detalle']); ?></td>
                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 text-right">$<?php echo number_format($venta['precio_unitario'], 2, ',', '.'); ?></td>
                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium"><?php echo $venta['cantidad']; ?></td>
                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">$<?php echo number_format($venta['s_total'], 2, ',', '.'); ?></td>
                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-center text-gray-900">
                                        <?php echo htmlspecialchars($venta['factura']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <!-- Fila de Totales -->
                    <tfoot class="bg-gray-100 border-t-2 border-gray-300">
                        <tr>
                            <td colspan="4" class="py-3 px-4 text-right text-sm font-bold text-gray-700 uppercase">Totales Filtrados:</td>
                            <td class="py-3 px-4 text-right text-sm font-bold text-gray-900"><?php echo $total_cantidad; ?> items</td>
                            <td class="py-3 px-4 text-right text-base font-extrabold text-blue-800">$<?php echo number_format($total_general, 2, ',', '.'); ?></td>
                            <td class="py-3 px-4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
    </div> <!-- fin container -->
</body>
</html>