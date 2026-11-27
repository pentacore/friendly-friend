<script setup lang="ts">
import { displayLocale } from '@/lib/locale';
import { Car, MapPin, Pencil, Trash2, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import {
    destroy,
    join,
    leave,
    store,
    update,
} from '@/actions/App/Http/Controllers/EventRideController';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEventAction } from '@/composables/useEventAction';
import { localInputIn } from '@/lib/eventDates';
import type { EventResource, PageProps, RideDirection } from '@/typefinder';

type Transport = PageProps['events/Show']['transport'];
type Ride = Transport['rides'][number];

const props = defineProps<{
    event: EventResource;
    transport: Transport;
    me: number;
}>();

const { busy, send } = useEventAction(['transport']);

const directions: Record<RideDirection, string> = {
    to: 'There',
    from: 'Back',
    both: 'There and back',
};

const groups = computed(() =>
    (Object.keys(directions) as RideDirection[])
        .map((direction) => ({
            direction,
            rides: props.transport.rides.filter(
                (r) => r.direction === direction,
            ),
        }))
        .filter((g) => g.rides.length > 0),
);

function seated(ride: Ride): boolean {
    return ride.passengers.some((p) => p.member.id === props.me);
}

function departs(ride: Ride): string | null {
    return ride.departs_at
        ? new Intl.DateTimeFormat(displayLocale(), {
              timeZone: props.event.timezone,
              weekday: 'short',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(ride.departs_at))
        : null;
}

const pickup = ref<Record<number, string>>({});

const offer = ref({
    direction: 'both' as RideDirection,
    seats: 3,
    departs_from: '',
    departs_at: '',
    note: '',
});
const offering = ref(false);
/** The ride being edited, or null when offering a new one. */
const editingId = ref<number | null>(null);
const offerForm = ref<HTMLElement | null>(null);

function blankOffer() {
    return {
        direction: 'both' as RideDirection,
        seats: 3,
        departs_from: '',
        departs_at: '',
        note: '',
    };
}

function startEdit(ride: Ride): void {
    editingId.value = ride.id;
    offer.value = {
        direction: ride.direction,
        seats: ride.seats,
        departs_from: ride.departs_from ?? '',
        departs_at: ride.departs_at
            ? localInputIn(ride.departs_at, props.event.timezone)
            : '',
        note: ride.note ?? '',
    };
    offering.value = true;
    nextTick(() =>
        offerForm.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        }),
    );
}

function closeOffer(): void {
    offering.value = false;
    editingId.value = null;
    offer.value = blankOffer();
}

function submitOffer(): void {
    send(
        editingId.value !== null
            ? update([props.event.id, editingId.value])
            : store(props.event.id),
        {
            ...offer.value,
            departs_from: offer.value.departs_from || null,
            departs_at: offer.value.departs_at || null,
            note: offer.value.note || null,
        },
        closeOffer,
    );
}

const field =
    'h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
</script>

