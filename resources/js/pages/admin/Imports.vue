<script setup lang="ts">
import { Link, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { index, show } from '@/routes/admin/imports';
import type { PageProps } from '@/typefinder';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Imports', href: index() }] },
});

const props = defineProps<PageProps['admin/Imports']>();

// Progress of running imports: poll while any is still scanning.
const running = computed(() =>
    props.imports.some((i) => i.status === 'queued' || i.status === 'running'),
);
const { start, stop } = usePoll(
    3000,
    { only: ['imports'] },
    { autoStart: false },
);
watch(running, (isRunning) => (isRunning ? start() : stop()), {
    immediate: true,
});

const statusLabel: Record<string, string> = {
    queued: 'Waiting',
    running: 'Scanning',
    done: 'Done',
    failed: 'Failed',
};
</script>

<template>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="Imports"
            description="Old quotes found in channels with /quote import. Review them before they appear on the site."
        />

        <ul class="flex flex-col gap-3">
            <li
                v-for="item in imports"
                :key="item.id"
                class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border p-4"
            >
                <div class="min-w-0 flex-1">
                    <p class="font-medium">
                        #{{ item.channel_name ?? item.channel_id }}
                        <span class="text-muted-foreground">
                            · {{ item.guild.name }}</span
                        >
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ item.messages_scanned }} messages scanned ·
                        {{ item.quotes_found }} quotes found
                    </p>
                    <p v-if="item.error" class="text-sm text-destructive">
                        {{ item.error }}
                    </p>
                </div>
                <Badge
                    :variant="
                        item.status === 'failed' ? 'destructive' : 'secondary'
                    "
                >
                    {{ statusLabel[item.status] ?? item.status }}
                </Badge>
                <Link
                    v-if="item.pending_count > 0"
                    :href="show(item.id)"
                    class="text-sm font-medium underline-offset-4 hover:underline"
                >
                    Review {{ item.pending_count }}
                </Link>
                <span v-else class="text-sm text-muted-foreground"
                    >Nothing to review</span
                >
            </li>
        </ul>

        <p
            v-if="imports.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            No imports yet. In Discord, run
            <code class="text-foreground">/quote import channel:#quotes</code>
            to scan an old quotes channel.
        </p>
    </div>
</template>
