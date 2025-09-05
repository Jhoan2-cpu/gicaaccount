// assets/js/admin-fcm.js
// JavaScript para el admin de FCM con debug mejorado

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 GICA FCM Admin Script Loaded');
    
    // Agregar debug al formulario
    const fcmForm = document.querySelector('.gica-fcm-form');
    if (fcmForm) {
        fcmForm.addEventListener('submit', function(e) {
            console.log('📝 GICA FCM: Enviando formulario...');
            
            const formData = new FormData(fcmForm);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                if (key.includes('firebase') || key.includes('fcm')) {
                    // Solo mostrar primeros caracteres para seguridad
                    if (key.includes('key') && value.length > 20) {
                        data[key] = value.substring(0, 20) + '...';
                    } else {
                        data[key] = value;
                    }
                } else {
                    data[key] = value;
                }
            }
            
            console.log('📋 Datos del formulario FCM:', data);
            
            // Verificar campos requeridos
            const requiredFields = [
                'firebase_api_key',
                'firebase_project_id', 
                'firebase_messaging_sender_id',
                'firebase_app_id',
                'fcm_vapid_key'
            ];
            
            const missingFields = [];
            requiredFields.forEach(field => {
                if (!formData.get(field) || formData.get(field).trim() === '') {
                    missingFields.push(field);
                }
            });
            
            if (missingFields.length > 0) {
                console.warn('⚠️ GICA FCM: Campos faltantes:', missingFields);
                if (!confirm('Hay campos requeridos vacíos: ' + missingFields.join(', ') + '. ¿Continuar de todas formas?')) {
                    e.preventDefault();
                    return false;
                }
            } else {
                console.log('✅ GICA FCM: Todos los campos requeridos están completos');
            }
        });
    }
    
    // Función para verificar el estado de FCM
    window.checkGicaFCMStatus = function() {
        console.log('=== 🔍 GICA FCM Status Check ===');
        
        // Verificar opciones en el formulario
        const form = document.querySelector('.gica-fcm-form');
        if (form) {
            const fields = {
                'FCM Enabled': form.querySelector('input[name="fcm_enabled"]')?.checked,
                'Debug Mode': form.querySelector('input[name="fcm_debug_mode"]')?.checked,
                'API Key': form.querySelector('input[name="firebase_api_key"]')?.value?.length > 0,
                'Auth Domain': form.querySelector('input[name="firebase_auth_domain"]')?.value?.length > 0,
                'Project ID': form.querySelector('input[name="firebase_project_id"]')?.value,
                'Storage Bucket': form.querySelector('input[name="firebase_storage_bucket"]')?.value?.length > 0,
                'Sender ID': form.querySelector('input[name="firebase_messaging_sender_id"]')?.value,
                'App ID': form.querySelector('input[name="firebase_app_id"]')?.value?.length > 0,
                'VAPID Key': form.querySelector('textarea[name="fcm_vapid_key"]')?.value?.length > 0
            };
            
            console.table(fields);
        }
        
        // Verificar si hay elementos de estado en la página
        const statusElements = document.querySelectorAll('.gica-status-indicator');
        if (statusElements.length > 0) {
            console.log('📊 Status Indicators found:', statusElements.length);
            statusElements.forEach((el, index) => {
                console.log(`Status ${index + 1}:`, el.classList.contains('active') ? '✅ Active' : '❌ Inactive');
            });
        }
        
        // Verificar si hay tablas de debug
        const debugTable = document.querySelector('details table');
        if (debugTable) {
            console.log('🔍 Debug table found - checking values...');
            const rows = debugTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 3) {
                    console.log(`${cells[0].textContent}: ${cells[1].textContent} ${cells[2].textContent}`);
                }
            });
        }
        
        console.log('=== End FCM Status Check ===');
    };
    
    // Auto-check status on page load
    setTimeout(() => {
        if (typeof window.checkGicaFCMStatus === 'function') {
            window.checkGicaFCMStatus();
        }
    }, 1000);
    
    // Agregar botón de debug si estamos en modo debug
    const debugMode = document.querySelector('input[name="fcm_debug_mode"]');
    if (debugMode && debugMode.checked) {
        const debugButton = document.createElement('button');
        debugButton.type = 'button';
        debugButton.className = 'button button-secondary';
        debugButton.style.marginLeft = '10px';
        debugButton.textContent = '🔍 Check Status';
        debugButton.onclick = window.checkGicaFCMStatus;
        
        const submitButton = document.querySelector('input[name="gica_fcm_submit"]');
        if (submitButton) {
            submitButton.parentNode.insertBefore(debugButton, submitButton.nextSibling);
        }
    }
    
    // Resaltar campos vacíos en rojo
    const requiredInputs = document.querySelectorAll('input[name*="firebase"], textarea[name*="fcm"]');
    requiredInputs.forEach(input => {
        function checkEmpty() {
            if (input.value.trim() === '' && input.name !== 'firebase_auth_domain' && input.name !== 'firebase_storage_bucket') {
                input.style.borderColor = '#dc3232';
                input.style.backgroundColor = '#ffeaea';
            } else {
                input.style.borderColor = '';
                input.style.backgroundColor = '';
            }
        }
        
        checkEmpty();
        input.addEventListener('blur', checkEmpty);
        input.addEventListener('input', checkEmpty);
    });
    
    console.log('✅ GICA FCM Admin Script Ready');
});

// Función global para testing
window.testGicaFCMAdmin = function() {
    console.log('🧪 Testing GICA FCM Admin Functions...');
    
    if (typeof window.checkGicaFCMStatus === 'function') {
        window.checkGicaFCMStatus();
    } else {
        console.error('❌ checkGicaFCMStatus function not available');
    }
    
    // Test form submission (dry run)
    const form = document.querySelector('.gica-fcm-form');
    if (form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        console.log('📋 Current form data:', data);
    } else {
        console.error('❌ FCM form not found');
    }
};