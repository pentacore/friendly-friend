<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { redirect } from '@/routes/auth/discord';
import type { AccessDeniedReason, PageProps } from '@/typefinder';

defineOptions({
    layout: {
        title: 'Friendly Friend',
        description: 'Log in with the Discord account you use on the server',
    },
});

const props = defineProps<PageProps['auth/Login']>();

const messages: Record<AccessDeniedReason, string> = {
    'not-member':
        "That Discord account isn't a member of the server. Join the server first, then try again.",
    'missing-role':
        "You're on the server but don't have a role that's allowed in yet. Ask an admin.",
    'access-revoked':
        'Your access was removed because you left the server or lost your role.',
    'oauth-failed': 'Discord login was cancelled or failed. Please try again.',
};

const errorMessage = computed(() =>
    props.error ? messages[props.error] : null,
);
</script>

<template>
    <Head title="Log in" />

    <div class="flex flex-col gap-6">
        <div
            v-if="errorMessage"
            role="alert"
            class="rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-center text-sm text-destructive"
            data-test="login-error"
        >
            {{ errorMessage }}
        </div>

        <Button
            as-child
            class="w-full bg-[#5865F2] text-white hover:bg-[#4752C4]"
        >
            <a :href="redirect().url" data-test="discord-login-button">
                <svg
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    class="size-5 fill-current"
                >
                    <path
                        d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"
                    />
                </svg>
                Log in with Discord
            </a>
        </Button>
    </div>
</template>
