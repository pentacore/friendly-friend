<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import QuoteCard from '@/components/quotes/QuoteCard.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { provideLiveQuoteScores } from '@/composables/useLiveQuoteScores';
import { show as showPerson } from '@/routes/people';
import { top } from '@/routes';
import { show as thisOrThat } from '@/routes/this-or-that';
import type { PageProps } from '@/typefinder';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Top lists', href: top() }] },
});

const props = defineProps<PageProps['quotes/Top']>();

provideLiveQuoteScores(
    [...props.all_time, ...props.this_month, ...props.elo].map(
        (quote) => quote.guild.id,
    ),
);

const { getInitials } = useInitials();

const quoteLists = [
    {
        key: 'all_time' as const,
        title: 'Funniest of all time',
        empty: 'Vote on quotes to fill this list.',
    },
    {
        key: 'this_month' as const,
        title: 'This month',
        empty: 'Nothing saved this month yet.',
    },
    {
        key: 'elo' as const,
        title: 'This-or-that champions',
        empty: `Quotes appear here after ${props.min_matchups} ${props.min_matchups === 1 ? 'round' : 'rounds'} of this or that.`,
    },
];
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-12 p-4 sm:p-6">
        <Heading
            title="Top lists"
            description="Ranked by votes, and by this-or-that results."
        />

        <section
            v-for="list in quoteLists"
            :key="list.key"
            :aria-labelledby="`top-${list.key}`"
            class="flex flex-col gap-4"
        >
            <div class="flex items-baseline justify-between gap-4">
                <h2 :id="`top-${list.key}`" class="text-lg font-semibold">
                    {{ list.title }}
                </h2>
                <Link
                    v-if="list.key === 'elo'"
                    :href="thisOrThat()"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Play this or that
                </Link>
            </div>
            <ol v-if="props[list.key].length" class="flex flex-col gap-3">
                <li
                    v-for="(quote, rank) in props[list.key]"
                    :key="quote.id"
                    class="grid grid-cols-[2.5rem_1fr] items-start gap-3"
                >
                    <span
                        class="pt-5 text-right font-quote text-2xl font-bold text-muted-foreground tabular-nums"
                        :aria-label="`Rank ${rank + 1}`"
                        >{{ rank + 1 }}</span
                    >
                    <QuoteCard :quote="quote" />
                </li>
            </ol>
            <p v-else class="text-sm text-muted-foreground">{{ list.empty }}</p>
        </section>

        <div class="grid gap-10 md:grid-cols-2">
            <section aria-labelledby="top-people" class="flex flex-col gap-4">
                <h2 id="top-people" class="text-lg font-semibold">
                    Most quoted
                </h2>
                <ol class="flex flex-col divide-y rounded-xl border">
                    <li
                        v-for="(person, rank) in most_quoted"
                        :key="person.slug"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <span
                            class="w-6 text-right text-sm text-muted-foreground tabular-nums"
                            >{{ rank + 1 }}</span
                        >
                        <Link
                            :href="showPerson(person.slug)"
                            class="flex-1 font-medium underline-offset-4 hover:underline"
                        >
                            {{ person.display_name }}
                        </Link>
                        <span
                            class="text-sm text-muted-foreground tabular-nums"
                            >{{ person.quote_count }}</span
                        >
                    </li>
                    <li
                        v-if="most_quoted.length === 0"
                        class="px-4 py-3 text-sm text-muted-foreground"
                    >
                        Nobody yet.
                    </li>
                </ol>
            </section>

            <section aria-labelledby="top-savers" class="flex flex-col gap-4">
                <h2 id="top-savers" class="text-lg font-semibold">
                    Top quote savers
                </h2>
                <ol class="flex flex-col divide-y rounded-xl border">
                    <li
                        v-for="(saver, rank) in top_submitters"
                        :key="saver.name"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <span
                            class="w-6 text-right text-sm text-muted-foreground tabular-nums"
                            >{{ rank + 1 }}</span
                        >
                        <Avatar class="size-7">
                            <AvatarImage
                                :src="saver.avatar_url"
                                :alt="saver.name"
                            />
                            <AvatarFallback>{{
                                getInitials(saver.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <span class="flex-1 font-medium">{{ saver.name }}</span>
                        <span
                            class="text-sm text-muted-foreground tabular-nums"
                            >{{ saver.quote_count }}</span
                        >
                    </li>
                    <li
                        v-if="top_submitters.length === 0"
                        class="px-4 py-3 text-sm text-muted-foreground"
                    >
                        Nobody yet.
                    </li>
                </ol>
            </section>
        </div>
    </div>
</template>
