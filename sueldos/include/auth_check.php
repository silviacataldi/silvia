<?php
// include/auth_check.php
session_start();

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Verificar si hay una empresa seleccionada
// (Omitimos esta validación si estamos justo en la página de selección)
$pagina_actual = basename($_SERVER['PHP_SELF']);

// LISTA BLANCA: Páginas permitidas sin tener empresa seleccionada
$paginas_globales = ['dashboard.php', 'cliente_form.php', 'logout.php'];

if (!isset($_SESSION['tenant_id']) && !in_array($pagina_actual, $paginas_globales)) {
    // Si no eligió empresa y quiere entrar a liquidar, lo mandamos al dashboard
    header("Location: dashboard.php");
    exit;
}

// Si todo está bien, definimos la variable global para usar en los scripts

if (isset($_SESSION['tenant_id'])) {
    $tenant_id_actual = $_SESSION['tenant_id'];
    $tenant_nombre_actual = $_SESSION['tenant_nombre'];
}
?>