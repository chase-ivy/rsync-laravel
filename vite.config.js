import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
             input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/auth.css',   // RSYNC sign-in pages
                'resources/js/auth.js',
                'resources/css/home.css',   // RSYNC home page
                'resources/js/home.js',
                'resources/css/rooms.css',  // RSYNC rooms page
                'resources/js/rooms.js',
                'resources/css/room-detail.css', // RSYNC one-room page
                'resources/js/room-detail.js',     // RSYNC sign-in pages
                'resources/css/tenant-dashboard.css', // RSYNC tenant dashboard
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