<template>
    <div class="flex flex-col gap-4" data-test="transport">
        <section
            v-if="transport.needs_ride.length"
            class="flex flex-col gap-2 rounded-xl border border-dashed p-4"
        >
            <h2 class="font-medium">Still needs a ride there</h2>
            <ul class="flex flex-wrap gap-3">
                <li
                    v-for="person in transport.needs_ride"
                    :key="person.id"
                    class="flex items-center gap-1.5 text-sm"
                >
                    <MemberAvatar :member="person" />{{ person.name }}
                </li>
            </ul>
        </section>

        <section
            v-for="group in groups"
            :key="group.direction"
            class="flex flex-col gap-3"
        >
            <h2 class="text-sm font-medium text-muted-foreground">
                {{ directions[group.direction] }}
            </h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="ride in group.rides"
                    :key="ride.id"
                    class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                    :data-test="`ride-${ride.id}`"
                >
                    <header class="flex items-start gap-2.5">
                        <MemberAvatar :member="ride.driver" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                {{ ride.driver.name }} drives
                            </p>
                            <p
                                class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="ride.departs_from"
                                    class="inline-flex items-center gap-1"
                                    ><MapPin class="size-3" />{{
                                        ride.departs_from
                                    }}</span
                                >
                                <span v-if="departs(ride)">{{
                                    departs(ride)
                                }}</span>
                            </p>
                        </div>
                        <Button
                            v-if="ride.can_manage"
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            aria-label="Edit this ride"
                            :disabled="busy"
                            @click="startEdit(ride)"
                        >
                            <Pencil class="size-3.5" />
                        </Button>
                        <Button
                            v-if="ride.can_manage"
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            aria-label="Cancel this ride"
                            :disabled="busy"
                            @click="send(destroy([event.id, ride.id]))"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </header>
                    <p v-if="ride.note" class="text-sm text-muted-foreground">
                        {{ ride.note }}
                    </p>

                    <div
                        class="flex items-center gap-1.5"
                        :aria-label="`${ride.passengers.length} of ${ride.seats} seats taken`"
                    >
                        <span
                            v-for="seat in ride.seats"
                            :key="seat"
                            :class="[
                                'h-2 flex-1 rounded-full',
                                seat <= ride.passengers.length
                                    ? 'bg-foreground/70'
                                    : 'bg-muted',
                            ]"
                        />
                        <span
                            class="ml-1 shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            {{ ride.seats - ride.passengers.length }} free
                        </span>
                    </div>

                    <ul
                        v-if="ride.passengers.length"
                        class="flex flex-col gap-1.5"
                    >
                        <li
                            v-for="p in ride.passengers"
                            :key="p.member.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <MemberAvatar :member="p.member" />
                            <span class="min-w-0 flex-1 truncate">
                                {{ p.member.name }}
                                <span
                                    v-if="p.pickup_note"
                                    class="text-xs text-muted-foreground"
                                    >· {{ p.pickup_note }}</span
                                >
                            </span>
                            <Button
                                v-if="ride.can_manage || p.member.id === me"
                                size="icon"
                                variant="ghost"
                                class="size-7"
                                :aria-label="
                                    p.member.id === me
                                        ? 'Leave this ride'
                                        : `Remove ${p.member.name}`
                                "
                                :disabled="busy"
                                @click="
                                    send(leave([event.id, ride.id]), {
                                        discord_user_id: p.member.id,
                                    })
                                "
                            >
                                <X class="size-3.5" />
                            </Button>
                        </li>
                    </ul>

                    <form
                        v-if="
                            event.can.participate &&
                            ride.driver.id !== me &&
                            !seated(ride) &&
                            ride.passengers.length < ride.seats
                        "
                        class="flex gap-2"
                        @submit.prevent="
                            send(join([event.id, ride.id]), {
                                pickup_note: pickup[ride.id] || null,
                            })
                        "
                    >
                        <Input
                            v-model="pickup[ride.id]"
                            placeholder="Pick me up at… (optional)"
                            aria-label="Pickup"
                            maxlength="120"
                        />
                        <Button
                            type="submit"
                            variant="secondary"
                            :disabled="busy"
                            >Take a seat</Button
                        >
                    </form>
                </article>
            </div>
        </section>

        <p
            v-if="transport.rides.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            No rides offered yet. Driving? Offer your free seats.
        </p>

        <section
            v-if="event.can.participate"
            ref="offerForm"
            class="rounded-xl border bg-card p-4"
        >
            <Button v-if="!offering" variant="outline" @click="offering = true"
                ><Car class="size-4" />Offer a ride</Button
            >
            <form
                v-else
                class="grid gap-3 sm:grid-cols-2"
                @submit.prevent="submitOffer"
            >
                <div class="grid gap-1.5">
                    <Label for="ride-direction">Which way</Label>
                    <select
                        id="ride-direction"
                        v-model="offer.direction"
                        :class="field"
                    >
                        <option
                            v-for="(label, value) in directions"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="ride-seats">Free seats</Label>
                    <Input
                        id="ride-seats"
                        v-model.number="offer.seats"
                        type="number"
                        min="1"
                        max="8"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ride-from">Leaving from</Label>
                    <Input
                        id="ride-from"
                        v-model="offer.departs_from"
                        placeholder="Central station"
                        maxlength="120"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ride-at">Leaving at</Label>
                    <Input
                        id="ride-at"
                        v-model="offer.departs_at"
                        type="datetime-local"
                    />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="ride-note">Note</Label>
                    <Input
                        id="ride-note"
                        v-model="offer.note"
                        placeholder="Room for a bag each"
                        maxlength="200"
                    />
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <Button type="button" variant="ghost" @click="closeOffer"
                        >Never mind</Button
                    >
                    <Button type="submit" :disabled="busy">{{
                        editingId !== null ? 'Save ride' : 'Offer ride'
                    }}</Button>
                </div>
            </form>
        </section>
    </div>
</template>
