<?php
// Iniciar la sesión para poder usar mensajes flash (de éxito o error)
session_start();

// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS ---
include("config/db.php");

// Crear conexión
$conexion = new mysqli($servidor, $usuario, $contrasena, $base_de_datos);

// Verificar conexión
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
// Establecer el charset a utf8mb4
$conexion->set_charset("utf8mb4");

// --- 2. VARIABLES Y FUNCIONES DE MENSAJES ---

$producto_encontrado = null; 
$codigo_buscado = "";       

/**
 * Función para establecer un mensaje flash
 */
function set_mensaje($tipo, $texto, $contexto) {
    $_SESSION['mensaje'] = ['tipo' => $tipo, 'texto' => $texto, 'contexto' => $contexto];
}

/**
 * Función para obtener y mostrar un mensaje flash
 */
function get_mensaje($contexto) {
    if (isset($_SESSION['mensaje']) && $_SESSION['mensaje']['contexto'] == $contexto) {
        $mensaje = $_SESSION['mensaje'];
        unset($_SESSION['mensaje']); 
        
        $clase_color = $mensaje['tipo'] == 'exito' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
        
        echo "<div class='{$clase_color} p-4 rounded-md mb-6' role='alert'>
                <span class='font-medium'>" . htmlspecialchars($mensaje['texto']) . "</span>
              </div>";
    }
}


