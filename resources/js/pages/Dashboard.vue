<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, ChartColumn, Scale, Shuffle, Trophy } from '@lucide/vue';
import QuoteCard from '@/components/quotes/QuoteCard.vue';
import QuoteText from '@/components/quotes/QuoteText.vue';
import VoteButtons from '@/components/quotes/VoteButtons.vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { provideLiveQuoteScores } from '@/composables/useLiveQuoteScores';
import { formatDuration, plural } from '@/lib/statsFormat';
import { dashboard, stats, top } from '@/routes';
import { index, show } from '@/routes/quotes';
import { show as thisOrThat } from '@/routes/this-or-that';
import type { PageProps } from '@/typefinder';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

const props = defineProps<PageProps['Dashboard']>();

provideLiveQuoteScores(
    [props.random, ...props.recent]
        .filter((quote) => quote !== null)
        .map((quote) => quote.guild.id),
);

function anotherOne(): void {
    router.reload({ only: ['random'] });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-12 p-4 sm:p-10">
        <section
            v-if="random"
            aria-label="Random quote"
            class="flex flex-col gap-6 py-6 sm:py-12"
        >
            <QuoteText :key="random.id" :lines="random.lines" size="lg" />
            <p v-if="random.context" class="text-muted-foreground italic">
                {{ random.context }}
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <VoteButtons :key="random.id" :quote="random" />
                <Button variant="outline" size="sm" @click="anotherOne">
                    <Shuffle class="size-4" />
                    Another one
                </Button>
                <Link
                    :href="show(random.id)"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Open quote
                </Link>
            </div>
        </section>

        <section
            v-else
            class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            <p class="font-quote text-2xl text-foreground">
                Nothing quotable yet.
            </p>
            <p class="mt-2 text-sm">
                In Discord, right-click a message and choose
                <strong class="text-foreground">Apps → Save quote</strong>, or
                use <code class="text-foreground">/quote add</code>.
            </p>
        </section>

        <nav
            v-if="total > 1"
            aria-label="Play"
            class="grid gap-3 sm:grid-cols-2"
        >
            <Link
                :href="thisOrThat()"
                class="group flex items-center gap-3 rounded-xl border p-4 transition-colors hover:border-foreground/30"
            >
                <Scale class="size-5 text-muted-foreground" />
                <span class="flex-1">
                    <span class="block font-medium">This or that</span>
                    <span class="text-sm text-muted-foreground"
                        >Pick the funnier of two quotes.</span
                    >
                </span>
                <ArrowRight
                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                />
            </Link>
            <Link
                :href="top()"
                class="group flex items-center gap-3 rounded-xl border p-4 transition-colors hover:border-foreground/30"
            >
                <Trophy class="size-5 text-muted-foreground" />
                <span class="flex-1">
                    <span class="block font-medium">Top lists</span>
                    <span class="text-sm text-muted-foreground"
                        >The funniest, and who says the most.</span
                    >
                </span>
                <ArrowRight
                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                />
            </Link>
        </nav>

        <Link
            v-if="week"
            :href="stats({ query: { days: 7 } })"
            class="group flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border p-4 transition-colors hover:border-foreground/30"
            data-test="week-card"
        >
            <ChartColumn class="size-5 text-muted-foreground" />
            <span class="min-w-0 flex-1">
                <span class="block font-medium">This week</span>
                <span class="text-sm text-muted-foreground">
                    {{ plural(week.messages, 'message', 'messages') }},
                    {{ formatDuration(week.voice_seconds) }} in voice, from
                    {{ plural(week.members, 'person', 'people') }}.
                </span>
            </span>
            <span v-if="week.top" class="flex items-center gap-2 text-sm">
                <MemberAvatar :member="week.top" />
                <span>
                    <span class="text-muted-foreground">Chattiest:</span>
                    {{ week.top.name }}
                </span>
            </span>
            <ArrowRight
                class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
            />
        </Link>

        <section
            v-if="recent.length"
            aria-labelledby="latest"
            class="flex flex-col gap-4"
        >
            <div class="flex items-baseline justify-between">
                <h2 id="latest" class="text-lg font-semibold">Latest quotes</h2>
                <Link
                    :href="index()"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    All {{ total }} quotes
                </Link>
            </div>
            <QuoteCard v-for="quote in recent" :key="quote.id" :quote="quote" />
        </section>
    </div>
</template>
