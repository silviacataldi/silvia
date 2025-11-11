<?php
session_start();

// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS ---
$servidor = "localhost";
$usuario = "root";
$contrasena = "";
$base_de_datos = "cotillon";

$conexion = new mysqli($servidor, $usuario, $contrasena, $base_de_datos);
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");

// --- 2. MANEJO DE PETICIONES (API INTERNA) ---

// ACCIÓN: Buscar productos (usada por JavaScript 'fetch')
if (isset($_GET['accion']) && $_GET['accion'] == 'buscar' && isset($_GET['termino'])) {
    $termino = $conexion->real_escape_string($_GET['termino']);
    
    // Buscamos productos donde el 'detalle' contenga el término de búsqueda
    $query = "SELECT id, codigo, detalle, compra, dividido FROM precios WHERE detalle LIKE '%$termino%' LIMIT 20";
    
    $resultado = $conexion->query($query);
    $productos = [];
    
    if ($resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            $productos[] = $fila;
        }
    }
    
    // Devolvemos los resultados como JSON
    header('Content-Type: application/json');
    echo json_encode($productos);
    $conexion->close();
    exit;
}

// ACCIÓN: Guardar Venta (usada por JavaScript 'fetch' POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- BLOQUE DE TRANSACCIÓN SEGURO (CON try...catch) ---
    // Envolvemos toda la operación en un try...catch
    try {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null) {
            throw new Exception("No se recibieron datos JSON válidos.");
        }

        $items_venta = $data['items'];
        $con_factura = $data['factura'];

        // Iniciar transacción
        $conexion->begin_transaction();

        // 1. Obtener el último número de venta y calcular el siguiente
        $query_venta = "SELECT MAX(venta) as max_venta FROM ventas";
        $resultado_venta = $conexion->query($query_venta);
        $fila_venta = $resultado_venta->fetch_assoc();
        $nuevo_numero_venta = ($fila_venta['max_venta'] ?? 0) + 1;

        // 2. Preparar la consulta para insertar en 'ventas'
        $stmt_insert = $conexion->prepare("INSERT INTO ventas (venta, fecha_venta, detalle, precio_unitario, cantidad, s_total, factura) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_insert) {
            throw new Exception("Error al preparar la consulta de ventas: " . $conexion->error);
        }

        // 3. Preparar la consulta para ACTUALIZAR el stock en 'precios'
        $stmt_update_stock = $conexion->prepare("UPDATE precios SET cantidad = cantidad - ? WHERE id = ?");
        if (!$stmt_update_stock) {
            throw new Exception("Error al preparar la consulta de stock: " . $conexion->error);
        }

        $fecha_actual = date('Y-m-d');
        $factura_texto = $con_factura ? "Si" : "No";

        // 4. Iterar y guardar cada producto de la venta
        foreach ($items_venta as $item) {
            
            // Guardar el item en la tabla 'ventas'
            $stmt_insert->bind_param(
                "issdids",
                $nuevo_numero_venta,
                $fecha_actual,
                $item['detalle'],
                $item['unitario'],
                $item['cantidad'],
                $item['subtotal'],
                $factura_texto
            );
            $stmt_insert->execute();

            // Ahora, restar el stock de la tabla 'precios'
            $cantidad_vendida = (int)$item['cantidad'];
            $producto_id = (int)$item['id']; // Este 'id' es el de la tabla 'precios'

            $stmt_update_stock->bind_param("ii", $cantidad_vendida, $producto_id);
            $stmt_update_stock->execute();
        }

        // 5. Cerrar statements
        $stmt_insert->close();
        $stmt_update_stock->close();

        // 6. Confirmar la transacción
        $conexion->commit();

        // 7. Enviar respuesta de ÉXITO
        header('Content-Type: application/json');
        echo json_encode(['exito' => true, 'mensaje' => "Venta #$nuevo_numero_venta guardada y stock actualizado."]);

    } catch (Exception $e) {
        // Si algo falló (en CUALQUIER punto del 'try'), deshacer la transacción
        $conexion->rollback();
        
        // Enviar respuesta de ERROR (JSON válido, no un error HTML)
        header('Content-Type: application/json');
        http_response_code(500); // Enviar un código de error http
        echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
    
    }
    // --- FIN DEL BLOQUE DE TRANSACCIÓN ---

    $conexion->close();
    exit;
}


