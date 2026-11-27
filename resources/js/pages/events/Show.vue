<script setup lang="ts">
import { Link, router, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    CalendarX,
    ExternalLink,
    MapPin,
    Pencil,
    RotateCcw,
} from '@lucide/vue';
import { cancel } from '@/actions/App/Http/Controllers/EventController';
import AttendeeList from '@/components/events/AttendeeList.vue';
import CostsTab from '@/components/events/CostsTab.vue';
import EventDateBadge from '@/components/events/EventDateBadge.vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import FoodTab from '@/components/events/FoodTab.vue';
import RsvpPanel from '@/components/events/RsvpPanel.vue';
import TransportTab from '@/components/events/TransportTab.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useLiveEvent } from '@/composables/useLiveEvent';
import { formatEventWhen } from '@/lib/eventDates';
import { edit, index, show } from '@/routes/events';
import type { PageProps } from '@/typefinder';

const props = defineProps<
    PageProps['events/Show'] & { errors: Record<string, string> }
>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Events', href: index() },
        { title: props.event.title, href: show(props.event.id) },
    ],
});

useLiveEvent(props.event.id, {
    overview: ['event', 'attendees', 'transport'],
    food: ['food'],
    transport: ['transport'],
    costs: ['costs'],
});

const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'food', label: 'Food' },
    { id: 'transport', label: 'Transport' },
    { id: 'costs', label: 'Costs' },
] as const;

type Tab = (typeof tabs)[number]['id'];

const tab = ref<Tab>(
    tabs.find((t) => `#${t.id}` === window.location.hash)?.id ?? 'overview',
);

watch(tab, (value) => {
    history.replaceState(
        history.state,
        '',
        value === 'overview' ? window.location.pathname : `#${value}`,
    );
});

/** The event's first day (YYYY-MM-DD) in its own timezone, the meal form's default. */
const firstDay = computed(() =>
    new Intl.DateTimeFormat('en-CA', { timeZone: props.event.timezone }).format(
        new Date(props.event.starts_at),
    ),
);

function setCancelled(cancelled: boolean): void {
    router.visit(cancel(props.event.id), {
        data: { cancelled },
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <EventDateBadge
                size="lg"
                :starts-at="event.starts_at"
                :timezone="event.timezone"
                :cancelled="event.status === 'cancelled'"
            />
            <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-quote text-3xl font-bold tracking-tight">
                        {{ event.title }}
                    </h1>
                    <Badge
                        v-if="event.status === 'cancelled'"
                        variant="destructive"
                        >Cancelled</Badge
                    >
                </div>
                <p class="text-muted-foreground">
                    {{
                        formatEventWhen(
                            event.starts_at,
                            event.ends_at,
                            event.timezone,
                        )
                    }}
                    <span class="text-xs">({{ event.timezone }})</span>
                </p>
                <p
                    v-if="event.location"
                    class="flex items-center gap-1.5 text-sm"
                >
                    <MapPin class="size-4 text-muted-foreground" />
                    <a
                        v-if="event.location_url"
                        :href="event.location_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 underline underline-offset-4"
                    >
                        {{ event.location }}<ExternalLink class="size-3" />
                    </a>
                    <span v-else>{{ event.location }}</span>
                </p>
                <p
                    class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                >
                    Hosted by
                    <template
                        v-for="(host, i) in [event.created_by, ...event.hosts]"
                        :key="host.id"
                    >
                        <MemberAvatar :member="host" />
                        <span
                            >{{ host.name
                            }}{{ i < event.hosts.length ? ',' : '' }}</span
                        >
                    </template>
                    · {{ event.guild.name }}
                </p>
            </div>
            <div v-if="event.can.edit" class="flex gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="edit(event.id)"
                        ><Pencil class="size-4" />Edit</Link
                    >
                </Button>
                <Dialog v-if="event.status === 'planned'">
                    <DialogTrigger as-child>
                        <Button variant="ghost" size="sm"
                            ><CalendarX class="size-4" />Cancel event</Button
                        >
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Cancel {{ event.title }}?</DialogTitle>
                            <DialogDescription>
                                Nobody can RSVP any more. The event stays here,
                                so costs can still be settled, and you can call
                                it back on.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child
                                ><Button variant="ghost"
                                    >Keep it</Button
                                ></DialogClose
                            >
                            <Button
                                variant="destructive"
                                @click="setCancelled(true)"
                                >Cancel event</Button
                            >
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Button
                    v-else
                    variant="ghost"
                    size="sm"
                    @click="setCancelled(false)"
                >
                    <RotateCcw class="size-4" />It's back on
                </Button>
            </div>
        </header>

        <nav
            class="-mb-2 flex gap-1 border-b"
            role="tablist"
            aria-label="Event sections"
        >
            <button
                v-for="t in tabs"
                :key="t.id"
                type="button"
                role="tab"
                :aria-selected="tab === t.id"
                :class="[
                    '-mb-px border-b-2 px-3 py-2 text-sm transition-colors',
                    tab === t.id
                        ? 'border-foreground font-medium text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
                @click="tab = t.id"
            >
                {{ t.label }}
            </button>
        </nav>

        <FoodTab
            v-if="tab === 'food'"
            :event="event"
            :food="food"
            :me="me"
            :first-day="firstDay"
        />

        <TransportTab
            v-else-if="tab === 'transport'"
            :event="event"
            :transport="transport"
            :me="me"
        />

        <CostsTab
            v-else-if="tab === 'costs'"
            :event="event"
            :costs="costs"
            :me="me"
            :errors="errors"
        />

        <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex flex-col gap-6">
                <p
                    v-if="event.description"
                    class="text-sm leading-relaxed whitespace-pre-line"
                >
                    {{ event.description }}
                </p>
                <AttendeeList :attendees="attendees" />
            </div>
            <aside class="order-first flex flex-col gap-4 lg:order-none">
                <RsvpPanel
                    v-if="event.can.participate"
                    :key="event.my_rsvp?.status ?? 'none'"
                    :event="event"
                />
            </aside>
        </div>
    </div>
</template>
