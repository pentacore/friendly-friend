<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/Settings/CalendarSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/calendar-settings';
import type { PageProps } from '@/typefinder';

defineProps<PageProps['settings/Calendar']>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Calendar settings', href: edit() }] },
});

const busy = ref(false);
const copied = ref(false);

function send(
    route: ReturnType<typeof store> | ReturnType<typeof destroy>,
): void {
    router.visit(route, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}

async function copy(url: string): Promise<void> {
    await navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function reset(): void {
    if (
        confirm(
            'Make a new link? Calendars subscribed to the old one stop updating.',
        )
    ) {
        send(store());
    }
}
</script>

<template>
    <Head title="Calendar settings" />

    <h1 class="sr-only">Calendar settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Your events in your calendar"
            description="Subscribe to this link in Google Calendar, Apple Calendar or Outlook. It has the events you're going to (or might go to), host or created, and updates by itself when plans change."
        />

        <template v-if="feed_url">
            <div class="grid gap-2">
                <Label for="feed-url">Calendar link</Label>
                <div class="flex gap-2">
                    <Input
                        id="feed-url"
                        :model-value="feed_url"
                        readonly
                        class="font-mono text-xs"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        :aria-label="copied ? 'Copied' : 'Copy link'"
                        @click="copy(feed_url)"
                    >
                        <Check v-if="copied" class="size-4" />
                        <Copy v-else class="size-4" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Anyone with the link can see these events, so keep it to
                    yourself. Calendar apps check for changes every hour or so.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="busy"
                    @click="reset"
                    >Make a new link</Button
                >
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="busy"
                    @click="send(destroy())"
                    >Turn off</Button
                >
            </div>
        </template>

        <Button v-else type="button" :disabled="busy" @click="send(store())"
            >Create my calendar link</Button
        >
    </div>
</template>
