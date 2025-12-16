<?php
// include/layout_head.php
require_once 'include/auth_check.php'; 

// Detectar página actual para marcarla 'active' en el menú
$pagina = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Sueldos - <?= htmlspecialchars($tenant_nombre_actual ?? 'Admin') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        /* Estilos personalizados para la marca */
        .navbar-brand { font-weight: bold; letter-spacing: 0.5px; }
        .nav-link.active { font-weight: bold; color: #fff !important; border-bottom: 2px solid white; }
    </style>
</head>
<body class="d-flex flex-column h-100 bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-building"></i> <?= htmlspecialchars($tenant_nombre_actual ?? 'Mi Estudio') ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= ($pagina == 'index.php' || $pagina == 'empleado_form.php') ? 'active' : '' ?>" 
                           href="index.php"><i class="bi bi-people"></i> Legajos</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= ($pagina == 'conceptos.php' || $pagina == 'concepto_form.php') ? 'active' : '' ?>" 
                           href="conceptos.php"><i class="bi bi-calculator"></i> Conceptos</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= ($pagina == 'liquidaciones.php' || $pagina == 'novedades.php' || $pagina == 'procesar_liquidacion.php') ? 'active' : '' ?>" 
                           href="liquidaciones.php"><i class="bi bi-cash-coin"></i> Liquidaciones</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_nombre']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Empresa Actual</h6></li>
                            <li class="px-3 text-muted small"><?= htmlspecialchars($tenant_nombre_actual) ?></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-arrow-repeat"></i> Cambiar Empresa</a></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="flex-shrink-0">
        <div class="container">