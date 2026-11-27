<script setup lang="ts">
import { InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import EventCard from '@/components/events/EventCard.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index } from '@/routes/events';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['events/Index']>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Events', href: index() }] },
});

const guild = ref<number | null>(props.filters.guild);
const search = ref(props.filters.q);

function filter(): void {
    router.visit(
        index({
            query: {
                q: search.value || undefined,
                guild: guild.value ?? undefined,
            },
        }),
        {
            preserveState: true,
            replace: true,
            // `reset` alone would reload only `past`.
            only: ['upcoming', 'past', 'filters'],
            reset: ['past'],
        },
    );
}

const searchSoon = useDebounceFn(filter, 300);
</script>

<template>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Events"
                description="Who's coming, who brings what, who drives."
            />
            <div class="flex items-center gap-2">
                <select
                    v-if="guilds.length > 1"
                    v-model="guild"
                    aria-label="Server"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    @change="filter"
                >
                    <option :value="null">All servers</option>
                    <option v-for="g in guilds" :key="g.id" :value="g.id">
                        {{ g.name }}
                    </option>
                </select>
                <Button as-child>
                    <Link :href="create()"
                        ><Plus class="size-4" />New event</Link
                    >
                </Button>
            </div>
        </div>

        <Input
            v-model="search"
            type="search"
            placeholder="Search by name or place…"
            aria-label="Search events"
            @input="searchSoon"
        />

        <section class="grid gap-3 lg:grid-cols-2">
            <h2 class="text-sm font-medium text-muted-foreground lg:col-span-2">
                Coming up
            </h2>
            <EventCard
                v-for="event in upcoming"
                :key="event.id"
                :event="event"
                :show-server="guilds.length > 1"
            />
            <p
                v-if="upcoming.length === 0 && filters.q"
                class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground lg:col-span-2"
            >
                Nothing coming up matches “{{ filters.q }}”.
            </p>
            <p
                v-else-if="upcoming.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground lg:col-span-2"
            >
                Nothing planned yet. Start something:
                <Link
                    :href="create()"
                    class="underline underline-offset-4 hover:text-foreground"
                    >new event</Link
                >.
            </p>
        </section>

        <section v-if="past.data.length" class="flex flex-col gap-3">
            <h2 class="text-sm font-medium text-muted-foreground">
                Been and gone
            </h2>
            <InfiniteScroll data="past" class="grid gap-3 lg:grid-cols-2">
                <EventCard
                    v-for="event in past.data"
                    :key="event.id"
                    :event="event"
                    :show-server="guilds.length > 1"
                />

                <template #loading>
                    <div
                        class="h-24 animate-pulse rounded-xl border bg-muted/40"
                        aria-label="Loading more events"
                    />
                </template>
            </InfiniteScroll>
        </section>
    </div>
</template>
