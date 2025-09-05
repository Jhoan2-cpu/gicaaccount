// firebase-messaging-sw.js
// IMPORTANTE: Este archivo debe estar en la raíz del plugin

// Importar Firebase scripts - usar la versión específica
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');

// Configuración Firebase real
const firebaseConfig = {
  apiKey: "AIzaSyAGududTxCJe5ySMw6lLkpkyE2U09PCOqg",
  authDomain: "gicaform-notifications.firebaseapp.com",
  projectId: "gicaform-notifications",
  storageBucket: "gicaform-notifications.firebasestorage.app",
  messagingSenderId: "714103885883",
  appId: "1:714103885883:web:2f6a575a362d1aa7f50c1e"
};

// Inicializar Firebase
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Manejar mensajes en segundo plano
messaging.onBackgroundMessage((payload) => {
  console.log('[firebase-messaging-sw.js] Mensaje recibido en segundo plano:', payload);
  
  const notificationTitle = payload.notification?.title || 'Nueva notificación';
  const notificationOptions = {
    body: payload.notification?.body || 'Tienes una nueva notificación',
    icon: payload.notification?.icon || '/wp-content/plugins/GICAACCOUNT/assets/images/icon.png',
    badge: '/wp-content/plugins/GICAACCOUNT/assets/images/badge.png',
    image: payload.notification?.image,
    data: payload.data || {},
    tag: 'gica-notification', // Evita duplicados
    requireInteraction: false, // true = no se oculta automáticamente
    actions: [
      {
        action: 'open',
        title: 'Abrir',
        icon: '/wp-content/plugins/GICAACCOUNT/assets/images/icon.png'
      },
      {
        action: 'close',
        title: 'Cerrar'
      }
    ]
  };

  // Mostrar notificación
  self.registration.showNotification(notificationTitle, notificationOptions);
});

// Manejar clics en las notificaciones
self.addEventListener('notificationclick', function(event) {
  console.log('[firebase-messaging-sw.js] Clic en notificación:', event);
  
  event.notification.close();
  
  if (event.action === 'open' || !event.action) {
    // Abrir o enfocar ventana de WordPress
    const urlToOpen = event.notification.data?.url || self.registration.scope;
    
    event.waitUntil(
      clients.matchAll({
        type: 'window',
        includeUncontrolled: true
      }).then(function(clientList) {
        // Si hay una ventana abierta, enfocarla
        for (let i = 0; i < clientList.length; i++) {
          const client = clientList[i];
          if (client.url.includes(self.registration.scope.replace('/wp-content/plugins/GICAACCOUNT/', '')) && 'focus' in client) {
            return client.focus();
          }
        }
        // Si no hay ventana abierta, abrir una nueva
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
    );
  }
  // Si la acción es 'close', no hacer nada (ya se cerró arriba)
});

// Debugging - eliminar en producción
self.addEventListener('install', function(event) {
  console.log('[firebase-messaging-sw.js] Service Worker instalado');
});

self.addEventListener('activate', function(event) {
  console.log('[firebase-messaging-sw.js] Service Worker activado');
});