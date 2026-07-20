import './bootstrap';
import '../css/app.css';
import '../css/sticker-print.css';

import { createApp, h, Fragment } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PwaInstallPrompt from './Components/PwaInstallPrompt.vue';
import { initPwa } from './Composables/usePwaInstall';

const appName = import.meta.env.VITE_APP_NAME || 'نظام الكاشير';

function refreshCsrfFromPage(page) {
    const token = page?.props?.csrf_token;
    if (token && typeof window.syncCsrfToken === 'function') {
        window.syncCsrfToken(token);
    }
}

router.on('invalid', (event) => {
    if (event.detail.response?.status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

initPwa();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        refreshCsrfFromPage(props.initialPage);

        return createApp({
            render: () => h(Fragment, null, [
                h(App, props),
                h(PwaInstallPrompt),
            ]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#15803d',
    },
});

document.addEventListener('inertia:success', (event) => {
    refreshCsrfFromPage(event.detail.page);
});
