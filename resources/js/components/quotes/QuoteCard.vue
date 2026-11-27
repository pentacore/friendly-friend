<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import QuoteText from '@/components/quotes/QuoteText.vue';
import VoteButtons from '@/components/quotes/VoteButtons.vue';
import { formatSaidAt } from '@/lib/quoteDates';
import { show } from '@/routes/quotes';
import type { QuoteResource } from '@/typefinder';

const props = defineProps<{ quote: QuoteResource; showServer?: boolean }>();

const saidAt = computed(() =>
    formatSaidAt(props.quote.said_at, props.quote.said_at_precision),
);
</script>

<template>
    <article
        class="group rounded-xl border bg-card p-5 text-card-foreground shadow-xs transition-colors hover:border-foreground/20 sm:p-6"
        :data-test="`quote-${quote.id}`"
    >
        <QuoteText :lines="quote.lines" />

        <p
            v-if="quote.context"
            class="mt-3 text-sm text-muted-foreground italic"
        >
            {{ quote.context }}
        </p>

        <footer
            class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-muted-foreground"
        >
            <VoteButtons :quote="quote" />
            <span v-if="showServer" class="font-medium text-foreground/80">
                {{ quote.guild.name }}
            </span>
            <span v-if="saidAt">{{ saidAt }}</span>
            <span>saved by {{ quote.submitted_by.name }}</span>
            <Link
                :href="show(quote.id)"
                class="ml-auto font-medium text-foreground underline-offset-4 hover:underline focus-visible:underline"
            >
                Open<span class="sr-only"> quote {{ quote.id }}</span>
            </Link>
        </footer>
    </article>
</template>
