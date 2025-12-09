<?php
// Iniciamos buffering para evitar que espacios en blanco rompan el JSON
ob_start();
session_start();

// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS ---
include("config/db.php");

$conexion = new mysqli($servidor, $usuario, $contrasena, $base_de_datos);
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");

// --- 2. MANEJO DE PETICIONES (API INTERNA) ---

// ACCIÓN: Buscar productos
if (isset($_GET['accion']) && $_GET['accion'] == 'buscar' && isset($_GET['termino'])) {
    // Limpiamos cualquier salida previa (espacios, enters en db.php, etc)
    ob_end_clean();
    header('Content-Type: application/json');

    try {
        $termino = $conexion->real_escape_string($_GET['termino']);
        
        // Consulta SQL
        $query = "SELECT id, codigo, detalle, compra, dividido, ganancia FROM precios WHERE detalle LIKE '%$termino%' LIMIT 20";
        
        $resultado = $conexion->query($query);
        
        if (!$resultado) {
            throw new Exception("Error SQL: " . $conexion->error);
        }

        $productos = [];
        if ($resultado->num_rows > 0) {
            while ($fila = $resultado->fetch_assoc()) {
                $productos[] = $fila;
            }
        }
        
        echo json_encode($productos);

    } catch (Exception $e) {
        // Enviar error como JSON para que JS lo pueda leer
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    $conexion->close();
    exit;
}

// ACCIÓN: Guardar Venta
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_end_clean(); // Limpiar buffer
    header('Content-Type: application/json');

    try {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null || empty($data['items'])) {
            throw new Exception("No se recibieron datos JSON válidos o no hay items.");
        }

        $items_venta = $data['items'];
        $con_factura = $data['factura'];

        $conexion->begin_transaction();

        $query_venta = "SELECT MAX(venta) as max_venta FROM ventas";
        $resultado_venta = $conexion->query($query_venta);
        $fila_venta = $resultado_venta->fetch_assoc();
        $nuevo_numero_venta = ($fila_venta['max_venta'] ?? 0) + 1;

        $stmt_insert = $conexion->prepare("INSERT INTO ventas (venta, fecha_venta, detalle, precio_unitario, cantidad, s_total, factura) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_update_stock = $conexion->prepare("UPDATE precios SET cantidad = cantidad - ? WHERE id = ?");

        $fecha_actual = date('Y-m-d');
        $factura_texto = $con_factura ? "Si" : "No";

        $bind_detalle = "";
        $bind_unitario = 0.0;
        $bind_cantidad = 0;
        $bind_subtotal = 0.0;
        $bind_stock_cantidad = 0;
        $bind_stock_id = 0;

        $stmt_insert->bind_param("issdids", $nuevo_numero_venta, $fecha_actual, $bind_detalle, $bind_unitario, $bind_cantidad, $bind_subtotal, $factura_texto);
        $stmt_update_stock->bind_param("ii", $bind_stock_cantidad, $bind_stock_id);

        foreach ($items_venta as $item) {
            $bind_unitario = $item['unitario'];
            $bind_cantidad = (int)$item['cantidad'];
            $bind_subtotal = $item['subtotal'];
            $bind_stock_cantidad = (int)$item['cantidad'];
            $bind_stock_id = (int)$item['id'];
            $bind_detalle = $item['detalle'];

            if ($bind_unitario <= 0 || $bind_cantidad <= 0 || $bind_stock_id <= 0) {
                 throw new Exception("Datos inválidos en un item: " . $bind_detalle);
            }

            $stmt_insert->execute();
            $stmt_update_stock->execute();
        }

        $stmt_insert->close();
        $stmt_update_stock->close();
        $conexion->commit();

        echo json_encode(['exito' => true, 'mensaje' => "Venta #$nuevo_numero_venta guardada y stock actualizado."]);

    } catch (Exception $e) {
        $conexion->rollback();
        http_response_code(500); 
        echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
    }
    $conexion->close();
    exit;
}

