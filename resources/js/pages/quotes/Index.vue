<script setup lang="ts">
import { InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { Shuffle, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import QuoteCard from '@/components/quotes/QuoteCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { provideLiveQuoteScores } from '@/composables/useLiveQuoteScores';
import { searchWords } from '@/lib/searchHighlight';
import { index, random } from '@/routes/quotes';
import type { PageProps } from '@/typefinder';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Quotes', href: index() }],
    },
});

const props = defineProps<PageProps['quotes/Index']>();

provideLiveQuoteScores(props.guilds.map((g) => g.id));

const search = ref(props.filters.q);
const guild = ref<number | null>(props.filters.guild);
const personSlug = ref<string | null>(props.filters.person);
const sort = ref(props.filters.sort);
const highlight = computed(() => searchWords(props.filters.q));

function applyFilters(): void {
    router.visit(
        index({
            query: {
                q: search.value || undefined,
                guild: guild.value ?? undefined,
                person: personSlug.value ?? undefined,
                // The default order isn't put in the URL.
                sort:
                    sort.value === (search.value ? 'best' : 'newest')
                        ? undefined
                        : sort.value,
            },
        }),
        {
            preserveState: true,
            replace: true,
            // `reset` alone turns the visit into a partial reload of just
            // `quotes`, which would leave the filters (and highlights) stale.
            only: ['quotes', 'filters', 'person', 'people', 'close_matches'],
            reset: ['quotes'],
        },
    );
}

const searchSoon = useDebounceFn(() => {
    // A new search starts at best match; clearing it goes back to newest.
    if (sort.value !== 'top') {
        sort.value = search.value ? 'best' : 'newest';
    }

    applyFilters();
}, 300);

const field =
    'h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 xl:max-w-6xl"
    >
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                :title="person ? `Quotes by ${person.display_name}` : 'Quotes'"
                description="Everything your friends shouldn't have said."
            />
            <Button variant="outline" as-child>
                <Link :href="random()">
                    <Shuffle class="size-4" />
                    Random quote
                </Link>
            </Button>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <Input
                v-model="search"
                type="search"
                placeholder="Search quotes, people, context…"
                aria-label="Search quotes"
                class="sm:flex-1"
                @input="searchSoon"
            />
            <select
                v-if="guilds.length > 1"
                v-model="guild"
                aria-label="Server"
                :class="field"
                @change="applyFilters"
            >
                <option :value="null">All servers</option>
                <option v-for="g in guilds" :key="g.id" :value="g.id">
                    {{ g.name }}
                </option>
            </select>
            <select
                v-if="people.length > 1"
                v-model="personSlug"
                aria-label="Person"
                :class="[field, 'sm:max-w-48']"
                @change="applyFilters"
            >
                <option :value="null">Everyone</option>
                <option v-for="p in people" :key="p.slug" :value="p.slug">
                    {{ p.display_name }}
                </option>
            </select>
            <select
                v-model="sort"
                aria-label="Sort"
                :class="field"
                @change="applyFilters"
            >
                <option v-if="filters.q" value="best">Best match</option>
                <option value="newest">Newest</option>
                <option value="top">Top score</option>
            </select>
        </div>

        <p v-if="person" class="-mt-2 text-sm text-muted-foreground">
            <Link
                :href="index({ query: { q: filters.q || undefined } })"
                class="inline-flex items-center gap-1 underline-offset-4 hover:text-foreground hover:underline"
            >
                <X class="size-3.5" />
                Show everyone's quotes
            </Link>
        </p>

        <p
            v-if="close_matches && quotes.data.length > 0"
            class="-mt-2 text-sm text-muted-foreground"
        >
            Nothing matches “{{ filters.q }}” exactly. These are close.
        </p>

        <InfiniteScroll
            data="quotes"
            class="grid items-start gap-4 xl:grid-cols-2"
        >
            <QuoteCard
                v-for="quote in quotes.data"
                :key="quote.id"
                :quote="quote"
                :show-server="guilds.length > 1"
                :highlight="highlight"
            />

            <template #loading>
                <div
                    class="h-32 animate-pulse rounded-xl border bg-muted/40"
                    aria-label="Loading more quotes"
                />
            </template>
        </InfiniteScroll>

        <div
            v-if="quotes.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            <template v-if="filters.q">
                Nothing matches “{{ filters.q }}”. Try a name or a single word.
            </template>
            <template v-else>
                No quotes yet. In Discord, right-click a message and choose
                <strong class="text-foreground">Apps → Save quote</strong>, or
                use <code class="text-foreground">/quote add</code>.
            </template>
        </div>
    </div>
</template>
