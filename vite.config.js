import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        // Cho phép truy cập từ điện thoại trong cùng mạng LAN khi test dev.
        origin: 'http://192.168.129.63:5173',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
