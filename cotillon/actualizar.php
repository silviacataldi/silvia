<?php
// Iniciar la sesión para poder usar mensajes flash (de éxito o error)
session_start();

// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS (XAMPP) ---
include("config/db.php");

// Crear conexión
$conexion = new mysqli($servidor, $usuario, $contrasena, $base_de_datos);

// Verificar conexión
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
// Establecer el charset a utf8mb4 para manejar acentos y caracteres especiales
$conexion->set_charset("utf8mb4");

// --- 2. VARIABLES Y FUNCIONES DE MENSAJES ---

// Variables para el formulario manual
$producto_encontrado = null; // Almacenará el producto buscado
$codigo_buscado = "";       // Almacenará el código que el usuario buscó

/**
 * Función para establecer un mensaje flash en la sesión.
 * @param string $tipo 'exito' o 'error'
 * @param string $texto El mensaje a mostrar
 * @param string $contexto 'csv' o 'manual', para saber dónde mostrar el mensaje
 */
function set_mensaje($tipo, $texto, $contexto) {
    $_SESSION['mensaje'] = ['tipo' => $tipo, 'texto' => $texto, 'contexto' => $contexto];
}

/**
 * Función para obtener y mostrar un mensaje flash, y luego limpiarlo.
 * @param string $contexto 'csv' o 'manual'
 */
function get_mensaje($contexto) {
    if (isset($_SESSION['mensaje']) && $_SESSION['mensaje']['contexto'] == $contexto) {
        $mensaje = $_SESSION['mensaje'];
        unset($_SESSION['mensaje']); // Limpiar después de mostrar
        
        // Determinar el color del mensaje basado en el tipo
        $clase_color = $mensaje['tipo'] == 'exito' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
        
        echo "<div class='{$clase_color} p-4 rounded-md mb-6' role='alert'>
                <span class='font-medium'>" . htmlspecialchars($mensaje['texto']) . "</span>
              </div>";
    }
}


