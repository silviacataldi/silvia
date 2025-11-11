<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotillón Nubes Blancas - Sistema de Cajas</title>
    <!-- Incluimos Tailwind CSS desde el CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Incluimos la fuente Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Configuración de Tailwind para usar la fuente Inter
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-gray-100 font-sans flex items-center justify-center min-h-screen">

    <div class="w-full max-w-5xl mx-auto p-6 md:p-8">
        
        <header class="text-center mb-12">
            <h1 class="text-4xl font-bold text-blue-900">Cotillón Nubes Blancas</h1>
            <p class="text-lg text-blue-700 mt-2">Sistema de Manejo de Cajas</p>
        </header>
        
        <!-- Contenedor de los módulos -->
        <main class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
            
            <!-- Módulo 1: Venta -->
            <a href="venta.php" 
               class="group block p-8 bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                
                <!-- Icono -->
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                
                <h2 class="text-2xl font-semibold text-gray-900 mb-2">Venta</h2>
                <p class="text-gray-600">Registrar una nueva venta y procesar pagos.</p>
                <span class="text-blue-500 font-medium mt-4 inline-block group-hover:translate-x-1 transition-transform duration-300">
                    Acceder &rarr;
                </span>
            </a>
            
            <!-- Módulo 2: Actualizar -->
            <a href="actualizar.php" 
               class="group block p-8 bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                
                <!-- Icono (Cambiado a un lápiz para "actualizar") -->
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mb-4">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>
                
                <h2 class="text-2xl font-semibold text-gray-900 mb-2">Actualizar</h2>
                <p class="text-gray-600">Actualizar precios y productos.</p>
                <span class="text-blue-500 font-medium mt-4 inline-block group-hover:translate-x-1 transition-transform duration-300">
                    Acceder &rarr;
                </span>
            </a>
            
            <!-- Módulo 3: Control -->
            <a href="control.php" 
               class="group block p-8 bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                
                <!-- Icono -->
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                
                <h2 class="text-2xl font-semibold text-gray-900 mb-2">Contable</h2>
                <p class="text-gray-600">Datos y listados contables.</p>
                <span class="text-blue-500 font-medium mt-4 inline-block group-hover:translate-x-1 transition-transform duration-300">
                    Acceder &rarr;
                </span>
            </a>
            
        </main>

    </div>

</body>
</html>