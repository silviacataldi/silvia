<?php 
// CONFIGURACIÓN DE LA BASE DE DATOS ---
$servidor = "localhost";
$usuario = "er000470_nubes";
$contrasena = "LEri84mudi";
$base_de_datos = "er000470_nubes";

$conexion = new mysqli($servidor, $usuario, $contrasena, $base_de_datos);
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");
 ?>