// --- 3. LÓGICA DE PROCESAMIENTO (POST REQUESTS) ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- ACCIÓN: SUBIR ARCHIVO CSV ---
    if (isset($_POST['accion']) && $_POST['accion'] == 'subir_csv') {
        
        if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == UPLOAD_ERR_OK) {
            $archivo_tmp = $_FILES['archivo_csv']['tmp_name'];
            
            $filas_insertadas = 0;
            $filas_actualizadas = 0;
            $filas_omitidas = 0;
            
            // Usamos transacciones para que, si algo falla, no se haga nada.
            $conexion->begin_transaction();
            
            try {
                if (($gestor = fopen($archivo_tmp, "r")) !== FALSE) {
                    
                    // Omitir la cabecera (primera fila) del CSV
                    fgetcsv($gestor, 1000, ";");
                    
                    // Preparar sentencias SQL para eficiencia (fuera del bucle)
                    $stmt_check = $conexion->prepare("SELECT id FROM precios WHERE codigo = ?");
                    // 'orden' quitado de INSERT
                    $stmt_insert = $conexion->prepare("INSERT INTO precios (fecha_compra, cantidad, codigo, detalle, compra, dividido) VALUES (?, ?, ?, ?, ?, ?)");
                    // 'orden' quitado de UPDATE
                    $stmt_update = $conexion->prepare("UPDATE precios SET fecha_compra = ?, cantidad = ?, detalle = ?, compra = ?, dividido = ? WHERE codigo = ?");
                    // Nueva sentencia para setear 'orden' después de insertar
                    $stmt_set_orden = $conexion->prepare("UPDATE precios SET orden = ? WHERE id = ?");

                    while (($datos = fgetcsv($gestor, 1000, ";")) !== FALSE) {
                        
                        // Asegurarse de que la fila tiene las 7 columnas esperadas
                        if (count($datos) != 7) {
                            $filas_omitidas++;
                            continue; // Saltar esta fila
                        }

                        // Asignar datos del CSV a variables (ignorando $datos[0] - orden)
                        $fecha_compra = $datos[1];
                        $cantidad = (int)$datos[2];
                        $codigo = trim($datos[3]); // Limpiar espacios
                        $detalle = $datos[4];
                        
                        // ¡Importante! Convertir la coma decimal (ej: "2352,75") a punto decimal (ej: "2352.75")
                        $compra_str = str_replace(',', '.', $datos[5]);
                        $compra = (float)$compra_str;
                        
                        $dividido = (int)$datos[6];

                        // Validar que el código no esté vacío
                        if (empty($codigo)) {
                            $filas_omitidas++;
                            continue;
                        }

                        // 1. Verificar si el código existe
                        $stmt_check->bind_param("s", $codigo);
                        $stmt_check->execute();
                        $resultado_check = $stmt_check->get_result();

                        if ($resultado_check->num_rows > 0) {
                            // 2. Si existe, ACTUALIZAR (sin 'orden')
                            // CORRECCIÓN: 'sisdds' -> 'sisdis' (dividido es 'i', no 'd')
                            $stmt_update->bind_param("sisdis", $fecha_compra, $cantidad, $detalle, $compra, $dividido, $codigo);
                            $stmt_update->execute();
                            $filas_actualizadas++;
                        } else {
                            // 3. Si no existe, INSERTAR (sin 'orden')
                            // CORRECCIÓN: 'sisddi' -> 'sissdi' (detalle es 's', no 'd')
                            $stmt_insert->bind_param("sissdi", $fecha_compra, $cantidad, $codigo, $detalle, $compra, $dividido);
                            $stmt_insert->execute();
                            
                            // 3b. Obtener el nuevo ID y usarlo para setear 'orden'
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
                    $stmt_set_orden->close(); // Cerrar la nueva sentencia
                    
                    // Si todo fue bien, confirmar los cambios
                    $conexion->commit();
                    set_mensaje('exito', "Actualización por CSV completada. Insertados: $filas_insertadas. Actualizados: $filas_actualizadas. Omitidos: $filas_omitidas.", 'csv');
                    
                } else {
                    throw new Exception('No se pudo abrir el archivo CSV.');
                }
            } catch (Exception $e) {
                // Si algo falló, revertir todos los cambios
                $conexion->rollback();
                set_mensaje('error', 'Error en la transacción: ' . $e->getMessage(), 'csv');
            }
            
        } else {
            set_mensaje('error', 'Error al subir el archivo o no se seleccionó ninguno.', 'csv');
        }
        
        // Redirigir para evitar re-envío del formulario (Patrón Post-Redirect-Get)
        header("Location: actualizar.php");
        exit;
    }
    
    // --- ACCIÓN: BUSCAR PRODUCTO MANUALMENTE ---
    if (isset($_POST['accion']) && $_POST['accion'] == 'buscar_manual') {
        $codigo_buscado = trim($_POST['codigo_buscar']);
        
        if (!empty($codigo_buscado)) {
            $stmt = $conexion->prepare("SELECT * FROM precios WHERE codigo = ?");
            $stmt->bind_param("s", $codigo_buscado);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows > 0) {
                // Producto encontrado, cargamos sus datos
                $producto_encontrado = $resultado->fetch_assoc();
                set_mensaje('exito', "Producto encontrado. Puedes editarlo a continuación.", 'manual');
            } else {
                // Producto NO encontrado, preparamos el formulario para AGREGAR
                set_mensaje('error', "El código '$codigo_buscado' no existe. Puedes agregarlo como un nuevo producto.", 'manual');
                // Pre-llenar el formulario con el código buscado y valores por defecto
                $producto_encontrado = [
                'id' => '', // ID vacío significa "nuevo"
                'codigo' => $codigo_buscado,
                // 'orden' => 0, // Quitado
                'fecha_compra' => date('d/m/Y'), // Fecha de hoy
                'cantidad' => 0,
                    'detalle' => '',
                    'compra' => 0.00,
                    'dividido' => 1
                ];
            }
            $stmt->close();
        } else {
            set_mensaje('error', "Por favor, ingresa un código para buscar.", 'manual');
        }
    }

    // --- ACCIÓN: GUARDAR (INSERTAR O ACTUALIZAR) MANUALMENTE ---
    if (isset($_POST['accion']) && $_POST['accion'] == 'guardar_manual') {
        // Recoger todos los datos del formulario manual
        $id = $_POST['id_producto']; // Será '' si es nuevo, o un número si es existente
        $codigo = trim($_POST['codigo']);
        // $orden = (int)$_POST['orden']; // Quitado
        $fecha_compra = $_POST['fecha_compra'];
        // CORRECCIÓN: Se debe tomar de $_POST['cantidad']
        $cantidad = (int)$_POST['cantidad'];
        $detalle = trim($_POST['detalle']);
        
        // ¡Importante! Convertir coma a punto
        $compra_str = str_replace(',', '.', $_POST['compra']);
        $compra = (float)$compra_str;
        
        $dividido = (int)$_POST['dividido'];

        // Validación simple
        if (empty($codigo) || empty($detalle)) {
            set_mensaje('error', 'El Código y el Detalle son campos obligatorios.', 'manual');
            // Repoblar el formulario para que el usuario no pierda datos
            $producto_encontrado = $_POST; 
            $producto_encontrado['id'] = $id; // Asegurarse de mantener el ID
        } else {
            
            if (empty($id)) {
                // --- ES UN PRODUCTO NUEVO (INSERT) ---
                
                // Verificar de nuevo si el código ya existe (para evitar duplicados)
                $stmt_check = $conexion->prepare("SELECT id FROM precios WHERE codigo = ?");
                $stmt_check->bind_param("s", $codigo);
                $stmt_check->execute();
                $resultado_check = $stmt_check->get_result();
                
                if ($resultado_check->num_rows > 0) {
                    set_mensaje('error', "Error al guardar: El código '$codigo' ya existe en la base de datos.", 'manual');
                    $producto_encontrado = $_POST; // Repopular
                    $producto_encontrado['id'] = ''; 
                } else {
                    // Insertar (sin 'orden')
                    $stmt_insert = $conexion->prepare("INSERT INTO precios (fecha_compra, cantidad, codigo, detalle, compra, dividido) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("sissdi", $fecha_compra, $cantidad, $codigo, $detalle, $compra, $dividido);
                    $stmt_insert->execute();
                    
                    // Obtener nuevo ID y setear 'orden'
                    $nuevo_id = $conexion->insert_id;
                    $stmt_set_orden = $conexion->prepare("UPDATE precios SET orden = ? WHERE id = ?");
                    $stmt_set_orden->bind_param("ii", $nuevo_id, $nuevo_id);
                    $stmt_set_orden->execute();
                    $stmt_set_orden->close();
                    
                    $stmt_insert->close();
                    set_mensaje('exito', "Producto '$codigo' agregado correctamente.", 'manual');
                    header("Location: actualizar.php"); // Redirigir en éxito
                    exit;
                }
                $stmt_check->close();
                
            } else {
                // --- ES UN PRODUCTO EXISTENTE (UPDATE) ---
                // 'orden' quitado de la sentencia UPDATE
                $stmt_update = $conexion->prepare("UPDATE precios SET fecha_compra = ?, cantidad = ?, codigo = ?, detalle = ?, compra = ?, dividido = ? WHERE id = ?");
                // Ojo: pasamos 7 parámetros (6 + id al final)
                $stmt_update->bind_param("sissdii", $fecha_compra, $cantidad, $codigo, $detalle, $compra, $dividido, $id);
                $stmt_update->execute();
                $stmt_update->close();
                set_mensaje('exito', "Producto '$codigo' (ID: $id) actualizado correctamente.", 'manual');
                header("Location: actualizar.php"); // Redirigir en éxito
                exit;
            }
        }
    }
} // Fin del bloque POST

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Productos - Cotillón Nubes Blancas</title>
    <!-- Incluimos Tailwind CSS desde el CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Incluimos la fuente Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Configuración de Tailwind para usar la fuente Inter y los colores azules
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'azul-cotillon': {
                            100: '#E6F0FF',
                            300: '#B0D0FF',
                            500: '#007BFF', // Un azul principal
                            600: '#006AE1',
                            700: '#0058C0',
                            900: '#003D82', // Azul oscuro para textos
                        }
                    }
                },
            },
        }
    </script>
