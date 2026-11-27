<script setup lang="ts">
import { Link, setLayoutProps } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import QuoteText from '@/components/quotes/QuoteText.vue';
import { formatSaidAt } from '@/lib/quoteDates';
import { index, show } from '@/routes/quotes';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['quotes/Show']>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Quotes', href: index() },
        { title: `#${props.quote.id}`, href: show(props.quote.id) },
    ],
});

const saidAt = computed(() =>
    formatSaidAt(props.quote.said_at, props.quote.said_at_precision),
);

const editedAt = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-10 p-4 sm:p-10">
        <section class="flex flex-col gap-6" :data-test="`quote-${quote.id}`">
            <QuoteText :lines="quote.lines" size="lg" />

            <p v-if="quote.context" class="text-muted-foreground italic">
                {{ quote.context }}
            </p>

            <dl
                class="flex flex-wrap gap-x-6 gap-y-2 border-t pt-4 text-sm text-muted-foreground"
            >
                <div v-if="saidAt">
                    <dt class="sr-only">Said</dt>
                    <dd>{{ saidAt }}</dd>
                </div>
                <div>
                    <dt class="sr-only">Server</dt>
                    <dd>{{ quote.guild.name }}</dd>
                </div>
                <div>
                    <dt class="sr-only">Saved by</dt>
                    <dd>saved by {{ quote.submitted_by.name }}</dd>
                </div>
                <div v-if="quote.source_message_url">
                    <dt class="sr-only">Source</dt>
                    <dd>
                        <a
                            :href="quote.source_message_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 underline-offset-4 hover:text-foreground hover:underline"
                        >
                            Original message
                            <ExternalLink class="size-3.5" />
                        </a>
                    </dd>
                </div>
            </dl>
        </section>

        <section v-if="revisions.length > 1" aria-labelledby="history">
            <h2 id="history" class="mb-3 text-sm font-semibold">
                Edit history
            </h2>
            <ol class="flex flex-col gap-3 text-sm">
                <li
                    v-for="revision in revisions"
                    :key="revision.id"
                    class="rounded-lg border p-3"
                >
                    <p class="mb-1 text-xs text-muted-foreground">
                        {{ revision.editor_name }} ·
                        {{
                            revision.created_at
                                ? editedAt.format(new Date(revision.created_at))
                                : ''
                        }}
                    </p>
                    <p v-for="(line, i) in revision.lines" :key="i">
                        <span class="font-medium">{{ line.speaker }}:</span>
                        {{ line.text }}
                    </p>
                </li>
            </ol>
        </section>

        <Link
            :href="index()"
            class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
            ← All quotes
        </Link>
    </div>
</template>
