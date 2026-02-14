import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const devServerHost = process.env.VITE_DEV_HOST ?? '0.0.0.0';
const hmrHost = process.env.VITE_HMR_HOST ?? 'localhost';
const devServerPort = Number(process.env.VITE_PORT ?? 5173);
const appOrigin = process.env.APP_URL ?? 'http://localhost:8000';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: devServerHost,
        port: devServerPort,
        strictPort: true,
        origin: `http://${hmrHost}:${devServerPort}`,
        cors: {
            origin: [
                appOrigin,
                'http://localhost:8000',
                'http://127.0.0.1:8000',
            ],
        },
        hmr: {
            host: hmrHost,
            port: devServerPort,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