</head>
<body class="bg-gray-100 font-sans">

    <div class="container mx-auto max-w-6xl p-4 md:p-8">
        
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-blue-900">Actualizar Precios y Productos</h1>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors duration-200">&larr; Volver al inicio</a>
        </header>

        <!-- Contenedor de dos columnas -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            <!-- Columna 1: Actualización por Lotes (CSV) -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">Actualización por Lotes (CSV)</h2>
                
                <!-- Mostrar mensajes de CSV aquí -->
                <?php get_mensaje('csv'); ?>

                <form action="actualizar.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="subir_csv">
                    <div>
                        <label for="archivo_csv" class="block text-sm font-medium text-gray-700 mb-2">Seleccionar archivo CSV</label>
                        <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required 
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none file:bg-blue-600 file:text-white file:border-0 file:py-2 file:px-4 file:mr-4 file:hover:bg-blue-700">
                    </div>
                    <button type="submit" 
                            class="w-full mt-4 bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                        Subir y Procesar CSV
                    </button>
                </form>
                
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="font-semibold text-blue-800">Instrucciones:</h4>
                    <ul class="list-disc list-inside text-sm text-blue-700 mt-2 space-y-1">
                        <li>El archivo debe ser formato `.csv`.</li>
                        <li>El separador de columnas debe ser punto y coma (`;`).</li>
                        <li>El decimal de precio debe ser una coma (`,`). Ej: `1250,50`.</li>
                        <li>La primera fila (cabeceras) será omitida automáticamente.</li>
                        <li>El CSV debe tener 7 columnas. La primera (`ORDEN`) será ignorada y se generará automáticamente.</li>
                    </ul>
                </div>
            </div>

            <!-- Columna 2: Actualización Manual -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">Actualización Manual</h2>

                <!-- Mostrar mensajes manuales aquí -->
                <?php get_mensaje('manual'); ?>

                <!-- Formulario de BÚSQUEDA -->
                <form action="actualizar.php" method="post" class="mb-6 pb-6 border-b border-gray-200">
                    <input type="hidden" name="accion" value="buscar_manual">
                    <label for="codigo_buscar" class="block text-sm font-medium text-gray-700">Buscar producto por código</label>
                    <div class="mt-2 flex">
                        <input type="text" name="codigo_buscar" id="codigo_buscar" 
                               value="<?php echo htmlspecialchars($codigo_buscado); ?>"
                               class="flex-grow block w-full rounded-l-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                               placeholder="Ingrese un código...">
                        <button type="submit" 
                                class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-r-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                            Buscar
                        </button>
                    </div>
                </form>

                <!-- Formulario para AGREGAR o EDITAR -->
                <?php
                // Mostrar este formulario SOLO si se ha buscado un producto (encontrado o no)
                // o si hubo un error al guardar (para repoblar los datos)
                if ($producto_encontrado !== null):
                    $es_nuevo = empty($producto_encontrado['id']);
                ?>
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">
                        <?php echo $es_nuevo ? 'Agregar Nuevo Producto' : 'Editando Producto'; ?>
                    </h3>
                
                    <form action="actualizar.php" method="post" class="space-y-4">
                        <input type="hidden" name="accion" value="guardar_manual">
                        <!-- Guardamos el ID; estará vacío si es un producto nuevo -->
                        <input type="hidden" name="id_producto" value="<?php echo htmlspecialchars($producto_encontrado['id'] ?? ''); ?>">

                        <div>
                            <label for="codigo" class="block text-sm font-medium text-gray-700">Código</label>
                            <input type="text" name="codigo" id="codigo" required
                                   value="<?php echo htmlspecialchars($producto_encontrado['codigo'] ?? ''); ?>"
                                   <?php if (!$es_nuevo): ?>readonly<?php endif; ?> 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm <?php if (!$es_nuevo): ?>bg-gray-100 cursor-not-allowed<?php endif; ?>">
                            <?php if (!$es_nuevo): ?>
                                <p class="mt-1 text-xs text-gray-500">El código no se puede modificar al editar.</p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="detalle" class="block text-sm font-medium text-gray-700">Detalle</label>
                            <textarea name="detalle" id="detalle" rows="3" required 
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"><?php echo htmlspecialchars($producto_encontrado['detalle'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="compra" class="block text-sm font-medium text-gray-700">Precio (Compra)</label>
                                <input type="text" name="compra" id="compra" required
                                       value="<?php echo htmlspecialchars(number_format($producto_encontrado['compra'] ?? 0.0, 2, ',', '.')); ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                       placeholder="Ej: 1250,50">
                            </div>
                            <div>
                                <label for="cantidad" class="block text-sm font-medium text-gray-700">Cantidad</label>
                                <input type="number" name="cantidad" id="cantidad" required
                                       value="<?php echo htmlspecialchars($producto_encontrado['cantidad'] ?? 0); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="fecha_compra" class="block text-sm font-medium text-gray-700">Fecha Compra</label>
                                <input type="text" name="fecha_compra" id="fecha_compra" 
                                       value="<?php echo htmlspecialchars($producto_encontrado['fecha_compra'] ?? date('d/m/Y')); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                       placeholder="dd/mm/aaaa">
                            </div>
                            <div>
                                <label for="dividido" class="block text-sm font-medium text-gray-700">Dividido por</label>
                                <input type="number" name="dividido" id="dividido" required
                                       value="<?php echo htmlspecialchars($producto_encontrado['dividido'] ?? 1); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" 
                                    class="w-full <?php echo $es_nuevo ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'; ?> text-white font-semibold py-2 px-4 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 transition-all duration-200">
                                <?php echo $es_nuevo ? 'Agregar Nuevo Producto' : 'Actualizar Producto'; ?>
                            </button>
                        </div>
                        
                    </form>
                <?php endif; ?>
            </div>

        </div> <!-- fin grid 2 columnas -->

    </div> <!-- fin container -->

<?php
// Cerrar la conexión al final del script
$conexion->close();
?>
</body>
</html>