<script setup lang="ts">
import { InfiniteScroll, setLayoutProps } from '@inertiajs/vue3';
import QuoteCard from '@/components/quotes/QuoteCard.vue';
import { provideLiveQuoteScores } from '@/composables/useLiveQuoteScores';
import ActivityHeatmap from '@/components/stats/ActivityHeatmap.vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import MemberName from '@/components/stats/MemberName.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { formatDuration, plural } from '@/lib/statsFormat';
import { show } from '@/routes/people';
import { index } from '@/routes/quotes';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['people/Show']>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Quotes', href: index() },
        { title: props.person.display_name, href: show(props.person.slug) },
    ],
});

provideLiveQuoteScores(props.quotes.data.map((quote) => quote.guild.id));

const { getInitials } = useInitials();

/** The other person of a "time together" pair. */
function buddy(pair: NonNullable<typeof props.stats>['voice_pairs'][number]) {
    return pair.first.person_slug === props.person.slug
        ? pair.second
        : pair.first;
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 sm:p-6">
        <header class="flex items-center gap-4">
            <Avatar class="size-16 rounded-2xl">
                <AvatarImage
                    v-if="person.avatar_url"
                    :src="person.avatar_url"
                    :alt="person.display_name"
                />
                <AvatarFallback class="rounded-2xl text-lg">
                    {{ getInitials(person.display_name) }}
                </AvatarFallback>
            </Avatar>
            <div>
                <h1 class="font-quote text-3xl font-bold tracking-tight">
                    {{ person.display_name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ person.quote_count }}
                    {{ person.quote_count === 1 ? 'quote' : 'quotes' }}
                    <template v-if="person.aliases.length">
                        · also known as {{ person.aliases.join(', ') }}
                    </template>
                </p>
            </div>
        </header>

        <section
            v-if="
                stats &&
                (stats.totals.messages ||
                    stats.totals.voice_seconds ||
                    stats.totals.reactions)
            "
            class="flex flex-col gap-4 rounded-xl border bg-card p-4"
            data-test="person-stats"
        >
            <p class="text-sm">
                Last {{ stats.days }} days:
                <strong class="font-semibold">{{
                    plural(stats.totals.messages, 'message', 'messages')
                }}</strong
                >,
                <strong class="font-semibold">{{
                    formatDuration(stats.totals.voice_seconds)
                }}</strong>
                in voice,
                <strong class="font-semibold">{{
                    plural(stats.totals.reactions, 'reaction', 'reactions')
                }}</strong>
                given.
            </p>

            <ActivityHeatmap :cells="stats.heatmap" />

            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                <div v-if="stats.voice_pairs.length" class="space-y-1.5">
                    <dt class="text-xs text-muted-foreground">
                        Most time together with
                    </dt>
                    <dd
                        v-for="pair in stats.voice_pairs.slice(0, 3)"
                        :key="buddy(pair).id"
                        class="flex items-center gap-2"
                    >
                        <MemberAvatar :member="buddy(pair)" />
                        <MemberName :member="buddy(pair)" />
                        <span
                            class="ml-auto text-muted-foreground tabular-nums"
                        >
                            {{ formatDuration(pair.total) }}
                        </span>
                    </dd>
                </div>
                <div v-if="stats.games.length" class="space-y-1.5">
                    <dt class="text-xs text-muted-foreground">Plays</dt>
                    <dd
                        v-for="game in stats.games.slice(0, 3)"
                        :key="game.name"
                        class="flex gap-2"
                    >
                        <span class="truncate">{{ game.name }}</span>
                        <span
                            class="ml-auto text-muted-foreground tabular-nums"
                        >
                            {{ formatDuration(game.total) }}
                        </span>
                    </dd>
                </div>
                <div v-if="stats.emoji.length" class="space-y-1.5">
                    <dt class="text-xs text-muted-foreground">Reacts with</dt>
                    <dd class="flex flex-wrap items-center gap-2 text-xl">
                        <template v-for="e in stats.emoji" :key="e.emoji">
                            <img
                                v-if="e.image_url"
                                :src="e.image_url"
                                :alt="e.name"
                                :title="`:${e.name}: × ${e.total}`"
                                class="size-6 object-contain"
                            />
                            <span v-else :title="`× ${e.total}`">{{
                                e.name
                            }}</span>
                        </template>
                    </dd>
                </div>
            </dl>
        </section>

        <InfiniteScroll
            v-if="quotes.data.length"
            data="quotes"
            class="flex flex-col gap-4"
        >
            <QuoteCard
                v-for="quote in quotes.data"
                :key="quote.id"
                :quote="quote"
            />
        </InfiniteScroll>

        <p
            v-if="quotes.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            No quotes from {{ person.display_name }} yet. Give it time.
        </p>
    </div>
</template>
