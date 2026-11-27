<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { CalendarDays, Quote, Search, User } from '@lucide/vue';
import { onKeyStroke, useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { relativeToNow } from '@/lib/eventDates';
import { search as searchRoute } from '@/routes';
import { show as showEvent } from '@/routes/events';
import { show as showPerson } from '@/routes/people';
import { index as quotesIndex, show as showQuote } from '@/routes/quotes';
import type { SearchResultsResource } from '@/typefinder';

/**
 * The header search (Ctrl+K or /): a few quotes, people and events as you
 * type, picked with the arrow keys and Enter, plus a link to every quote
 * that matches.
 */

type Result = {
    key: string;
    kind: 'quote' | 'person' | 'event' | 'all';
    href: string;
    title: string;
    detail: string;
};

const open = ref(false);
const query = ref('');
const results = ref<SearchResultsResource | null>(null);
const active = ref(0);
const http = useHttp<{ q: string }, SearchResultsResource>({ q: '' });

const items = computed<Result[]>(() => {
    const found = results.value;

    if (
        found === null ||
        query.value.trim() === '' ||
        found.quotes.length + found.people.length + found.events.length === 0
    ) {
        return [];
    }

    return [
        ...found.quotes.map((quote) => ({
            key: `quote-${quote.id}`,
            kind: 'quote' as const,
            href: showQuote(quote.id).url,
            title: quote.text,
            detail: quote.speaker,
        })),
        ...found.people.map((person) => ({
            key: `person-${person.slug}`,
            kind: 'person' as const,
            href: showPerson(person.slug).url,
            title: person.display_name,
            detail: 'Person',
        })),
        ...found.events.map((event) => ({
            key: `event-${event.id}`,
            kind: 'event' as const,
            href: showEvent(event.id).url,
            title: event.title,
            detail: relativeToNow(event.starts_at),
        })),
        {
            key: 'all',
            kind: 'all' as const,
            href: quotesIndex({ query: { q: query.value.trim() } }).url,
            title: `All quotes matching “${query.value.trim()}”`,
            detail: '',
        },
    ];
});

const icons = { quote: Quote, person: User, event: CalendarDays, all: Search };

const searchSoon = useDebounceFn(async () => {
    const text = query.value.trim();

    if (text === '') {
        results.value = null;

        return;
    }

    http.cancel();
    http.q = text;

    try {
        results.value = await http.submit(searchRoute());
        active.value = 0;
    } catch {
        // A cancelled or failed search keeps the last results.
    }
}, 200);

watch(query, () => searchSoon());

watch(open, (isOpen) => {
    if (!isOpen) {
        query.value = '';
        results.value = null;
    }
});

/** Ctrl+K / Cmd+K anywhere, or / when not typing in a field. */
onKeyStroke(['k', 'K', '/'], (event) => {
    const target = event.target as HTMLElement | null;
    const typing =
        target !== null &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
    const shortcut =
        event.key === '/'
            ? !typing && !event.ctrlKey && !event.metaKey
            : event.ctrlKey || event.metaKey;

    if (shortcut) {
        event.preventDefault();
        open.value = true;
    }
});

function move(step: number): void {
    const count = items.value.length;

    if (count > 0) {
        active.value = (active.value + step + count) % count;
    }
}

function go(item: Result | undefined): void {
    if (item !== undefined) {
        open.value = false;
        router.visit(item.href);
    }
}
</script>

<template>
    <div class="ml-auto">
        <button
            type="button"
            class="inline-flex h-9 items-center gap-2 rounded-md border border-input bg-background px-2.5 text-sm text-muted-foreground shadow-xs transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:w-56"
            aria-label="Search"
            @click="open = true"
        >
            <Search class="size-4" />
            <span class="hidden sm:inline">Search…</span>
            <kbd
                class="ml-auto hidden rounded border bg-muted px-1.5 font-sans text-[0.7rem] sm:inline"
                >Ctrl K</kbd
            >
        </button>

        <Dialog v-model:open="open">
            <DialogContent
                class="top-[15%] translate-y-0 gap-0 overflow-hidden p-0"
                :show-close-button="false"
            >
                <DialogTitle class="sr-only">Search</DialogTitle>
                <DialogDescription class="sr-only">
                    Quotes, people and events. Use the arrow keys and Enter to
                    open one.
                </DialogDescription>

                <div class="flex items-center gap-2 border-b px-3">
                    <Search class="size-4 shrink-0 text-muted-foreground" />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Search quotes, people, events…"
                        aria-label="Search quotes, people and events"
                        role="combobox"
                        aria-controls="global-search-results"
                        :aria-expanded="items.length > 0"
                        :aria-activedescendant="
                            items[active]
                                ? `global-search-${items[active].key}`
                                : undefined
                        "
                        class="h-12 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="go(items[active])"
                    />
                </div>

                <ul
                    v-if="items.length > 0"
                    id="global-search-results"
                    role="listbox"
                    aria-label="Search results"
                    class="max-h-[60vh] overflow-y-auto p-1.5"
                >
                    <li
                        v-for="(item, i) in items"
                        :id="`global-search-${item.key}`"
                        :key="item.key"
                        role="option"
                        :aria-selected="i === active"
                        class="flex cursor-pointer items-start gap-3 rounded-md px-2.5 py-2 text-sm"
                        :class="
                            i === active
                                ? 'bg-accent text-accent-foreground'
                                : ''
                        "
                        @mouseenter="active = i"
                        @click="go(item)"
                    >
                        <component
                            :is="icons[item.kind]"
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate"
                                :class="
                                    item.kind === 'quote' ? 'font-quote' : ''
                                "
                                >{{ item.title }}</span
                            >
                            <span
                                v-if="item.detail"
                                class="block truncate text-xs text-muted-foreground"
                                >{{ item.detail }}</span
                            >
                        </span>
                    </li>
                </ul>

                <p
                    v-if="results?.close_matches && results.quotes.length > 0"
                    class="border-t px-4 py-2 text-xs text-muted-foreground"
                >
                    No exact quote matches; these are close.
                </p>
                <p
                    v-else-if="
                        results !== null &&
                        items.length === 0 &&
                        query.trim() !== ''
                    "
                    class="px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    Nothing matches “{{ query.trim() }}”. Try a name or a single
                    word.
                </p>
                <p
                    v-else-if="query.trim() === ''"
                    class="px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    Type a word, a name or an event.
                </p>
            </DialogContent>
        </Dialog>
    </div>
</template>
