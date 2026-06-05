import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { initializeColorTheme } from './hooks/use-color-theme';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('httpException', (event) => {
    const statusCode = event.detail.response.status;

    if (statusCode !== 401 && statusCode !== 419) {
        return;
    }

    event.preventDefault();

    if (window.location.pathname !== '/login') {
        window.location.href = '/login';
    }
});
const el = document.getElementById('app');

let page = null;
if (el && el.dataset.page) {
    page = JSON.parse(el.dataset.page);
}

createInertiaApp({
    ...(page ? { page } : {}),
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name): Promise<ComponentType> => {
        const module = await resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        );

        return (
            (module as { default?: ComponentType }).default ??
            (module as ComponentType)
        );
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
initializeColorTheme();
