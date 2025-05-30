import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { useFormatters } from './composables/useFormatters';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Global Pusher configuration
window.pusherConfig = {
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        
        // Register formatters globally
        const formatters = useFormatters();
        app.config.globalProperties.$formatCurrency = formatters.formatCurrency;
        app.config.globalProperties.$formatNumber = formatters.formatNumber;
        app.config.globalProperties.$formatDate = formatters.formatDate;
        app.config.globalProperties.$formatDateTime = formatters.formatDateTime;
        app.config.globalProperties.$formatPercentage = formatters.formatPercentage;
        app.config.globalProperties.$formatRut = formatters.formatRut;
        
        return app
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
