    <!-- Footer-->
    <footer class="py-5 bg-dark">
        <div class="container px-4 px-lg-5"><p class="m-0 text-center text-white">Copyright &copy; Silvia Cataldi 2023</p></div>
    </footer>
    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core theme JS-->
    <script src="js/scripts.js"></script>
    <!-- Bootstrap & Popper.js -->
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
         <!-- Ventana Modal con Formulario -->
     <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
             <div class="modal-dialog modal-dialog-centered modal-lg">
                 <div class="modal-content">
                     <div class="modal-header">
                         <h5 class="modal-title" id="contactModalLabel">
                             <i class="fas fa-envelope me-2"></i> Formulario de Contacto
                         </h5>
                         <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                     </div>
                     <div class="modal-body">
                         <div class="alert alert-success" id="successAlert">
                             <i class="fas fa-check-circle"></i> 
                             <span id="successMessage">¡Mensaje enviado con éxito! El modal se cerrará en <span class="countdown">5</span> segundos.</span>
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
                                     <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                                 </div>
                             </div>
                             
                             <div class="form-group">
                                 <button type="submit" class="btn btn-submit">
                                     <i class="fas fa-paper-plane me-2"></i> Enviar mensaje
                                 </button>
                             </div>
                         </form>
                     </div>
                     <div class="modal-footer">
                         <p class="text-muted small mb-0">* Todos los campos son obligatorios. Te responderemos en un plazo máximo de 24 horas.</p>
                     </div>
                 </div>
             </div>
         </div>

         <!-- Bootstrap & Popper.js -->
         <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
         <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
         
         <!-- JavaScript para validación del formulario y cierre del modal -->
         <script>
             // Inicializar modal de Bootstrap
             const contactModalElement = document.getElementById('contactModal');
             const contactModal = new bootstrap.Modal(contactModalElement);
             let countdownInterval = null;
             
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
                 
                 // Iniciar cuenta regresiva para cerrar el modal
                 let seconds = 5;
                 const countdownElement = document.querySelector('.countdown');
                 
                 // Limpiar cualquier intervalo previo
                 if (countdownInterval) {
                     clearInterval(countdownInterval);
                 }
                 
                 countdownInterval = setInterval(function() {
                     seconds--;
                     countdownElement.textContent = seconds;
                     
                     if (seconds <= 0) {
                         clearInterval(countdownInterval);
                         // Cerrar el modal correctamente
                         contactModal.hide();
                         
                         // Eliminar manualmente el backdrop si es necesario
                         const backdrops = document.querySelectorAll('.modal-backdrop');
                         backdrops.forEach(backdrop => {
                             backdrop.parentNode.removeChild(backdrop);
                         });
                         
                         // Restablecer el scroll del body
                         document.body.style.overflow = 'auto';
                         document.body.style.paddingRight = '0';
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

             // Limpiar el formulario cuando se cierra el modal
             contactModalElement.addEventListener('hidden.bs.modal', function () {
                 document.getElementById('contactForm').reset();
                 document.getElementById('successAlert').style.display = 'none';
                 document.getElementById('errorAlert').style.display = 'none';
                 
                 // Rehabilitar el formulario
                 document.querySelectorAll('#contactForm input, #contactForm textarea, #contactForm button').forEach(element => {
                     element.disabled = false;
                 });
                 
                 // Limpiar el intervalo si el modal se cierra manualmente
                 if (countdownInterval) {
                     clearInterval(countdownInterval);
                 }
             });
             
             // Manejar el evento cuando el modal se termina de ocultar
             contactModalElement.addEventListener('hidden.bs.modal', function() {
                 // Asegurarse de que se eliminen todos los backdrops
                 const backdrops = document.querySelectorAll('.modal-backdrop');
                 backdrops.forEach(backdrop => {
                     backdrop.parentNode.removeChild(backdrop);
                 });
                 
                 // Restablecer el scroll del body
                 document.body.style.overflow = 'auto';
                 document.body.style.paddingRight = '0';
                 
                 // Eliminar la clase modal-open del body
                 document.body.classList.remove('modal-open');
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
             
             // No es necesaria la redirección ya que cerramos el modal con JS
         }
         ?>   
</body>