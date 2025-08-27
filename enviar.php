<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Contacto</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 800px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .header {
            background: #4b6cb7;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .form-container {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .btn-primary {
            background: #4b6cb7;
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #3a5999;
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 10px;
            display: none;
        }
        .contact-info {
            background: #f8f9fa;
            padding: 30px;
            border-left: 1px solid #eee;
        }
        .contact-info h4 {
            color: #4b6cb7;
            margin-bottom: 20px;
        }
        .info-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .info-item i {
            width: 30px;
            color: #4b6cb7;
            font-size: 1.2rem;
        }
        .countdown {
            font-weight: bold;
            color: #4b6cb7;
        }
        @media (max-width: 768px) {
            .contact-info {
                border-left: none;
                border-top: 1px solid #eee;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-envelope"></i>
            <h2>Formulario de Contacto</h2>
            <p>Envíanos tus consultas y te responderemos a la brevedad</p>
        </div>
        
        <div class="row">
            <div class="col-md-7">
                <div class="form-container">
                    <div class="alert alert-success" id="successAlert">
                        <i class="fas fa-check-circle"></i> 
                        <span id="successMessage">¡Mensaje enviado con éxito! Serás redirigido a la página principal en <span class="countdown">5</span> segundos.</span>
                    </div>
                    
                    <div class="alert alert-danger" id="errorAlert">
                        <i class="fas fa-exclamation-circle"></i> Hubo un problema al enviar el mensaje. Intenta nuevamente.
                    </div>
                    
                    <form id="contactForm" method="POST">
                        <div class="form-group">
                            <label for="name" class="form-label">Nombre completo *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Correo electrónico *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message" class="form-label">Mensaje *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-comment"></i></span>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i> Enviar mensaje
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="contact-info">
                    <h4>Información de contacto</h4>
                    
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span>info@silviacataldi.com.ar</span>
                    </div>
                    
                    <div class="info-item">
                        <i class="fa-brands fa-square-whatsapp"></i>
                        <span><a href="https://wa.me/+5491132905768">Enviar Whatsapp</a></span>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Ferroviarios Argentinos 735, Bragado, BA</span>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span>Lunes a Viernes: 9:00 - 16:00</span>
                    </div>
                    
                    <hr>
                    
                    <p class="text-muted">* Todos los campos son obligatorios. Te responderemos en un plazo máximo de 24 horas.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <!-- JavaScript para validación del formulario y redirección -->
    <script>
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validación básica
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const message = document.getElementById('message').value;
            
            if (name === '' || email === '' || message === '') {
                document.getElementById('errorAlert').style.display = 'block';
                document.getElementById('successAlert').style.display = 'none';
                return;
            }
            
            // Mostrar mensaje de éxito
            document.getElementById('successAlert').style.display = 'block';
            document.getElementById('errorAlert').style.display = 'none';
            
            // Deshabilitar el formulario para evitar múltiples envíos
            document.querySelectorAll('#contactForm input, #contactForm textarea, #contactForm button').forEach(element => {
                element.disabled = true;
            });
            
            // Iniciar cuenta regresiva para redirección
            let seconds = 5;
            const countdownElement = document.querySelector('.countdown');
            const countdownInterval = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = 'index.php'; // Cambia por tu URL
                }
            }, 1000);
            
            // Crear FormData para enviar
            const formData = new FormData(this);
            
            // Enviar datos mediante AJAX
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en el envío');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('errorAlert').style.display = 'block';
                document.getElementById('successAlert').style.display = 'none';
                
                // Rehabilitar el formulario en caso de error
                document.querySelectorAll('#contactForm input, #contactForm textarea, #contactForm button').forEach(element => {
                    element.disabled = false;
                });
                
                clearInterval(countdownInterval);
            });
        });
    </script>
    
    <!-- PHP para procesar el formulario -->
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Recoger los datos del formulario
        $name = htmlspecialchars($_POST['name']);
        $email = htmlspecialchars($_POST['email']);
        $message = htmlspecialchars($_POST['message']);
        
        // Dirección de correo destino
        $to = "info@silviacataldi.com.ar";
        
        // Asunto del correo
        $subject = "Nuevo mensaje de contacto de $name";
        
        // Construir el cuerpo del mensaje
        $body = "Has recibido un nuevo mensaje de contacto:\n\n";
        $body .= "Nombre: $name\n";
        $body .= "Email: $email\n";
        $body .= "Mensaje:\n$message\n";
        
        // Cabeceras del correo
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Enviar el correo
        $success = mail($to, $subject, $body, $headers);
        
        // Opción 2: Redirección con PHP (comenta la opción JS si usas esta)
        // if ($success) {
        //     header("Location: index.php?envio=exitoso");
        //     exit();
        // } else {
        //     header("Location: index.php?envio=error");
        //     exit();
        // }
    }
    ?>
</body>
</html>