// --- 3. LÓGICA DE PROCESAMIENTO (POST REQUESTS) ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- ACCIÓN: SUBIR ARCHIVO CSV (SIN CAMBIOS, ASUME GANANCIA DEFAULT DE BD) ---
    if (isset($_POST['accion']) && $_POST['accion'] == 'subir_csv') {
        
        if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == UPLOAD_ERR_OK) {
            $archivo_tmp = $_FILES['archivo_csv']['tmp_name'];
            
            $filas_insertadas = 0;
            $filas_actualizadas = 0;
            $filas_omitidas = 0;
            
            $conexion->begin_transaction();
            
            try {
                if (($gestor = fopen($archivo_tmp, "r")) !== FALSE) {
                    
                    fgetcsv($gestor, 1000, ";"); // Omitir cabecera
                    
                    // Consultas preparadas (NO tocamos ganancia aquí, dejamos que la BD use el default)
                    $stmt_check = $conexion->prepare("SELECT id FROM precios WHERE codigo = ?");
                    $stmt_insert = $conexion->prepare("INSERT INTO precios (fecha_compra, cantidad, codigo, detalle, compra, dividido) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_update = $conexion->prepare("UPDATE precios SET fecha_compra = ?, cantidad = ?, detalle = ?, compra = ?, dividido = ? WHERE codigo = ?");
                    $stmt_set_orden = $conexion->prepare("UPDATE precios SET orden = ? WHERE id = ?");

                    while (($datos = fgetcsv($gestor, 1000, ";")) !== FALSE) {
                        $num_columnas = count($datos);
                        if ($num_columnas == 8 && empty(trim($datos[7]))) {
                            array_pop($datos);
                            $num_columnas = 7;
                        }
                        
                        if (count($datos) != 7) {
                            $filas_omitidas++;
                            continue; 
                        }

                        $fecha_compra = $datos[1];
                        $cantidad = (int)$datos[2];
                        $codigo = trim($datos[3]);
                        $detalle = $datos[4];
                        $compra_str = str_replace(',', '.', $datos[5]);
                        $compra = (float)$compra_str;
                        $dividido = (int)$datos[6];

                        if (empty($codigo)) {
                            $filas_omitidas++;
                            continue;
                        }

                        // Verificar existencia
                        $stmt_check->bind_param("s", $codigo);
                        $stmt_check->execute();
                        $resultado_check = $stmt_check->get_result();

                        if ($resultado_check->num_rows > 0) {
                            // ACTUALIZAR (Mantiene la ganancia que ya tenga el producto en la BD)
                            $stmt_update->bind_param("sisdis", $fecha_compra, $cantidad, $detalle, $compra, $dividido, $codigo);
                            $stmt_update->execute();
                            $filas_actualizadas++;
                        } else {
                            // INSERTAR (La BD pondrá ganancia = 100 por defecto)
                            $stmt_insert->bind_param("sissdi", $fecha_compra, $cantidad, $codigo, $detalle, $compra, $dividido);
                            $stmt_insert->execute();
                            
                            $nuevo_id = $conexion->insert_id;
                            $stmt_set_orden->bind_param("ii", $nuevo_id, $nuevo_id);
                            $stmt_set_orden->execute();
                            
                            $filas_insertadas++;
                        }
                    }
                    fclose($gestor);
                    $stmt_check->close();
                    $stmt_insert->close();
                    $stmt_update->close();
                    $stmt_set_orden->close();
                    
                    $conexion->commit();
                    set_mensaje('exito', "Actualización por CSV completada. Insertados: $filas_insertadas. Actualizados: $filas_actualizadas. Omitidos: $filas_omitidas.", 'csv');
                    
                } else {
                    throw new Exception('No se pudo abrir el archivo CSV.');
                }
            } catch (Exception $e) {
                $conexion->rollback();
                set_mensaje('error', 'Error en la transacción: ' . $e->getMessage(), 'csv');
            }
            
        } else {
            set_mensaje('error', 'Error al subir el archivo o no se seleccionó ninguno.', 'csv');
        }
        
        header("Location: actualizar.php");
        exit;
    }
    
    // --- ACCIÓN: BUSCAR PRODUCTO MANUALMENTE ---
    if (isset($_POST['accion']) && $_POST['accion'] == 'buscar_manual') {
        $codigo_buscado = trim($_POST['codigo_buscar']);
        
        if (!empty($codigo_buscado)) {
            // Buscamos todos los datos, incluyendo la ganancia si existe
            $stmt = $conexion->prepare("SELECT * FROM precios WHERE codigo = ?");
            $stmt->bind_param("s", $codigo_buscado);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows > 0) {
                $producto_encontrado = $resultado->fetch_assoc();
                
                // Asegurarnos de que ganancia tenga un valor si viene null
                if (!isset($producto_encontrado['ganancia'])) {
                    $producto_encontrado['ganancia'] = 100.00;
                }
                
                set_mensaje('exito', "Producto encontrado. Puedes editarlo a continuación.", 'manual');
            } else {
                set_mensaje('error', "El código '$codigo_buscado' no existe. Puedes agregarlo como un nuevo producto.", 'manual');
                $producto_encontrado = [
                    'id' => '', 
                    'codigo' => $codigo_buscado,
                    'fecha_compra' => date('d/m/Y'),
                    'cantidad' => 0,
                    'detalle' => '',
                    'compra' => 0.00,
                    'dividido' => 1,
                    'ganancia' => 100.00 // Default para nuevos
                ];
            }
            $stmt->close();
        } else {
            set_mensaje('error', "Por favor, ingresa un código para buscar.", 'manual');
        }
    }

    // --- ACCIÓN: GUARDAR (INSERTAR O ACTUALIZAR) MANUALMENTE ---
    if (isset($_POST['accion']) && $_POST['accion'] == 'guardar_manual') {
        $id = $_POST['id_producto']; 
        $codigo = trim($_POST['codigo']);
        $fecha_compra = $_POST['fecha_compra'];
        $cantidad = (int)$_POST['cantidad'];
        $detalle = trim($_POST['detalle']);
        
        // Limpieza de precio compra
        $compra_clean = str_replace('.', '', $_POST['compra']); 
        $compra_clean = str_replace(',', '.', $compra_clean);   
        $compra = (float)$compra_clean;
        
        $dividido = (int)$_POST['dividido'];

        // --- NUEVO: Obtener Ganancia ---
        $ganancia = isset($_POST['ganancia']) ? (float)$_POST['ganancia'] : 100.00; 

        if (empty($codigo) || empty($detalle)) {
            set_mensaje('error', 'El Código y el Detalle son campos obligatorios.', 'manual');
            $producto_encontrado = $_POST; 
            $producto_encontrado['id'] = $id; 
        } else {
            
            if (empty($id)) {
                // --- INSERTAR NUEVO (Con Ganancia) ---
                $stmt_check = $conexion->prepare("SELECT id FROM precios WHERE codigo = ?");
                $stmt_check->bind_param("s", $codigo);
                $stmt_check->execute();
                $resultado_check = $stmt_check->get_result();
                
                if ($resultado_check->num_rows > 0) {
                    set_mensaje('error', "Error al guardar: El código '$codigo' ya existe en la base de datos.", 'manual');
                    $producto_encontrado = $_POST; 
                    $producto_encontrado['id'] = ''; 
                } else {
                    // Agregamos columna ganancia y tipo 'd' en bind
                    $stmt_insert = $conexion->prepare("INSERT INTO precios (fecha_compra, cantidad, codigo, detalle, compra, dividido, ganancia) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("sissdid", $fecha_compra, $cantidad, $codigo, $detalle, $compra, $dividido, $ganancia);
                    $stmt_insert->execute();
                    
                    $nuevo_id = $conexion->insert_id;
                    $stmt_set_orden = $conexion->prepare("UPDATE precios SET orden = ? WHERE id = ?");
                    $stmt_set_orden->bind_param("ii", $nuevo_id, $nuevo_id);
                    $stmt_set_orden->execute();
                    $stmt_set_orden->close();
                    
                    $stmt_insert->close();
                    set_mensaje('exito', "Producto '$codigo' agregado correctamente.", 'manual');
                    header("Location: actualizar.php"); 
                    exit;
                }
                $stmt_check->close();
                
            } else {
                // --- ACTUALIZAR EXISTENTE (Con Ganancia) ---
                $stmt_update = $conexion->prepare("UPDATE precios SET fecha_compra = ?, cantidad = ?, codigo = ?, detalle = ?, compra = ?, dividido = ?, ganancia = ? WHERE id = ?");
                // s=string, i=int, d=double. Orden: fecha, cant, cod, det, compra, div, ganancia, id
                $stmt_update->bind_param("sissdidi", $fecha_compra, $cantidad, $codigo, $detalle, $compra, $dividido, $ganancia, $id);
                $stmt_update->execute();
                $stmt_update->close();
                set_mensaje('exito', "Producto '$codigo' (ID: $id) actualizado correctamente.", 'manual');
                header("Location: actualizar.php"); 
                exit;
            }
        }
    }
} 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Precios - Cotillón Nubes Blancas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        'azul-cotillon': {
                            100: '#E6F0FF', 300: '#B0D0FF', 500: '#007BFF', 600: '#006AE1', 700: '#0058C0', 900: '#003D82',
                        }
                    }
                },
            },
        }
    </script>
