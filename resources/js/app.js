import './bootstrap';
import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Import Toast Notification System
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';

// We've removed AdminLTE JavaScript completely
// Instead, we're implementing the necessary functionality directly in Vue

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Configure the toast notification system
const toastOptions = {
    position: "top-right",
    timeout: 5000,
    closeOnClick: true,
    pauseOnFocusLoss: true,
    pauseOnHover: true,
    draggable: true,
    draggablePercent: 0.6,
    showCloseButtonOnHover: false,
    hideProgressBar: false,
    closeButton: "button",
    icon: true,
    rtl: false,
    transition: "Vue-Toastification__bounce",
    maxToasts: 20,
    newestOnTop: true
};

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Toast, toastOptions)
            .mount(el);
    },
    progress: {
        // Use AdminLTE primary blue color for consistency
        color: '#007bff',
        showSpinner: true,
        delay: 0, // Show immediately
        includeCSS: true,
        // Ensure higher z-index to appear above AdminLTE elements
        zIndex: 9999
    }
});
