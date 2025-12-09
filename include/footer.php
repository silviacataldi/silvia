<footer class="py-5 bg-dark">
    <div class="container px-4 px-lg-5"><p class="m-0 text-center text-white">Copyright &copy; Silvia Cataldi 2023</p></div>
</footer>

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
                <div class="alert alert-success" id="successAlert" style="display:none;">
                    <i class="fas fa-check-circle"></i> 
                    <span id="successMessage">¡Mensaje enviado con éxito! Cerrando en <span class="countdown">5</span>...</span>
                </div>
                
                <div class="alert alert-danger" id="errorAlert" style="display:none;">
                    <i class="fas fa-exclamation-circle"></i> <span id="errorMessage">Hubo un problema. Intenta nuevamente.</span>
                </div>
                
                <form id="contactForm">
                    <div class="form-group mb-3">
                        <label for="name" class="form-label">Nombre completo *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">Correo electrónico *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="message" class="form-label">Mensaje *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-comment"></i></span>
                            <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group text-end">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i> Enviar mensaje
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-center">
                <p class="text-muted small mb-0">* Te responderemos en un plazo máximo de 24 horas.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/scripts.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const contactModalElement = document.getElementById('contactModal');
        // Inicializamos el modal con la API de Bootstrap 5
        const contactModal = new bootstrap.Modal(contactModalElement);
        let countdownInterval = null;
        
        const form = document.getElementById('contactForm');
        const successAlert = document.getElementById('successAlert');
        const errorAlert = document.getElementById('errorAlert');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // UI: Bloquear formulario y mostrar estado de carga
            successAlert.style.display = 'none';
            errorAlert.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            
            const formData = new FormData(this);
            
            // CAMBIO CLAVE: Apuntar al archivo dedicado procesar_mail.php
            fetch('procesar_mail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json()) // Esperamos una respuesta JSON
            .then(data => {
                if (data.success) {
                    // ÉXITO
                    successAlert.style.display = 'block';
                    form.reset();
                    
                    // Cuenta regresiva
                    let seconds = 5;
                    const countdownElement = document.querySelector('.countdown');
                    if(countdownElement) countdownElement.textContent = seconds;
                    
                    if (countdownInterval) clearInterval(countdownInterval);
                    
                    countdownInterval = setInterval(() => {
                        seconds--;
                        if(countdownElement) countdownElement.textContent = seconds;
                        
                        if (seconds <= 0) {
                            clearInterval(countdownInterval);
                            contactModal.hide();
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Enviar mensaje';
                            successAlert.style.display = 'none';
                        }
                    }, 1000);
                    
                } else {
                    // ERROR LÓGICO (ej. email inválido)
                    throw new Error(data.message || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorAlert.style.display = 'block';
                if(document.getElementById('errorMessage')) {
                    document.getElementById('errorMessage').textContent = error.message || 'Hubo un error al conectar.';
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Enviar mensaje';
            });
        });

        // Limpieza al cerrar modal manualmente
        contactModalElement.addEventListener('hidden.bs.modal', function () {
            if (countdownInterval) clearInterval(countdownInterval);
            form.reset();
            successAlert.style.display = 'none';
            errorAlert.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Enviar mensaje';
        });
    });
</script>