</head>
<body class="bg-gray-100 font-sans">
    <nav class="relative bg-blue-800/50 after:pointer-events-none after:absolute after:inset-x-0 after:bottom-0 after:h-px after:bg-white/10">
      <div class="mx-auto max-w-7xl px-2 sm:px-6 lg:px-8">
        <div class="relative flex h-16 items-center justify-between">
          <div class="flex flex-1 items-center justify-center sm:items-stretch sm:justify-start">
            <div class="flex shrink-0 items-center">
              <img src="config/logo.png" alt="Nubes Blancas" class="h-8 w-auto" />
            </div>
            <div class="hidden sm:ml-6 sm:block">
              <div class="flex space-x-4">
                <a href="index.php" class="rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-white/5 hover:text-white">Inicio</a>
                <a href="venta.php" class="rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-white/5 hover:text-white">Venta</a>
                <a href="#" aria-current="page" class="rounded-md bg-gray-950/50 px-3 py-2 text-sm font-medium text-white">Actualizar</a>
                <a href="contable.php" class="rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-white/5 hover:text-white">Contable</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>
    <div class="container mx-auto max-w-6xl p-4 md:p-8">
        
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-blue-900">Actualizar Precios y Productos</h1>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors duration-200">&larr; Volver al inicio</a>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">Actualización por Lotes (CSV)</h2>
                <?php get_mensaje('csv'); ?>
                <form action="actualizar.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="subir_csv">
                    <div>
                        <label for="archivo_csv" class="block text-sm font-medium text-gray-700 mb-2">Seleccionar archivo CSV</label>
                        <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required 
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none file:bg-blue-600 file:text-white file:border-0 file:py-2 file:px-4 file:mr-4 file:hover:bg-blue-700">
                    </div>
                    <button type="submit" class="w-full mt-4 bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:bg-blue-700 transition-all">
                        Subir y Procesar CSV
                    </button>
                </form>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="font-semibold text-blue-800">Instrucciones:</h4>
                    <ul class="list-disc list-inside text-sm text-blue-700 mt-2 space-y-1">
                        <li>Formato `.csv` separado por punto y coma (`;`).</li>
                        <li>Precios con coma decimal (`,`).</li>
                        <li>La actualización por CSV mantiene la Ganancia actual del producto.</li>
                    </ul>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">Actualización Manual</h2>

                <?php get_mensaje('manual'); ?>

                <form action="actualizar.php" method="post" class="mb-6 pb-6 border-b border-gray-200">
                    <input type="hidden" name="accion" value="buscar_manual">
                    <label for="codigo_buscar" class="block text-sm font-medium text-gray-700">Buscar producto por código</label>
                    <div class="mt-2 flex">
                        <input type="text" name="codigo_buscar" id="codigo_buscar" 
                               value="<?php echo htmlspecialchars($codigo_buscado); ?>"
                               class="flex-grow block w-full rounded-l-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                               placeholder="Ingrese un código...">
                        <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-r-md hover:bg-blue-700 transition-all">
                            Buscar
                        </button>
                    </div>
                </form>

                <?php if ($producto_encontrado !== null): 
                    $es_nuevo = empty($producto_encontrado['id']); 
                ?>
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">
                        <?php echo $es_nuevo ? 'Agregar Nuevo Producto' : 'Editando Producto'; ?>
                    </h3>
                
                    <form action="actualizar.php" method="post" class="space-y-4">
                        <input type="hidden" name="accion" value="guardar_manual">
                        <input type="hidden" name="id_producto" value="<?php echo htmlspecialchars($producto_encontrado['id'] ?? ''); ?>">

                        <div>
                            <label for="codigo" class="block text-sm font-medium text-gray-700">Código</label>
                            <input type="text" name="codigo" id="codigo" required
                                   value="<?php echo htmlspecialchars($producto_encontrado['codigo'] ?? ''); ?>"
                                   <?php if (!$es_nuevo): ?>readonly<?php endif; ?> 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm <?php if (!$es_nuevo): ?>bg-gray-100 cursor-not-allowed<?php endif; ?>">
                        </div>

                        <div>
                            <label for="detalle" class="block text-sm font-medium text-gray-700">Detalle</label>
                            <textarea name="detalle" id="detalle" rows="3" required 
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"><?php echo htmlspecialchars($producto_encontrado['detalle'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <h4 class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">Precios y Ganancia</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label for="compra" class="block text-sm font-medium text-gray-700">Precio Compra</label>
                                    <input type="text" name="compra" id="compra" required
                                           value="<?php echo htmlspecialchars(number_format($producto_encontrado['compra'] ?? 0.0, 2, ',', '.')); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                           placeholder="Ej: 1250,50">
                                </div>

                                <div>
                                    <label for="ganancia" class="block text-sm font-medium text-gray-700">% Ganancia</label>
                                    <div class="relative mt-1 rounded-md shadow-sm">
                                        <input type="number" name="ganancia" id="ganancia" required step="0.01"
                                               value="<?php echo htmlspecialchars($producto_encontrado['ganancia'] ?? 100); ?>"
                                               class="block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm text-center">
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                            <span class="text-gray-500 sm:text-sm">%</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="dividido" class="block text-sm font-medium text-gray-700">Dividido</label>
                                    <input type="number" name="dividido" id="dividido" required min="1"
                                           value="<?php echo htmlspecialchars($producto_encontrado['dividido'] ?? 1); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm text-center">
                                </div>
                                <div>
                                    <label for="precio_calculado" class="block text-sm font-bold text-blue-800">Precio Venta (Est.)</label>
                                    <input type="text" id="precio_calculado" readonly
                                           class="mt-1 block w-full rounded-md border-blue-300 bg-blue-50 text-blue-800 font-bold shadow-sm sm:text-sm cursor-default focus:ring-0">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="cantidad" class="block text-sm font-medium text-gray-700">Cantidad (Stock)</label>
                                <input type="number" name="cantidad" id="cantidad" required
                                       value="<?php echo htmlspecialchars($producto_encontrado['cantidad'] ?? 0); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="fecha_compra" class="block text-sm font-medium text-gray-700">Fecha Compra</label>
                                <input type="text" name="fecha_compra" id="fecha_compra" 
                                       value="<?php echo htmlspecialchars($producto_encontrado['fecha_compra'] ?? date('d/m/Y')); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                       placeholder="dd/mm/aaaa">
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-all">
                                <?php echo $es_nuevo ? 'Agregar Nuevo Producto' : 'Actualizar Producto'; ?>
                            </button>
                        </div>
                        
                    </form>
                <?php endif; ?>
            </div>

        </div> 
    </div> 

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            const inputCompra = document.getElementById('compra');
            const inputDividido = document.getElementById('dividido');
            const inputGanancia = document.getElementById('ganancia'); // REFERENCIA NUEVA
            const inputResultado = document.getElementById('precio_calculado');

            if (inputCompra && inputDividido && inputGanancia && inputResultado) {

                function calcularPrecioVenta() {
                    let valorCompraStr = inputCompra.value;
                    valorCompraStr = valorCompraStr.replace(/\./g, '');
                    valorCompraStr = valorCompraStr.replace(',', '.');
                    const precioCompra = parseFloat(valorCompraStr);

                    const dividido = parseInt(inputDividido.value) || 1;
                    const porcentajeGanancia = parseFloat(inputGanancia.value) || 100; // Tomar ganancia

                    if (!isNaN(precioCompra) && dividido > 0) {
                        
                        // FÓRMULA DINÁMICA
                        // Factor ganancia: 100% -> 2, 50% -> 1.5
                        const factorGanancia = 1 + (porcentajeGanancia / 100);
                        
                        const calculoBase = (precioCompra / dividido) * factorGanancia * 1.105;
                        const precioFinal = Math.ceil(calculoBase / 10) * 10;

                        inputResultado.value = '$ ' + precioFinal.toFixed(2);
                    } else {
                        inputResultado.value = '$ 0.00';
                    }
                }

                inputCompra.addEventListener('input', calcularPrecioVenta);
                inputDividido.addEventListener('input', calcularPrecioVenta);
                inputGanancia.addEventListener('input', calcularPrecioVenta); // LISTENER NUEVO

                calcularPrecioVenta();
            }
        });
    </script>

<?php
$conexion->close();
?>
</body>
</html>