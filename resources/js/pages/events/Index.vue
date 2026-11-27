<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import EventCard from '@/components/events/EventCard.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/events';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['events/Index']>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Events', href: index() }] },
});

const guild = ref<number | null>(props.filters.guild);

function filter(): void {
    router.visit(index({ query: { guild: guild.value ?? undefined } }), {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6">
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

        <section class="flex flex-col gap-3">
            <h2 class="text-sm font-medium text-muted-foreground">Coming up</h2>
            <EventCard
                v-for="event in upcoming"
                :key="event.id"
                :event="event"
                :show-server="guilds.length > 1"
            />
            <p
                v-if="upcoming.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Nothing planned yet. Start something:
                <Link
                    :href="create()"
                    class="underline underline-offset-4 hover:text-foreground"
                    >new event</Link
                >.
            </p>
        </section>

        <section v-if="past.length" class="flex flex-col gap-3">
            <h2 class="text-sm font-medium text-muted-foreground">
                Been and gone
            </h2>
            <EventCard
                v-for="event in past"
                :key="event.id"
                :event="event"
                :show-server="guilds.length > 1"
            />
        </section>
    </div>
</template>