// --- 3. LÓGICA DE LA PÁGINA (SI NO ES API) ---
// (Solo se ejecuta si no es una petición 'GET' con 'accion' o 'POST')

// Obtener el Nro de Venta para mostrar en el HTML
$query_venta = "SELECT MAX(venta) as max_venta FROM ventas";
$resultado_venta = $conexion->query($query_venta);
$fila_venta = $resultado_venta->fetch_assoc();
$proximo_numero_venta = ($fila_venta['max_venta'] ?? 0) + 1;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venta - Cotillón Nubes Blancas</title>
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
    <style>
        /* Estilos para el spinner de carga */
        #spinner-busqueda {
            border: 4px solid rgba(0, 0, 0, .1);
            border-left-color: #3b82f6;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">

    <div class="container mx-auto max-w-7xl p-4 md:p-8">
        
        <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-blue-900">Venta Rápida</h1>
                <p class="text-gray-600">Cotillón Nubes Blancas</p>
            </div>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors">&larr; Volver al inicio</a>
        </header>

        <!-- --- ESTE ES EL NUEVO BLOQUE DE DISEÑO (EL ANTIGUO QUE TE GUSTABA) --- -->
        
        <!-- Resumen de Venta (Arriba) -->
        <div class="bg-white p-6 rounded-xl shadow-lg mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Resumen de Venta</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                
                <!-- Detalles Venta -->
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Nro. Venta:</span>
                        <span id="numero-venta" class="text-lg font-bold text-blue-800"><?php echo $proximo_numero_venta; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Fecha:</span>
                        <span class="text-lg font-bold text-gray-800"><?php echo date('d/m/Y'); ?></span>
                    </div>
                    <div class="flex items-center">
                        <input id="check-factura" name="factura" type="checkbox" class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="check-factura" class="ml-3 block text-md font-medium text-gray-700">Registrar como "Factura SI"</label>
                    </div>
                </div>

                <!-- Total -->
                <div class="text-center md:text-right md:col-span-1">
                    <span class="text-2xl font-bold text-gray-800 block">Total:</span>
                    <span id="gran-total" class="text-4xl font-extrabold text-blue-700">$ 0.00</span>
                </div>

                <!-- Botón -->
                <div class="md:col-span-1">
                     <button id="btn-completar-venta" 
                            disabled
                            class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg shadow-lg transition-all
                                   hover:bg-blue-700 
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                                   disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Completar Venta
                    </button>
                </div>
            </div>
        </div>

        <!-- Búsqueda de Producto -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-lg mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Buscar Producto</h2>
            <!-- Campo de Búsqueda -->
            <div class="relative">
                <input type="text" id="busqueda-producto" 
                       placeholder="Escribe parte del detalle del producto..."
                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <svg class="w-6 h-6 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <div id="spinner-busqueda" class="absolute right-3 top-1/2 -translate-y-1/2 hidden"></div>
            </div>
            
            <!-- Resultados de Búsqueda -->
            <div id="resultados-busqueda" class="mt-4 max-h-96 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-200">
                <!-- Los resultados de la búsqueda se insertarán aquí -->
                <div class="p-4 text-center text-gray-500">Escribe para buscar...</div>
            </div>
        </div>
        
        <!-- --- FIN DEL NUEVO BLOQUE DE DISEÑO --- -->

        
        <!-- Tabla de Venta Actual (Esto queda igual) -->
        <div class="lg:col-span-2 mt-8 bg-white rounded-xl shadow-lg overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-800 p-6">Productos en esta Venta</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">P. Compra</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Dividido</th>
                            <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">$ Unitario</th>
                            <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 120px;">Cantidad</th>
                            <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">$ Subtotal</th>
                            <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quitar</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpo-tabla-venta" class="bg-white divide-y divide-gray-200">
                        <tr id="fila-vacia">
                            <td colspan="7" class="p-6 text-center text-gray-500">Agrega productos desde la barra de búsqueda...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div> <!-- fin container -->


