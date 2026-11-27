import type { RequestPayload, UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Sends a small write on the event page and reloads only the given props
 * (the server redirects back). `busy` is true while a request runs.
 */
export function useEventAction(only: string[]) {
    const busy = ref(false);

    function send(
        route: UrlMethodPair,
        data: RequestPayload = {},
        onSuccess?: () => void,
    ): void {
        router.visit(route, {
            data,
            only,
            preserveScroll: true,
            preserveState: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
            onSuccess: () => onSuccess?.(),
        });
    }

    return { busy, send };
}
