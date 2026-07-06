import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    // running natively now (no docker env injection), so load .env ourselves
    const env = loadEnv(mode, process.cwd(), '');

    const port = Number(env.VITE_PORT) || 5173;
    const appUrl = env.APP_URL || 'http://findyourinfluencer.local';

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.jsx'],
                refresh: true,
            }),
            react(),
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            origin: `http://localhost:${port}`,
            // the page itself is served by Apache on findyourinfluencer.local, not by vite -
            // without this Vite echoes its own origin back as ACAO and the browser blocks it
            cors: {
                origin: [appUrl, 'http://localhost', 'http://127.0.0.1'],
            },
            hmr: {
                host: 'localhost',
                port,
            },
        },
    };
});
