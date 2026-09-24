import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from '@/app/layouts/AppLayout.vue';
import AuthLayout from '@/features/auth/layouts/AuthLayout.vue';
import SettingsLayout from '@/features/settings/Layout.vue';
import { initializeTheme } from '@/shared/composables/useAppearance';
import { initializeFlashToast } from '@/shared/lib/flashToast';

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
