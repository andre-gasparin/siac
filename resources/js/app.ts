import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import AppLayout from '@/app/layouts/AppLayout.vue';
import AuthLayout from '@/features/auth/layouts/AuthLayout.vue';
import SettingsLayout from '@/features/settings/Layout.vue';
import { initializeTheme } from '@/shared/composables/useAppearance';
import { initializeFlashToast } from '@/shared/lib/flashToast';

const reverbKey =
    document.querySelector<HTMLMetaElement>('meta[name="reverb-key"]')
        ?.content || import.meta.env.VITE_REVERB_APP_KEY;

const isSecure = window.location.protocol === 'https:';
const isLocalhost = ['localhost', '127.0.0.1'].includes(
    window.location.hostname,
);

const wsHost = !isLocalhost
    ? window.location.hostname
    : import.meta.env.VITE_REVERB_HOST || window.location.hostname;

const wsPort = !isLocalhost
    ? isSecure
        ? 443
        : 80
    : Number(import.meta.env.VITE_REVERB_PORT || 8080);

configureEcho({
    broadcaster: 'reverb',
    key: reverbKey,
    wsHost: wsHost,
    wsPort: wsPort,
    wssPort: wsPort,
    forceTLS: isSecure,
    enabledTransports: ['ws', 'wss'],
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'Reports/Public':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#1c6295',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
