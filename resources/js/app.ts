import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { recordHydrationMismatches } from '@/lib/hydrationCheck';
import { configureEcho } from '@laravel/echo-vue';

type ReverbConfig = {
    key: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
};

/**
 * Where browsers reach Reverb, from `<meta name="reverb-config">` (rendered by
 * Laravel at runtime, so one built image works for every deploy). Empty on the
 * server: live updates are browser-only.
 */
function reverbConnection(): Record<string, unknown> {
    const meta =
        typeof document === 'undefined'
            ? null
            : document.querySelector<HTMLMetaElement>(
                  'meta[name="reverb-config"]',
              );

    if (!meta?.content) {
        return {};
    }

    const config = JSON.parse(meta.content) as ReverbConfig;

    return {
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    };
}

configureEcho({
    broadcaster: 'reverb',
    ...reverbConnection(),
});

recordHydrationMismatches();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