<script>
document.addEventListener('DOMContentLoaded', () => {

    const MULTIPLO_REDONDEO = 10;
    const inputBusqueda = document.getElementById('busqueda-producto');
    const divResultados = document.getElementById('resultados-busqueda');
    const spinnerBusqueda = document.getElementById('spinner-busqueda');
    
    const cuerpoTabla = document.getElementById('cuerpo-tabla-venta');
    const filaVacia = document.getElementById('fila-vacia');
    
    const spanGranTotal = document.getElementById('gran-total');
    const checkFactura = document.getElementById('check-factura');
    const btnCompletarVenta = document.getElementById('btn-completar-venta');
    const spanNumeroVenta = document.getElementById('numero-venta');

    let timeoutBusqueda;
    let productosEnVenta = new Map(); // Usamos un Map para evitar duplicados y acceder rápido

    /**
     * Busca productos en el servidor
     */
    async function buscarProductos() {
        const termino = inputBusqueda.value.trim();
        
        if (termino.length < 3) {
            divResultados.innerHTML = '<div class="p-4 text-center text-gray-500">Escribe al menos 3 letras...</div>';
            spinnerBusqueda.classList.add('hidden');
            return;
        }

        spinnerBusqueda.classList.remove('hidden');

        try {
            const respuesta = await fetch(`venta.php?accion=buscar&termino=${encodeURIComponent(termino)}`);
            if (!respuesta.ok) {
                throw new Error('Error de red al buscar.');
            }
            const productos = await respuesta.json();
            
            mostrarResultados(productos);

        } catch (error) {
            console.error(error);
            divResultados.innerHTML = '<div class="p-4 text-center text-red-500">Error al buscar. Intenta de nuevo.</div>';
        } finally {
            spinnerBusqueda.classList.add('hidden');
        }
    }

    /**
     * Muestra los resultados de la búsqueda en la UI
     */
    function mostrarResultados(productos) {
        if (productos.length === 0) {
            divResultados.innerHTML = '<div class="p-4 text-center text-gray-500">No se encontraron productos.</div>';
            return;
        }

        divResultados.innerHTML = '';
        productos.forEach(producto => {
            // Calcular precio unitario (Compra / Dividido * 2)
            const compra = parseFloat(producto.compra) || 0;
            const dividido = parseInt(producto.dividido, 10) || 1;
        // MODIFICACIÓN: (compra / dividido) * 2.105 Y REDONDEO SUPERIOR
                    const precioCalculado = (compra / dividido) * 2.105;
                    const precioUnitario = Math.ceil(precioCalculado / MULTIPLO_REDONDEO) * MULTIPLO_REDONDEO;
            
            // Guardar datos en el elemento para usarlos al hacer clic
            const divItem = document.createElement('div');
            divItem.className = 'p-4 hover:bg-blue-50 cursor-pointer transition-colors';
            divItem.innerHTML = `
                <div class="font-medium text-gray-800">${producto.detalle}</div>
                <div class="text-sm text-gray-500">
                    Cód: ${producto.codigo} | 
                    Precio: <span class="font-bold text-blue-600">$ ${precioUnitario.toFixed(2)}</span>
                </div>
            `;
            
            divItem.addEventListener('click', () => {
                // Pasamos el producto completo y el precio unitario calculado
                agregarProductoALaVenta(producto, precioUnitario);
            });
            
            divResultados.appendChild(divItem);
        });
    }

    /**
     * Agrega un producto a la tabla de venta
     */
    function agregarProductoALaVenta(producto, precioUnitario) {
        // Si el producto ya está en la tabla, no lo agregamos de nuevo
        if (productosEnVenta.has(producto.id)) {
            // Opcional: podríamos hacer focus en el campo de cantidad existente
            alert('El producto ya está en la lista. Puedes modificar la cantidad.');
            const inputExistente = productosEnVenta.get(producto.id).querySelector('input[name="cantidad"]');
            inputExistente.focus();
            inputExistente.select();
            return;
        }

        // Si es el primer producto, quitar la fila vacía
        if (filaVacia) {
            filaVacia.remove();
        }

        const tr = document.createElement('tr');
        tr.id = `producto-${producto.id}`;
        
        const subtotal = precioUnitario * 1; // Cantidad inicial es 1

        tr.innerHTML = `
            <td class="py-4 px-4 text-sm text-gray-800" data-label="Detalle">
                <span class="font-medium">${producto.detalle}</span>
                <span class="text-xs text-gray-500 block">Cód: ${producto.codigo}</span>
            </td>
            <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-500 text-right hidden md:table-cell" data-label="P. Compra">$ ${parseFloat(producto.compra).toFixed(2)}</td>
            <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-500 text-right hidden md:table-cell" data-label="Dividido">
                            <input type="number" name="dividido" value="${producto.dividido}" min="1" 
                                   class="w-20 text-center border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </td>
            <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 text-right" data-label="$ Unitario">$ ${precioUnitario.toFixed(2)}</td>
            <td class="py-4 px-4 whitespace-nowrap text-center" data-label="Cantidad">
                <input type="number" name="cantidad" value="1" min="1" 
                       class="w-20 text-center border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </td>
            <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold" data-label="$ Subtotal">$ ${subtotal.toFixed(2)}</td>
            <td class="py-4 px-4 whitespace-nowrap text-center" data-label="Quitar">
                <button name="quitar" class="text-red-500 hover:text-red-700">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </td>
        `;

        // Guardar datos importantes en el objeto 'tr' para cálculos
        tr.dataset.productoId = producto.id;
        tr.dataset.precioUnitario = precioUnitario;
        tr.dataset.precioCompra = producto.compra;
        tr.dataset.detalle = producto.detalle;

        cuerpoTabla.appendChild(tr);
        productosEnVenta.set(producto.id, tr); // Registrar en el Map

        // Limpiar búsqueda
        inputBusqueda.value = '';
        divResultados.innerHTML = '<div class="p-4 text-center text-gray-500">Escribe para buscar...</div>';
        actualizarGranTotal();

        // --- MODIFICACIÓN: Poner el foco en el campo 'cantidad' ---
        const inputCantidad = tr.querySelector('input[name="cantidad"]');
        inputCantidad.focus();
        inputCantidad.select(); // Selecciona el '1' para que se pueda sobreescribir fácilmente
    }

    /**
        * Recalcula el subtotal de una fila y el gran total
        */
       function actualizarTotales(event) {
           const input = event.target;
           
           // MODIFICACIÓN: Reaccionar si se cambia "cantidad" O "dividido"
           if (input.name !== 'cantidad' && input.name !== 'dividido') return;

           const tr = input.closest('tr');
           if (!tr) return;

           // --- 1. Obtener valores base ---
           const precioCompra = parseFloat(tr.dataset.precioCompra) || 0;
           
           // --- 2. Obtener valores de AMBOS inputs ---
           const dividido = parseInt(tr.querySelector('input[name="dividido"]').value, 10) || 1; // Evitar división por cero
           const cantidad = parseInt(tr.querySelector('input[name="cantidad"]').value, 10) || 0;

           // --- 3. Recalcular precio unitario (NUEVA FÓRMULA) ---
           const nuevoPrecioUnitario = (precioCompra / dividido) * 2.105;
           
           // --- 4. Recalcular subtotal ---
           const subtotal = cantidad * nuevoPrecioUnitario;

           // --- 5. Actualizar la UI (los <td> de $ Unitario y $ Subtotal) ---
           tr.querySelector('[data-label="$ Unitario"]').textContent = `$ ${nuevoPrecioUnitario.toFixed(2)}`;
           tr.querySelector('[data-label="$ Subtotal"]').textContent = `$ ${subtotal.toFixed(2)}`;
           
           // --- 6. Actualizar el dataset para que 'guardarVenta' use el precio correcto ---
           tr.dataset.precioUnitario = nuevoPrecioUnitario;

           // Actualizar el gran total (esta función ya sabe qué hacer)
           actualizarGranTotal();
       }

    /**
     * Quita un producto de la tabla
     */
    function quitarProducto(event) {
        const boton = event.target.closest('button[name="quitar"]');
        if (!boton) return;

        const tr = boton.closest('tr');
        if (!tr) return;

        // Quitar del Map y del DOM
        productosEnVenta.delete(tr.dataset.productoId);
        tr.remove();

        // Si la tabla está vacía, mostrar la fila vacía
        if (productosEnVenta.size === 0 && filaVacia) {
            cuerpoTabla.appendChild(filaVacia);
        }

        actualizarGranTotal();
    }

    /**
     * Suma todos los subtotales y actualiza el gran total
     */
    function actualizarGranTotal() {
        let total = 0;
        
        productosEnVenta.forEach(tr => {
            const cantidad = parseInt(tr.querySelector('input[name="cantidad"]').value, 10) || 0;
            const precioUnitario = parseFloat(tr.dataset.precioUnitario) || 0;
            total += cantidad * precioUnitario;
        });

        spanGranTotal.textContent = `$ ${total.toFixed(2)}`;
        
        // Habilitar o deshabilitar el botón de completar venta
        btnCompletarVenta.disabled = (total === 0);
    }
    
    /**
     * Guarda la venta completa en la base de datos
     */
    async function guardarVenta() {
        // 1. Recolectar datos
        const itemsParaGuardar = [];
        productosEnVenta.forEach(tr => {
            const inputCantidad = tr.querySelector('input[name="cantidad"]');
            
            itemsParaGuardar.push({
                id: tr.dataset.productoId,
                detalle: tr.dataset.detalle,
                unitario: parseFloat(tr.dataset.precioUnitario),
                cantidad: parseInt(inputCantidad.value, 10),
                subtotal: parseFloat(tr.dataset.precioUnitario) * parseInt(inputCantidad.value, 10)
            });
        });

        const datosVenta = {
            factura: checkFactura.checked,
            items: itemsParaGuardar
        };

        // 2. Enviar al servidor
        btnCompletarVenta.disabled = true;
        btnCompletarVenta.textContent = 'Guardando...';

        try {
            const respuesta = await fetch('venta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datosVenta)
            });

            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.exito) {
                // Si el servidor envía un error (como desde el 'catch' de PHP), lo mostramos
                throw new Error(resultado.mensaje || 'Error desconocido al guardar.');
            }

            // Éxito
            alert(resultado.mensaje); // "Venta #123 guardada y stock actualizado."
            
            // Limpiar la interfaz para una nueva venta
            cuerpoTabla.innerHTML = '';
            if (filaVacia) cuerpoTabla.appendChild(filaVacia);
            productosEnVenta.clear(); // Limpiar el Map
            
            spanGranTotal.textContent = '$ 0.00';
            checkFactura.checked = false;
            // Actualizar el número de venta al siguiente
            spanNumeroVenta.textContent = parseInt(spanNumeroVenta.textContent) + 1;
            
        } catch (error) {
            console.error('Error al guardar venta:', error);
            alert('Error: ' + error.message);
        } finally {
            // Reactivar botón (o deshabilitarlo si la tabla está vacía)
            actualizarGranTotal();
            btnCompletarVenta.textContent = 'Completar Venta';
        }
    }


    // --- ASIGNACIÓN DE EVENTOS ---

    // Evento para la barra de búsqueda (con 'debounce')
    inputBusqueda.addEventListener('keyup', (e) => {
        clearTimeout(timeoutBusqueda);
        spinnerBusqueda.classList.remove('hidden'); // Mostrar spinner al teclear
        timeoutBusqueda = setTimeout(buscarProductos, 300); // Espera 300ms
    });

    // Eventos en la tabla (usando delegación de eventos)
    cuerpoTabla.addEventListener('change', actualizarTotales);
    cuerpoTabla.addEventListener('click', quitarProducto);
    
    // Evento para el botón de completar venta
    btnCompletarVenta.addEventListener('click', guardarVenta);

});
</script>

</body>
</html>