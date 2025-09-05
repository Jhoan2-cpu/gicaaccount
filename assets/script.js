jQuery(document).ready(function($) {
    
    // Authentication tabs functionality
    $('.gica-tab-btn').on('click', function() {
        var tab = $(this).data('tab');
        
        // Update active tab button
        $('.gica-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        // Show corresponding tab content
        $('.gica-tab-content').removeClass('active');
        $('#gica-' + tab + '-tab').addClass('active');
    });
    
    // Toggle password visibility
    $('.gica-toggle-password').on('click', function() {
        var $input = $(this).siblings('input');
        var type = $input.attr('type') === 'password' ? 'text' : 'password';
        
        $input.attr('type', type);
        $(this).text(type === 'password' ? '👁' : '🙈');
    });
    
    // Login form submission
    $('#gica-login-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
        
        // Disable form and show loading state
        $submitBtn.prop('disabled', true).addClass('loading');
        
        var formData = {
            action: 'gica_account_action',
            action_type: 'login_user',
            nonce: gica_ajax.nonce,
            username: $(this).find('input[name="username"]').val(),
            password: $(this).find('input[name="password"]').val(),
            remember: $(this).find('input[name="remember"]').is(':checked')
        };
        
        $.post(gica_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showMessage('success', 'Inicio de sesión exitoso. Redirigiendo...');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage('error', response.data || 'Error al iniciar sesión');
                    $submitBtn.prop('disabled', false).removeClass('loading');
                }
            })
            .fail(function() {
                showMessage('error', 'Error de conexión. Intenta nuevamente.');
                $submitBtn.prop('disabled', false).removeClass('loading');
            });
    });
    
    // Register form submission
    $('#gica-register-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
        
        var password = $(this).find('input[name="password"]').val();
        var confirmPassword = $(this).find('input[name="confirm_password"]').val();
        
        // Client-side validations
        if (password !== confirmPassword) {
            showMessage('error', 'Las contraseñas no coinciden');
            return;
        }
        
        if (password.length < 6) {
            showMessage('error', 'La contraseña debe tener al menos 6 caracteres');
            return;
        }
        
        // Disable form and show loading state
        $submitBtn.prop('disabled', true).addClass('loading');
        
        var formData = {
            action: 'gica_account_action',
            action_type: 'register_user',
            nonce: gica_ajax.nonce,
            username: $(this).find('input[name="username"]').val(),
            first_name: $(this).find('input[name="first_name"]').val(),
            last_name: $(this).find('input[name="last_name"]').val(),
            email: $(this).find('input[name="email"]').val(),
            password: password
        };
        
        $.post(gica_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showMessage('success', response.data + ' Ya puedes iniciar sesión.');
                    // Clear form
                    $form[0].reset();
                    // Switch to login tab after a delay
                    setTimeout(function() {
                        $('.gica-tab-btn[data-tab="login"]').click();
                    }, 2000);
                } else {
                    showMessage('error', response.data || 'Error al registrar usuario');
                }
                $submitBtn.prop('disabled', false).removeClass('loading');
            })
            .fail(function() {
                showMessage('error', 'Error de conexión. Intenta nuevamente.');
                $submitBtn.prop('disabled', false).removeClass('loading');
            });
    });
    
    // Navigation functionality
    $('.gica-nav-btn').on('click', function() {
        var section = $(this).data('section');
        
        if (section === 'logout') {
            handleLogout();
            return;
        }
        
        // Update active nav button
        $('.gica-nav-btn').removeClass('active');
        $(this).addClass('active');
        
        // Show corresponding section
        $('.gica-section').removeClass('active');
        $('#gica-' + section).addClass('active');
    });
    
    // Account details form
    $('#gica-account-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'gica_account_action',
            action_type: 'update_account',
            nonce: gica_ajax.nonce,
            display_name: $('#display_name').val(),
            user_email: $('#user_email').val(),
            first_name: $('#first_name').val(),
            last_name: $('#last_name').val()
        };
        
        $.post(gica_ajax.ajax_url, formData, function(response) {
            showMessage(response.success ? 'success' : 'error', response.data);
        });
    });
    
    // Contact info form
    $('#gica-contact-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'gica_account_action',
            action_type: 'update_contact',
            nonce: gica_ajax.nonce,
            phone: $('#phone').val(),
            address: $('#address').val()
        };
        
        $.post(gica_ajax.ajax_url, formData, function(response) {
            showMessage(response.success ? 'success' : 'error', response.data);
        });
    });
    
    // Additional info form
    $('#gica-additional-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'gica_account_action',
            action_type: 'update_additional',
            nonce: gica_ajax.nonce,
            dni: $('#dni').val(),
            city: $('#city').val(),
            region: $('#region').val(),
            country: $('#country').val(),
            reference: $('#reference').val()
        };
        
        $.post(gica_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                showMessage('success', response.data.message);
                
                // Update completion percentage
                if (response.data.completion_percentage) {
                    updateCompletionBar(response.data.completion_percentage);
                }
            } else {
                showMessage('error', response.data);
            }
        });
    });
    
    // Register button functionality
    $('#gica-register-btn').on('click', function() {
        showRegistrationForm();
    });
    
    function handleLogout() {
        if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
            var formData = {
                action: 'gica_account_action',
                action_type: 'logout',
                nonce: gica_ajax.nonce
            };
            
            $.post(gica_ajax.ajax_url, formData, function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                }
            });
        }
    }
    
    function showRegistrationForm() {
        var registrationHTML = `
            <div class="gica-registration-modal">
                <div class="gica-modal-content">
                    <h3>Crear Nueva Cuenta</h3>
                    <form id="gica-registration-form">
                        <div class="gica-field">
                            <label for="reg-username">Nombre de Usuario:</label>
                            <input type="text" id="reg-username" name="username" required>
                        </div>
                        <div class="gica-field">
                            <label for="reg-email">Email:</label>
                            <input type="email" id="reg-email" name="email" required>
                        </div>
                        <div class="gica-field">
                            <label for="reg-password">Contraseña:</label>
                            <input type="password" id="reg-password" name="password" required>
                        </div>
                        <div class="gica-field">
                            <label for="reg-password-confirm">Confirmar Contraseña:</label>
                            <input type="password" id="reg-password-confirm" name="password_confirm" required>
                        </div>
                        <div class="gica-modal-buttons">
                            <button type="submit" class="gica-btn gica-btn-primary">Registrarse</button>
                            <button type="button" class="gica-btn gica-btn-secondary" id="cancel-registration">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        $('body').append(registrationHTML);
        
        // Add modal styles dynamically
        var modalCSS = `
            <style>
                .gica-registration-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                }
                .gica-modal-content {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    width: 90%;
                    max-width: 400px;
                }
                .gica-modal-buttons {
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                    margin-top: 20px;
                }
            </style>
        `;
        $('head').append(modalCSS);
    }
    
    // Handle registration form submission
    $(document).on('submit', '#gica-registration-form', function(e) {
        e.preventDefault();
        
        var password = $('#reg-password').val();
        var passwordConfirm = $('#reg-password-confirm').val();
        
        if (password !== passwordConfirm) {
            showMessage('error', 'Las contraseñas no coinciden');
            return;
        }
        
        var formData = {
            action: 'gica_account_action',
            action_type: 'register_user',
            nonce: gica_ajax.nonce,
            username: $('#reg-username').val(),
            email: $('#reg-email').val(),
            password: password
        };
        
        $.post(gica_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                $('.gica-registration-modal').remove();
                showMessage('success', response.data + ' Por favor, inicia sesión.');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showMessage('error', response.data);
            }
        });
    });
    
    // Cancel registration
    $(document).on('click', '#cancel-registration', function() {
        $('.gica-registration-modal').remove();
    });
    
    // Close modal on outside click
    $(document).on('click', '.gica-registration-modal', function(e) {
        if (e.target === this) {
            $(this).remove();
        }
    });
    
    function showMessage(type, message) {
        // Remove existing messages
        $('.gica-message').remove();
        
        // Create new message
        var messageDiv = $('<div class="gica-message ' + type + '">' + message + '</div>');
        
        // Find the appropriate container
        var container = $('#gica-account-container');
        if (container.length === 0) {
            container = $('body');
        }
        
        container.prepend(messageDiv);
        messageDiv.show();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            messageDiv.fadeOut();
        }, 5000);
    }
    
    // Form validation
    $('input[required]').on('blur', function() {
        if ($(this).val() === '') {
            $(this).css('border-color', '#e74c3c');
        } else {
            $(this).css('border-color', '#e0e0e0');
        }
    });
    
    // Email validation
    $('input[type="email"]').on('blur', function() {
        var email = $(this).val();
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email !== '' && !emailRegex.test(email)) {
            $(this).css('border-color', '#e74c3c');
            showMessage('error', 'Por favor, introduce un email válido');
        } else if (email !== '') {
            $(this).css('border-color', '#27ae60');
        }
    });
    
    // Real-time password confirmation validation
    $('#gica-register-form input[name="confirm_password"]').on('input', function() {
        var password = $('#gica-register-form input[name="password"]').val();
        var confirmPassword = $(this).val();
        
        if (confirmPassword !== '' && password !== confirmPassword) {
            $(this).css('border-color', '#e74c3c');
        } else if (confirmPassword !== '') {
            $(this).css('border-color', '#27ae60');
        }
    });
    
    // Update completion bar animation
    function updateCompletionBar(percentage) {
        var $fill = $('.gica-completion-fill');
        var $label = $('.gica-completion-label');
        
        $fill.css('width', percentage + '%');
        $label.text('Perfil completado: ' + percentage + '%');
    }
    
    // Required field validation
    $('input[required], textarea[required]').on('blur', function() {
        var $field = $(this);
        var value = $field.val().trim();
        
        if (value === '') {
            $field.css('border-color', '#e74c3c');
            $field.addClass('error');
        } else {
            $field.css('border-color', '#27ae60');
            $field.removeClass('error');
        }
    });
    
    // Form submission validation
    $('form').on('submit', function(e) {
        var $form = $(this);
        var $requiredFields = $form.find('input[required], textarea[required]');
        var hasErrors = false;
        
        $requiredFields.each(function() {
            var $field = $(this);
            if ($field.val().trim() === '') {
                $field.css('border-color', '#e74c3c');
                $field.addClass('error');
                hasErrors = true;
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            showMessage('error', 'Por favor, completa todos los campos requeridos');
        }
    });
    
    // Initialize completion bar animation on page load
    $(window).on('load', function() {
        var $fill = $('.gica-completion-fill');
        if ($fill.length) {
            var targetWidth = $fill.css('width');
            $fill.css('width', '0%');
            
            setTimeout(function() {
                $fill.css('width', targetWidth);
            }, 500);
        }
    });
});