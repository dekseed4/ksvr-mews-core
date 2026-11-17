import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// 🌟 ดึง URL หลักของเว็บไซต์ (Base URL) มาจากแท็ก Meta
const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    
    // 🌟 ใช้ baseUrl มาต่อกับ path ทำให้เป๊ะ 100% ไม่ว่าจะอยู่ sub-folder ไหน!
    authEndpoint: baseUrl + '/broadcasting/auth',
});