// --- 3. LÓGICA DE LA PÁGINA ---
// Liberamos el buffer para mostrar HTML
ob_end_flush(); 

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
    <nav class="relative bg-blue-800/50 after:pointer-events-none after:absolute after:inset-x-0 after:bottom-0 after:h-px after:bg-white/10">
      <div class="mx-auto max-w-7xl px-2 sm:px-6 lg:px-8">
        <div class="relative flex h-16 items-center justify-between">
          <div class="flex flex-1 items-center justify-center sm:items-stretch sm:justify-start">
            <div class="flex shrink-0 items-center">
              <img src="config/logo.png" alt="Nubes Blancas" class="h-8 w-auto" />
            </div>
            <div class="hidden sm:ml-6 sm:block">
              <div class="flex space-x-4">
                <a href="index.php" class="rounded-md px-3 py-2 text-xs font-medium text-gray-300 hover:bg-white/5 hover:text-white">Inicio</a>
                <a href="#" aria-current="page" class="rounded-md bg-gray-950/50 px-3 py-2 text-xs font-medium text-white">Venta</a>
                <a href="actualizar.php" class="rounded-md px-3 py-2 text-xs font-medium text-gray-300 hover:bg-white/5 hover:text-white">Actualizar</a>
                <a href="contable.php" class="rounded-md px-3 py-2 text-xs font-medium text-gray-300 hover:bg-white/5 hover:text-white">Contable</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>
    
    <div class="container mx-auto max-w-7xl p-4 md:p-8">
        
        <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-blue-900">Venta Rápida</h1>
                <p class="text-gray-600">Cotillón Nubes Blancas</p>
            </div>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors">&larr; Volver al inicio</a>
        </header>

        <div class="bg-white p-6 rounded-xl shadow-lg mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Resumen de Venta</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
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
                <div class="text-center md:text-right md:col-span-1">
                    <span class="text-2xl font-bold text-gray-800 block">Total:</span>
                    <span id="gran-total" class="text-4xl font-extrabold text-blue-700">$ 0.00</span>
                </div>
                <div class="md:col-span-1">
                     <button id="btn-completar-venta" disabled
                            class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg shadow-lg transition-all hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Completar Venta
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-lg mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Buscar Producto</h2>
                <div class="relative">
                    <input type="text" id="busqueda-producto" 
                           placeholder="Escribe parte del detalle..."
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div id="spinner-busqueda" class="absolute right-3 top-1/2 -translate-y-1/2 hidden"></div>
                </div>
                <div id="resultados-busqueda" class="mt-4 max-h-96 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-200">
                    <div class="p-4 text-center text-gray-500">Escribe para buscar...</div>
                </div>
            </div>
            
            <div class="lg:col-span-2 mt-8 bg-white rounded-xl shadow-lg overflow-hidden">
                <h2 class="text-xl font-semibold text-gray-800 p-6">Productos en esta Venta</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Detalle</th>
                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">P. Compra</th>
                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Div</th>
                                <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase hidden md:table-cell">%</th>
                                <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase">$ Unitario</th>
                                <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase" style="width: 120px;">Cant</th>
                                <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase">$ Subtotal</th>
                                <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase">Quitar</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo-tabla-venta" class="bg-white divide-y divide-gray-200">
                            <tr id="fila-vacia">
                                <td colspan="8" class="p-6 text-center text-gray-500">Agrega productos desde la barra de búsqueda...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> 

    <script>
    document.addEventListener('DOMContentLoaded', () => {

        const MULTIPLO_REDONDEO = 10; 
        const inputBusqueda = document.getElementById('busqueda-producto');
        const divResultados = document.getElementById('resultados-busqueda');
        const spinnerBusqueda = document.getElementById('spinner-busqueda');
        const cuerpoTabla = document.getElementById('cuerpo-tabla-venta');
        const filaVacia = document.getElementById('fila-vacia');
        const spanGranTotal = document.getElementById('gran-total');
        const btnCompletarVenta = document.getElementById('btn-completar-venta');
        const spanNumeroVenta = document.getElementById('numero-venta');
        const modalFactura = document.getElementById('modal-factura');
        const btnFacturaSi = document.getElementById('btn-factura-si');
        const btnFacturaNo = document.getElementById('btn-factura-no');

        let timeoutBusqueda;
        let productosEnVenta = new Map();

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
                
                // Si la respuesta no es OK, intentamos leer el error JSON
                if (!respuesta.ok) {
                    const dataError = await respuesta.json();
                    throw new Error(dataError.error || 'Error desconocido en el servidor');
                }
                
                const productos = await respuesta.json();
                mostrarResultados(productos);
            } catch (error) {
                console.error(error);
                // ESTA ALERTA ES LA CLAVE PARA DEBUGGEAR
                alert("Error al buscar: " + error.message); 
                divResultados.innerHTML = '<div class="p-4 text-center text-red-500">Error. Intenta de nuevo.</div>';
            } finally {
                spinnerBusqueda.classList.add('hidden');
            }
        }

        function mostrarResultados(productos) {
            if (productos.length === 0) {
                divResultados.innerHTML = '<div class="p-4 text-center text-gray-500">No se encontraron productos.</div>';
                return;
            }
            divResultados.innerHTML = '';
            productos.forEach(producto => {
                const compra = parseFloat(producto.compra) || 0;
                const dividido = parseInt(producto.dividido, 10) || 1;
                const ganancia = parseFloat(producto.ganancia) || 100;
                
                const factorGanancia = 1 + (ganancia / 100);
                const precioCalculado = (compra / dividido) * factorGanancia * 1.105;
                const precioUnitario = Math.ceil(precioCalculado / MULTIPLO_REDONDEO) * MULTIPLO_REDONDEO;
                
                const divItem = document.createElement('div');
                divItem.className = 'p-4 hover:bg-blue-50 cursor-pointer transition-colors';
                divItem.innerHTML = `
                    <div class="font-medium text-gray-800">${producto.detalle}</div>
                    <div class="text-sm text-gray-500">
                        Cód: ${producto.codigo} | 
                        Precio: <span class="font-bold text-blue-600"> ${precioUnitario.toFixed(2)}</span>
                    </div>
                `;
                divItem.addEventListener('click', () => agregarProductoALaVenta(producto, precioUnitario));
                divResultados.appendChild(divItem);
            });
        }

        function agregarProductoALaVenta(producto, precioUnitario) {
                    if (productosEnVenta.has(producto.id)) {
                        alert('El producto ya está en la lista. Modifica la cantidad.');
                        return;
                    }
                    if (filaVacia) filaVacia.remove();

                    const tr = document.createElement('tr');
                    tr.id = `producto-${producto.id}`;
                    const subtotal = precioUnitario * 1; 
                    
                    // Valor por defecto si viene nulo
                    const gananciaDefault = producto.ganancia || 100;

                    // --- CAMBIOS DE DISEÑO AQUÍ ---
                    // Agregué 'text-sm' (letra chica) y 'py-1' (menos alto) a los inputs
                    tr.innerHTML = `
                        <td class="py-4 px-4 text-xs text-gray-800">
                            <span class="font-medium">${producto.detalle}</span>
                            <span class="text-xs text-gray-500 block">Cód: ${producto.codigo}</span>
                        </td>
                        <td class="py-4 px-4 text-xs text-gray-500 text-right hidden md:table-cell"> ${parseFloat(producto.compra).toFixed(2)}</td>
                        <td class="py-4 px-4 text-right hidden md:table-cell">
                            <input type="number" name="dividido" value="${producto.dividido}" min="1" 
                                   class="w-14 text-center text-xs py-1 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </td>
                        <td class="py-4 px-4 text-right hidden md:table-cell">
                            <input type="number" name="ganancia" value="${gananciaDefault}" step="0.01" 
                                   class="w-16 text-center text-xs py-1 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </td>
                        <td class="py-4 px-4 text-xs text-gray-600 text-right" data-label="$ Unitario"> ${precioUnitario.toFixed(2)}</td>
                        <td class="py-4 px-4 text-center">
                            <input type="number" name="cantidad" value="1" min="1" 
                                   class="w-16 text-center text-xs py-1 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </td>
                        <td class="py-4 px-4 text-xs text-gray-900 text-right font-bold" data-label=" Subtotal"> ${subtotal.toFixed(2)}</td>
                        <td class="py-4 px-4 text-center">
                            <button name="quitar" class="text-red-500 hover:text-red-700">X</button>
                        </td>
                    `;

                    tr.dataset.productoId = producto.id;
                    tr.dataset.precioCompra = producto.compra; 
                    tr.dataset.precioUnitario = precioUnitario; 
                    tr.dataset.detalle = producto.detalle;

                    cuerpoTabla.appendChild(tr);
                    productosEnVenta.set(producto.id, tr); 
                    inputBusqueda.value = '';
                    divResultados.innerHTML = '<div class="p-4 text-center text-gray-500">Escribe para buscar...</div>';
                    actualizarGranTotal();
                    tr.querySelector('input[name="cantidad"]').focus();
                }

        function actualizarTotales(event) {
            const input = event.target;
            
            // AHORA ESCUCHAMOS TAMBIÉN EL CAMBIO EN 'ganancia'
            if (input.name !== 'cantidad' && input.name !== 'dividido' && input.name !== 'ganancia') return;

            const tr = input.closest('tr');
            if (!tr) return;

            const precioCompra = parseFloat(tr.dataset.precioCompra) || 0;
            
            // TOMAMOS LA GANANCIA DEL INPUT
            const inputGanancia = tr.querySelector('input[name="ganancia"]');
            const porcentajeGanancia = parseFloat(inputGanancia.value) || 100;

            const dividido = parseInt(tr.querySelector('input[name="dividido"]').value, 10) || 1; 
            const cantidad = parseInt(tr.querySelector('input[name="cantidad"]').value, 10) || 0;

            // CÁLCULO DINÁMICO
            const factorGanancia = 1 + (porcentajeGanancia / 100);
            const precioCalculado = (precioCompra / dividido) * factorGanancia * 1.105;
            const nuevoPrecioUnitario = Math.ceil(precioCalculado / MULTIPLO_REDONDEO) * MULTIPLO_REDONDEO;
            
            const subtotal = cantidad * nuevoPrecioUnitario;

            tr.querySelector('[data-label="$ Unitario"]').textContent = `$ ${nuevoPrecioUnitario.toFixed(2)}`;
            tr.querySelector('[data-label="$ Subtotal"]').textContent = `$ ${subtotal.toFixed(2)}`;
            tr.dataset.precioUnitario = nuevoPrecioUnitario;

            actualizarGranTotal();
        }

        function quitarProducto(event) {
            const boton = event.target.closest('button[name="quitar"]');
            if (!boton) return;
            const tr = boton.closest('tr');
            productosEnVenta.delete(tr.dataset.productoId);
            tr.remove();
            if (productosEnVenta.size === 0 && filaVacia) cuerpoTabla.appendChild(filaVacia);
            actualizarGranTotal();
        }

        function actualizarGranTotal() {
            let total = 0;
            productosEnVenta.forEach(tr => {
                const precioUnitario = parseFloat(tr.dataset.precioUnitario) || 0;
                const cantidad = parseInt(tr.querySelector('input[name="cantidad"]').value, 10) || 0;
                total += cantidad * precioUnitario;
            });
            spanGranTotal.textContent = `$ ${total.toFixed(2)}`;
            btnCompletarVenta.disabled = (total === 0);
        }
        
        async function guardarVenta(conFactura) { 
            const itemsParaGuardar = [];
            let datosValidos = true;

            productosEnVenta.forEach(tr => {
                const unitario = parseFloat(tr.dataset.precioUnitario);
                const cantidad = parseInt(tr.querySelector('input[name="cantidad"]').value, 10);
                if (isNaN(unitario) || isNaN(cantidad) || unitario <= 0 || cantidad <= 0) datosValidos = false;

                itemsParaGuardar.push({
                    id: tr.dataset.productoId,
                    detalle: tr.dataset.detalle,
                    unitario: unitario,
                    cantidad: cantidad,
                    subtotal: unitario * cantidad
                });
            });

            if (!datosValidos) {
                alert('Hay datos inválidos en la tabla.');
                return;
            }

            btnCompletarVenta.textContent = 'Guardando...';
            try {
                const respuesta = await fetch('venta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ factura: conFactura, items: itemsParaGuardar })
                });
                const resultado = await respuesta.json();
                if (!respuesta.ok || !resultado.exito) throw new Error(resultado.mensaje);

                alert(resultado.mensaje); 
                cuerpoTabla.innerHTML = '';
                if (filaVacia) cuerpoTabla.appendChild(filaVacia);
                productosEnVenta.clear(); 
                spanGranTotal.textContent = '$ 0.00';
                spanNumeroVenta.textContent = parseInt(spanNumeroVenta.textContent) + 1;
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                actualizarGranTotal();
                btnCompletarVenta.textContent = 'Completar Venta';
            }
        }

        inputBusqueda.addEventListener('keyup', (e) => {
            clearTimeout(timeoutBusqueda);
            spinnerBusqueda.classList.remove('hidden'); 
            timeoutBusqueda = setTimeout(buscarProductos, 300); 
        });

        cuerpoTabla.addEventListener('change', actualizarTotales);
        cuerpoTabla.addEventListener('keyup', actualizarTotales);
        cuerpoTabla.addEventListener('click', quitarProducto);
        
        btnCompletarVenta.addEventListener('click', () => {
            if (productosEnVenta.size > 0) modalFactura.classList.remove('hidden');
        });
        btnFacturaSi.addEventListener('click', () => {
            modalFactura.classList.add('hidden');
            guardarVenta(true);
        });
        btnFacturaNo.addEventListener('click', () => {
            modalFactura.classList.add('hidden');
            guardarVenta(false);
        });
    });
    </script>

    <div id="modal-factura" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-xl shadow-2xl max-w-sm mx-auto">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Confirmar Venta</h3>
            <p class="text-md text-gray-700 mb-8">¿Registrar esta venta como "Factura SI"?</p>
            <div class="flex justify-end gap-4">
                <button id="btn-factura-no" class="px-6 py-3 bg-gray-200 text-gray-800 font-bold rounded-lg hover:bg-gray-300">No</button>
                <button id="btn-factura-si" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">Sí</button>
            </div>
        </div>
    </div>
</body>
</html>