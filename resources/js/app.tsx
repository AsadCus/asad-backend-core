import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { initializeColorTheme } from './hooks/use-color-theme';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('invalid', (event) => {
    const statusCode = event.detail.response.status;

    if (statusCode !== 401 && statusCode !== 419) {
        return;
    }

    event.preventDefault();

    if (window.location.pathname !== '/login') {
        window.location.href = '/login';
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
initializeColorTheme();
