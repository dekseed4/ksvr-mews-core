self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// 🚀 ดักจับสัญญาณ Web Push จากเซิร์ฟเวอร์ KSVR แม้ปิดเว็บหรือล็อกหน้าจอมือถืออยู่
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload = {};
    try {
        payload = event.data.json();
    } catch (e) {
        payload = {
            title: '🔔 แจ้งเตือนจาก KSVR MEWS',
            body: event.data.text(),
            url: '/backend/dashboard'
        };
    }

    const title = payload.title || '🔔 แจ้งเตือนจาก KSVR MEWS';
    const options = {
        body: payload.body || 'มีข้อมูลอัปเดตใหม่ในระบบ กรุณาแตะเพื่อตรวจสอบ',
        icon: payload.icon || '/images/logo5.png',
        badge: payload.badge || '/images/logo5.png',
        tag: payload.tag || ('ksvr-push-' + Date.now()),
        renotify: true,
        requireInteraction: !!payload.requireInteraction,
        vibrate: [200, 100, 200, 100, 300],
        data: {
            url: payload.url || '/backend/dashboard'
        }
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// 👆 เมื่อผู้ใช้แตะที่แบนเนอร์แจ้งเตือนบนจอมือถือหรือคอมพิวเตอร์ ให้เปิดหน้าเว็บเป้าหมายทันที
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/backend/dashboard';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});