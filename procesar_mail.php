<?php
// procesar_mail.php
header('Content-Type: application/json');

// Evitar acceso directo
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// 1. Recoger y limpiar datos
$name = strip_tags(trim($_POST['name'] ?? ''));
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$message = strip_tags(trim($_POST['message'] ?? ''));

// 2. Validar
if (empty($name) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
    exit;
}

// 3. Configurar correo
$to = "info@silviacataldi.com.ar"; 
$subject = "Nuevo mensaje web de: $name";
$body = "Nombre: $name\nEmail: $email\n\nMensaje:\n$message";
$headers = "From: no-reply@silviacataldi.com.ar\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// 4. Enviar y responder JSON
if (mail($to, $subject, $body, $headers)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error del servidor de correo']);
}
?>