import '../css/app.css';
import './bootstrap';
// Turbo Drive is already active on every still-Blade page (app.js) so that
// clicking between them feels instant instead of a full reload. Loading it
// here too means the same applies when leaving THIS (Inertia) page toward
// one of those — Turbo intercepts the plain <a> sidebar links and morphs
// into the Blade page instead of a hard navigation. The reverse direction
// (Blade -> this page) stays a real hard navigation via data-turbo="false"
// on the links that point here, since Turbo can't morph into an Inertia
// root document (different script bundle/DOM shape) without breaking React's
// mount — see the note on the Overview sidebar link in layouts/app.blade.php.
import '@hotwired/turbo';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import ErrorBoundary from './Components/ErrorBoundary';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ErrorBoundary>
                <App {...props} />
            </ErrorBoundary>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
