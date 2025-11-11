<?php
session_start();

// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS ---
include("config/db.php");

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
    try {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null || empty($data['items'])) {
            throw new Exception("No se recibieron datos JSON válidos o no hay items.");
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

        // 2. Preparar consultas FUERA del bucle
        $stmt_insert = $conexion->prepare("INSERT INTO ventas (venta, fecha_venta, detalle, precio_unitario, cantidad, s_total, factura) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_insert) {
            throw new Exception("Error al preparar la consulta de ventas: " . $conexion->error);
        }

        $stmt_update_stock = $conexion->prepare("UPDATE precios SET cantidad = cantidad - ? WHERE id = ?");
        if (!$stmt_update_stock) {
            throw new Exception("Error al preparar la consulta de stock: ". $conexion->error);
        }

        $fecha_actual = date('Y-m-d');
        $factura_texto = $con_factura ? "Si" : "No";

        // 3. Definir variables para el BINDING (para atar fuera del bucle)
        $bind_detalle = "";
        $bind_unitario = 0.0;
        $bind_cantidad = 0;
        $bind_subtotal = 0.0;
        $bind_stock_cantidad = 0;
        $bind_stock_id = 0;

        // 4. Hacer BINDING UNA SOLA VEZ (FUERA DEL BUCLE)
        // Atamos las variables a la consulta de insertar venta
        $stmt_insert->bind_param(
            "issdids",
            $nuevo_numero_venta, // Constante para todas las filas
            $fecha_actual,       // Constante para todas las filas
            $bind_detalle,       // Esta variable cambiará en cada bucle
            $bind_unitario,      // Esta variable cambiará en cada bucle
            $bind_cantidad,      // Esta variable cambiará en cada bucle
            $bind_subtotal,      // Esta variable cambiará en cada bucle
            $factura_texto       // Constante para todas las filas
        );

        // Atamos las variables a la consulta de actualizar stock
        $stmt_update_stock->bind_param("ii", $bind_stock_cantidad, $bind_stock_id);


        // 5. Iterar y guardar cada producto de la venta
        foreach ($items_venta as $item) {
            
            // Validar datos (importante)
            $bind_unitario = $item['unitario'];
            $bind_cantidad = (int)$item['cantidad'];
            $bind_subtotal = $item['subtotal'];
            $bind_stock_cantidad = (int)$item['cantidad'];
            $bind_stock_id = (int)$item['id'];
            $bind_detalle = $item['detalle'];

            // Si algo es nulo o inválido, detener la transacción
            if ($bind_unitario <= 0 || $bind_cantidad <= 0 || $bind_stock_id <= 0) {
                 throw new Exception("Datos inválidos en un item: " . $bind_detalle);
            }

            // 6. Actualizar el VALOR de las variables y EJECUTAR
            // (Ya no llamamos a bind_param, solo ejecutamos)
            $stmt_insert->execute();
            $stmt_update_stock->execute();
        }

        // 7. Cerrar statements
        $stmt_insert->close();
        $stmt_update_stock->close();

        // 8. Confirmar la transacción
        $conexion->commit();

        // 9. Enviar respuesta de ÉXITO
        header('Content-Type: application/json');
        echo json_encode(['exito' => true, 'mensaje' => "Venta #$nuevo_numero_venta guardada y stock actualizado."]);

    } catch (Exception $e) {
        // Si algo falló, deshacer la transacción
        $conexion->rollback();
        
        // Enviar respuesta de ERROR
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

        // --- CONFIGURACIÓN DE REDONDEO ---
        const MULTIPLO_REDONDEO = 10; // Redondea al siguiente múltiplo de 10
        // ------------------------------------

        const inputBusqueda = document.getElementById('busqueda-producto');
        const divResultados = document.getElementById('resultados-busqueda');
        const spinnerBusqueda = document.getElementById('spinner-busqueda');
        
        const cuerpoTabla = document.getElementById('cuerpo-tabla-venta');
        const filaVacia = document.getElementById('fila-vacia');
        
        const spanGranTotal = document.getElementById('gran-total');
        // const checkFactura = document.getElementById('check-factura'); // Eliminado
        const btnCompletarVenta = document.getElementById('btn-completar-venta');
        const spanNumeroVenta = document.getElementById('numero-venta');
        // --- MODIFICACIÓN: Constantes para el nuevo modal ---
            const modalFactura = document.getElementById('modal-factura');
            const btnFacturaSi = document.getElementById('btn-factura-si');
            const btnFacturaNo = document.getElementById('btn-factura-no');
            // ---------------------------------------------------
        let timeoutBusqueda;
        let productosEnVenta = new Map();

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
                const compra = parseFloat(producto.compra) || 0;
                const dividido = parseInt(producto.dividido, 10) || 1;
                
                // --- CÁLCULO CON REDONDEO ---
                const precioCalculado = (compra / dividido) * 2.105;
                const precioUnitario = Math.ceil(precioCalculado / MULTIPLO_REDONDEO) * MULTIPLO_REDONDEO;
                // -----------------------------
                
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
                    agregarProductoALaVenta(producto, precioUnitario);
                });
                
                divResultados.appendChild(divItem);
            });
        }

        /**
         * Agrega un producto a la tabla de venta
         */
        function agregarProductoALaVenta(producto, precioUnitario) {
            if (productosEnVenta.has(producto.id)) {
                alert('El producto ya está en la lista. Puedes modificar la cantidad.');
                const inputExistente = productosEnVenta.get(producto.id).querySelector('input[name="cantidad"]');
                inputExistente.focus();
                inputExistente.select();
                return;
            }

            if (filaVacia) {
                filaVacia.remove();
            }

            const tr = document.createElement('tr');
            tr.id = `producto-${producto.id}`;
            
            const subtotal = precioUnitario * 1; 

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
            tr.dataset.precioCompra = producto.compra; // <-- IMPORTANTE: Guardamos la compra
            tr.dataset.precioUnitario = precioUnitario; // Guardamos el unitario redondeado
            tr.dataset.detalle = producto.detalle;

            cuerpoTabla.appendChild(tr);
            productosEnVenta.set(producto.id, tr); 

            inputBusqueda.value = '';
            divResultados.innerHTML = '<div class="p-4 text-center text-gray-500">Escribe para buscar...</div>';
            actualizarGranTotal();

            const inputCantidad = tr.querySelector('input[name="cantidad"]');
            inputCantidad.focus();
            inputCantidad.select(); 
        }

        /**
         * Recalcula el subtotal de una fila y el gran total (FUNCIÓN CORREGIDA)
         */
        function actualizarTotales(event) {
            const input = event.target;
            
            // Reaccionar si se cambia "cantidad" O "dividido"
            if (input.name !== 'cantidad' && input.name !== 'dividido') return;

            const tr = input.closest('tr');
            if (!tr) return;

            // 1. Obtener valores base
            const precioCompra = parseFloat(tr.dataset.precioCompra) || 0;
            
            // 2. Obtener valores de AMBOS inputs
            const dividido = parseInt(tr.querySelector('input[name="dividido"]').value, 10) || 1; 
            const cantidad = parseInt(tr.querySelector('input[name="cantidad"]').value, 10) || 0;

            // 3. Recalcular precio unitario (CON REDONDEO)
            const precioCalculado = (precioCompra / dividido) * 2.105;
            const nuevoPrecioUnitario = Math.ceil(precioCalculado / MULTIPLO_REDONDEO) * MULTIPLO_REDONDEO;
            
            // 4. Recalcular subtotal
            const subtotal = cantidad * nuevoPrecioUnitario;

            // 5. Actualizar la UI (los <td> de $ Unitario y $ Subtotal)
            tr.querySelector('[data-label="$ Unitario"]').textContent = `$ ${nuevoPrecioUnitario.toFixed(2)}`;
            tr.querySelector('[data-label="$ Subtotal"]').textContent = `$ ${subtotal.toFixed(2)}`;
            
            // 6. Actualizar el dataset (CRÍTICO PARA QUE SE GUARDE BIEN)
            tr.dataset.precioUnitario = nuevoPrecioUnitario;

            // 7. Actualizar el gran total
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

            productosEnVenta.delete(tr.dataset.productoId);
            tr.remove();

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
                // Lee el precio del dataset (que es el correcto y redondeado)
                const precioUnitario = parseFloat(tr.dataset.precioUnitario) || 0;
                const cantidad = parseInt(tr.querySelector('input[name="cantidad"]').value, 10) || 0;
                total += cantidad * precioUnitario;
            });

            spanGranTotal.textContent = `$ ${total.toFixed(2)}`;
            btnCompletarVenta.disabled = (total === 0);
        }
        
        /**
         * Guarda la venta completa en la base de datos (FUNCIÓN MODIFICADA)
         */
        async function guardarVenta(conFactura) { // Recibe el parámetro
            // 1. Recolectar datos
            const itemsParaGuardar = [];
            let datosValidos = true;

            productosEnVenta.forEach(tr => {
                const inputCantidad = tr.querySelector('input[name="cantidad"]');
                const unitario = parseFloat(tr.dataset.precioUnitario);
                const cantidad = parseInt(inputCantidad.value, 10);
                
                if (isNaN(unitario) || isNaN(cantidad) || unitario <= 0 || cantidad <= 0) {
                    datosValidos = false;
                }

                itemsParaGuardar.push({
                    id: tr.dataset.productoId,
                    detalle: tr.dataset.detalle,
                    unitario: unitario,
                    cantidad: cantidad,
                    subtotal: unitario * cantidad
                });
            });

            if (!datosValidos) {
                alert('Error: Hay productos con cantidad o precio inválido (cero o negativo). Revisa la tabla.');
                return; // No continuar si hay datos malos
            }

            const datosVenta = {
                factura: conFactura, // Usa el parámetro
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
                    throw new Error(resultado.mensaje || 'Error desconocido al guardar.');
                }

                // Éxito
                alert(resultado.mensaje); 
                
                cuerpoTabla.innerHTML = '';
                if (filaVacia) cuerpoTabla.appendChild(filaVacia);
                productosEnVenta.clear(); 
                
                spanGranTotal.textContent = '$ 0.00';
                // checkFactura.checked = false; // Ya no existe
                spanNumeroVenta.textContent = parseInt(spanNumeroVenta.textContent) + 1;
                
            } catch (error) {
                console.error('Error al guardar venta:', error);
                alert('Error: ' + error.message);
            } finally {
                actualizarGranTotal();
                btnCompletarVenta.textContent = 'Completar Venta';
            }
        }


        // --- ASIGNACIÓN DE EVENTOS ---

        // Evento para la barra de búsqueda
        inputBusqueda.addEventListener('keyup', (e) => {
            clearTimeout(timeoutBusqueda);
            spinnerBusqueda.classList.remove('hidden'); 
            timeoutBusqueda = setTimeout(buscarProductos, 300); 
        });

        // Eventos en la tabla (delegación de eventos)
        cuerpoTabla.addEventListener('change', actualizarTotales); // Se activa al cambiar cantidad o dividido
        cuerpoTabla.addEventListener('keyup', actualizarTotales);  // Se activa mientras tecleas
        cuerpoTabla.addEventListener('click', quitarProducto);
        
        // --- MODIFICACIÓN: Eventos para el nuevo Modal de Factura ---

            // 1. Al hacer clic en "Completar Venta", SÓLO mostramos el modal
            btnCompletarVenta.addEventListener('click', () => {
                if (productosEnVenta.size > 0) {
                    modalFactura.classList.remove('hidden'); // Muestra el modal
                } else {
                    alert('No hay productos en la venta.');
                }
            });

            // 2. Si el usuario presiona "Sí"
            btnFacturaSi.addEventListener('click', () => {
                modalFactura.classList.add('hidden'); // Oculta el modal
                guardarVenta(true); // Llama a guardar con "Factura SI"
            });

            // 3. Si el usuario presiona "No"
            btnFacturaNo.addEventListener('click', () => {
                modalFactura.classList.add('hidden'); // Oculta el modal
                guardarVenta(false); // Llama a guardar con "Factura NO"
            });

    });
    </script>
    <!-- Modal para preguntar por la factura -->
    <div id="modal-factura" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-xl shadow-2xl max-w-sm mx-auto">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Confirmar Venta</h3>
            <p class="text-md text-gray-700 mb-8">
                ¿Registrar esta venta como "Factura SI"?
            </p>
            
            <div class="flex justify-end gap-4">
                
                <button id="btn-factura-no" type="button" 
                        class="w-full md:w-auto px-6 py-3 bg-gray-200 text-gray-800 font-bold rounded-lg hover:bg-gray-300 transition-all">
                    No
                </button>
                
                <button id="btn-factura-si" type="button" 
                        class="w-full md:w-auto px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition-all">
                    Sí
                </button>
                
            </div>
        </div>
    </div>
</body>
</html>