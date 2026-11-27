<script setup lang="ts">
import { InfiniteScroll, setLayoutProps } from '@inertiajs/vue3';
import QuoteCard from '@/components/quotes/QuoteCard.vue';
import { provideLiveQuoteScores } from '@/composables/useLiveQuoteScores';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
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

        <InfiniteScroll data="quotes" class="flex flex-col gap-4">
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
