// assets/js/firebase-init.js
// Este archivo inicializa Firebase y maneja los tokens FCM

class GicaFCMHandler {
    constructor() {
        this.config = gicaFirebaseConfig?.config || null;
        this.ajaxUrl = gicaFirebaseConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
        this.nonce = gicaFirebaseConfig?.nonce || '';
        this.vapidKey = gicaFirebaseConfig?.vapidKey || '';
        this.messaging = null;
        this.currentToken = null;
        
        // Inicializar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }
    
    async init() {
        // Verificaciones de compatibilidad
        if (!this.checkBrowserSupport()) {
            console.warn('[GICA FCM] Navegador no soporta notificaciones push');
            return;
        }
        
        if (!this.config) {
            console.error('[GICA FCM] Configuración Firebase no encontrada');
            return;
        }

        if (!this.vapidKey) {
            console.error('[GICA FCM] VAPID Key no encontrada');
            return;
        }
        
        try {
            await this.registerServiceWorker();
            await this.initializeFirebase();
            await this.requestNotificationPermission();
            await this.getRegistrationToken();
            this.setupMessageListener();
        } catch (error) {
            console.error('[GICA FCM] Error en inicialización:', error);
        }
    }
    
    checkBrowserSupport() {
        return 'serviceWorker' in navigator && 
               'Notification' in window && 
               'PushManager' in window;
    }
    
    async registerServiceWorker() {
        try {
            const swPath = '/wp-content/plugins/GICAACCOUNT/firebase-messaging-sw.js';
            const registration = await navigator.serviceWorker.register(swPath, {
                scope: '/wp-content/plugins/GICAACCOUNT/'
            });
            
            console.log('[GICA FCM] Service Worker registrado:', registration);
            
            // Esperar a que esté activo
            if (registration.installing) {
                await new Promise((resolve) => {
                    registration.installing.addEventListener('statechange', () => {
                        if (registration.installing.state === 'activated') {
                            resolve();
                        }
                    });
                });
            }
            
            return registration;
        } catch (error) {
            console.error('[GICA FCM] Error registrando Service Worker:', error);
            throw error;
        }
    }
    
    async initializeFirebase() {
        try {
            // Verificar si Firebase ya está inicializado
            if (typeof firebase === 'undefined') {
                throw new Error('Firebase SDK no cargado');
            }
            
            // Inicializar Firebase solo si no existe una app
            if (!firebase.apps.length) {
                firebase.initializeApp(this.config);
            }
            
            // Inicializar messaging
            this.messaging = firebase.messaging();
            
            console.log('[GICA FCM] Firebase inicializado correctamente');
        } catch (error) {
            console.error('[GICA FCM] Error inicializando Firebase:', error);
            throw error;
        }
    }
    
    async requestNotificationPermission() {
        try {
            const permission = await Notification.requestPermission();
            
            console.log('[GICA FCM] Permiso de notificación:', permission);
            
            if (permission !== 'granted') {
                throw new Error(`Permiso denegado: ${permission}`);
            }
            
            return permission;
        } catch (error) {
            console.error('[GICA FCM] Error solicitando permisos:', error);
            throw error;
        }
    }
    
    async getRegistrationToken() {
        try {
            // Usar VAPID key real
            const vapidKey = this.vapidKey;
            
            const token = await this.messaging.getToken({
                vapidKey: vapidKey,
                serviceWorkerRegistration: await navigator.serviceWorker.getRegistration('/wp-content/plugins/GICAACCOUNT/')
            });
            
            if (token) {
                console.log('[GICA FCM] Token obtenido:', token);
                this.currentToken = token;
                await this.saveTokenToServer(token);
                return token;
            } else {
                throw new Error('No se pudo obtener el token FCM');
            }
        } catch (error) {
            console.error('[GICA FCM] Error obteniendo token:', error);
            throw error;
        }
    }
    
    async saveTokenToServer(token) {
        try {
            const formData = new FormData();
            formData.append('action', 'gica_save_fcm_token');
            formData.append('token', token);
            formData.append('nonce', this.nonce);
            formData.append('user_agent', navigator.userAgent);
            formData.append('timestamp', Date.now());
            
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('[GICA FCM] Token guardado en servidor:', data);
            } else {
                console.error('[GICA FCM] Error guardando token:', data);
            }
            
            return data;
        } catch (error) {
            console.error('[GICA FCM] Error enviando token al servidor:', error);
            throw error;
        }
    }
    
    setupMessageListener() {
        // Escuchar mensajes cuando la app está en primer plano
        this.messaging.onMessage((payload) => {
            console.log('[GICA FCM] Mensaje recibido en primer plano:', payload);
            
            // Mostrar notificación personalizada o usar la nativa del navegador
            this.showForegroundNotification(payload);
        });
        
        // Escuchar cambios en el token
        this.messaging.onTokenRefresh(async () => {
            try {
                console.log('[GICA FCM] Token actualizado');
                await this.getRegistrationToken();
            } catch (error) {
                console.error('[GICA FCM] Error actualizando token:', error);
            }
        });
    }
    
    showForegroundNotification(payload) {
        const title = payload.notification?.title || 'Nueva notificación';
        const options = {
            body: payload.notification?.body || 'Tienes una nueva notificación',
            icon: payload.notification?.icon || '/wp-content/plugins/GICAACCOUNT/assets/images/icon.png',
            image: payload.notification?.image,
            data: payload.data,
            tag: 'gica-notification-foreground'
        };
        
        // Verificar si tenemos permisos
        if (Notification.permission === 'granted') {
            const notification = new Notification(title, options);
            
            notification.onclick = function(event) {
                event.preventDefault();
                console.log('[GICA FCM] Clic en notificación de primer plano');
                
                // Manejar clic (abrir URL, etc.)
                if (payload.data?.url) {
                    window.open(payload.data.url, '_blank');
                }
                
                notification.close();
            };
            
            // Auto-cerrar después de 5 segundos
            setTimeout(() => {
                notification.close();
            }, 5000);
        }
    }
    
    // Método público para testing
    async testNotification() {
        if (!this.currentToken) {
            console.error('[GICA FCM] No hay token disponible');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'gica_send_test_notification');
            formData.append('nonce', this.nonce);
            
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            console.log('[GICA FCM] Test notification result:', data);
            
            return data;
        } catch (error) {
            console.error('[GICA FCM] Error enviando notificación de prueba:', error);
            throw error;
        }
    }
}

// Inicializar cuando se cargue la página
const gicaFCM = new GicaFCMHandler();

// Exponer para debugging en consola
window.gicaFCM = gicaFCM;

// Función de testing para la consola del navegador
window.testGicaFCM = function() {
    console.log('=== GICA FCM Debug Info ===');
    console.log('Config:', gicaFCM.config);
    console.log('VAPID Key:', gicaFCM.vapidKey);
    console.log('Current Token:', gicaFCM.currentToken);
    console.log('Browser Support:', gicaFCM.checkBrowserSupport());
    console.log('Notification Permission:', Notification.permission);
    
    // Test notification
    gicaFCM.testNotification();
};