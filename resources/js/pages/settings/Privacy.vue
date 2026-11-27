<script setup lang="ts">
import { displayLocale, displayTimeZone } from '@/lib/locale';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Settings/PrivacyController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { edit } from '@/routes/privacy';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['settings/Privacy']>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Privacy settings', href: edit() }],
    },
});

const tracking = computed(() => props.opted_out_at === null);
const saving = ref(false);
const confirmOpen = ref(false);

const offSince = computed(() =>
    props.opted_out_at
        ? new Date(props.opted_out_at).toLocaleDateString(displayLocale(), {
              dateStyle: 'long',
              timeZone: displayTimeZone(),
          })
        : null,
);

function setTracking(value: boolean): void {
    router.visit(update(), {
        data: { tracking: value },
        preserveScroll: true,
        onStart: () => (saving.value = true),
        onFinish: () => {
            saving.value = false;
            confirmOpen.value = false;
        },
    });
}

const counted = [
    'How many messages you send, per channel and hour',
    'Which emoji you react with, and on whose messages',
    'Time in voice channels, and who you were there with',
    'Games Discord shows you playing',
];

const neverCounted = [
    'What you write: message text is never stored for stats',
    'DMs, and servers the bot isn’t enabled in',
    'Spotify and custom statuses',
];
</script>

<template>
    <Head title="Privacy settings" />

    <h1 class="sr-only">Privacy settings</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            title="Server stats"
            description="The bot counts activity on your servers for the stats pages. You decide whether that includes you."
        />

        <section
            class="space-y-4 rounded-xl border bg-card p-5"
            data-test="privacy-status"
        >
            <div class="flex items-start gap-3">
                <span
                    class="mt-1.5 size-2.5 shrink-0 rounded-full"
                    :class="
                        tracking ? 'bg-highlight' : 'bg-muted-foreground/40'
                    "
                    aria-hidden="true"
                />
                <div class="space-y-1">
                    <p class="font-medium">
                        {{
                            tracking
                                ? 'Stats are on for you'
                                : 'Stats are off for you'
                        }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="tracking">
                            You show up on leaderboards, the heatmap and your
                            person page.
                        </template>
                        <template v-else>
                            Off since {{ offSince }}. Nothing about you is
                            counted, and what was counted before was deleted.
                        </template>
                    </p>
                </div>
            </div>

            <Dialog v-if="tracking" v-model:open="confirmOpen">
                <DialogTrigger as-child>
                    <Button variant="outline">Turn off stats</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle
                            >Turn off stats and delete yours?</DialogTitle
                        >
                        <DialogDescription>
                            Your messages, reactions, voice time and games stop
                            being counted, and everything counted so far is
                            deleted for good. Your quotes stay.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button variant="ghost">Keep stats on</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="saving"
                            @click="setTracking(false)"
                        >
                            Turn off and delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div v-else class="space-y-2">
                <Button :disabled="saving" @click="setTracking(true)">
                    Turn stats back on
                </Button>
                <p class="text-xs text-muted-foreground">
                    Only activity from now on is counted.
                </p>
            </div>
        </section>

        <div class="grid gap-6 sm:grid-cols-2">
            <section class="space-y-2">
                <h2 class="text-sm font-medium">What is counted</h2>
                <ul class="space-y-1.5 text-sm text-muted-foreground">
                    <li v-for="item in counted" :key="item">{{ item }}</li>
                </ul>
            </section>
            <section class="space-y-2">
                <h2 class="text-sm font-medium">Never counted</h2>
                <ul class="space-y-1.5 text-sm text-muted-foreground">
                    <li v-for="item in neverCounted" :key="item">
                        {{ item }}
                    </li>
                </ul>
            </section>
        </div>

        <p class="text-sm text-muted-foreground">
            In Discord,
            <code class="rounded bg-muted px-1 py-0.5">/privacy</code> does the
            same: <code class="rounded bg-muted px-1 py-0.5">status</code>,
            <code class="rounded bg-muted px-1 py-0.5">optout</code> and
            <code class="rounded bg-muted px-1 py-0.5">optin</code>. Quotes are
            not affected either way.
        </p>
    </div>
</template>
