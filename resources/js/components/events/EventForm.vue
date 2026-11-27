<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    addDays,
    daysBetween,
    localDate,
    localDateTime,
    nextDay,
} from '@/lib/eventDates';
import type { SaveEventRequest } from '@/typefinder';

type Member = { id: number; name: string; avatar_url: string };

const props = defineProps<{
    form: InertiaForm<
        Omit<SaveEventRequest, 'guild_id'> & { guild_id?: number | null }
    >;
    members: Member[];
    submitLabel: string;
    excludeHostId?: number;
    /** Offer "let people vote on the date" (new events only). */
    allowDatePoll?: boolean;
    /** The date is being voted on: dates are set on the event page. */
    datesLocked?: boolean;
}>();

defineEmits<{ submit: [] }>();

/** Pickers start at today: events can't be planned in the past. */
const today = localDate();
const now = localDateTime();

/**
 * One proposed date, edited as a day, a start time, and an optional end
 * (a time, plus a day when it ends on a later day). Rows added after the
 * first take its times and length, and keep following them until their own
 * times are changed.
 */
type PollRow = {
    date: string;
    start: string;
    endDate: string;
    end: string;
    followsFirst: boolean;
};

const voting = ref((props.form.date_options?.length ?? 0) > 0);
const rows = ref<PollRow[]>([]);

function setVoting(on: boolean | 'indeterminate'): void {
    voting.value = on === true;

    if (!voting.value) {
        rows.value = [];
        props.form.date_options = null;

        return;
    }

    const [date = '', start = '18:00'] = (props.form.starts_at ?? '').split(
        'T',
    );
    const [endDate = '', end = ''] = (props.form.ends_at ?? '').split('T');
    rows.value = [
        {
            date,
            start: start || '18:00',
            endDate: endDate !== date ? endDate : '',
            end,
            followsFirst: false,
        },
        {
            date: '',
            start: start || '18:00',
            endDate: '',
            end,
            followsFirst: true,
        },
    ];
}

function addRow(): void {
    const first = rows.value[0];
    rows.value.push({
        date: '',
        start: first?.start ?? '18:00',
        endDate: '',
        end: first?.end ?? '',
        followsFirst: true,
    });
}

function removeRow(index: number): void {
    rows.value.splice(index, 1);
}

function setRowTime(
    index: number,
    key: 'start' | 'end' | 'endDate',
    value: string,
): void {
    const row = rows.value[index];

    if (!row) {
        return;
    }

    row[key] = value;

    if (index > 0) {
        row.followsFirst = false;
    }
}

/** How many days after its start the first date ends (0: the same day). */
const firstLength = computed(() => {
    const first = rows.value[0];

    return first?.date && first.endDate
        ? daysBetween(first.date, first.endDate)
        : 0;
});

// Followers take the first row's times and length.
watch(
    () =>
        rows.value.map((row) => [
            row.date,
            row.start,
            row.end,
            row.endDate,
            row.followsFirst,
        ]),
    () => {
        const first = rows.value[0];

        for (const row of rows.value.slice(1)) {
            if (first && row.followsFirst) {
                row.start = first.start;
                row.end = first.end;
                row.endDate =
                    row.date && firstLength.value > 0
                        ? addDays(row.date, firstLength.value)
                        : '';
            }
        }
    },
    { deep: true },
);

// The form sends local date-times; an end before the start means the next day.
watch(
    rows,
    (value) => {
        if (!voting.value) {
            return;
        }

        props.form.date_options = value.map((row) => ({
            starts_at: row.date && row.start ? `${row.date}T${row.start}` : '',
            ends_at:
                row.date && row.end
                    ? `${row.endDate || (row.end <= row.start ? nextDay(row.date) : row.date)}T${row.end}`
                    : null,
        }));
    },
    { deep: true },
);

/** The first error about the proposed dates, whichever row it's on. */
const dateOptionsError = computed(
    () =>
        Object.entries(
            props.form.errors as Record<string, string | undefined>,
        ).find(([key]) => key.startsWith('date_options'))?.[1],
);

const hostChoices = computed(() =>
    props.members.filter((m) => m.id !== props.excludeHostId),
);

const hostFilter = ref('');

/** Matches for the filter; people already picked always stay visible. */
const visibleHosts = computed(() => {
    const term = hostFilter.value.trim().toLocaleLowerCase();

    return term === ''
        ? hostChoices.value
        : hostChoices.value.filter(
              (m) =>
                  m.name.toLocaleLowerCase().includes(term) ||
                  (props.form.host_ids ?? []).includes(m.id),
          );
});

function toggleHost(id: number, on: boolean | 'indeterminate'): void {
    const hosts = new Set(props.form.host_ids ?? []);

    if (on === true) {
        hosts.add(id);
    } else {
        hosts.delete(id);
    }

    props.form.host_ids = [...hosts];
}

const field =
    'w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="$emit('submit')">
        <slot name="before" />

        <div class="grid gap-2">
            <Label for="title">What</Label>
            <Input
                id="title"
                v-model="form.title"
                placeholder="Crayfish party at the cabin"
                class="h-11 text-base"
                required
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                class="flex flex-col gap-5 rounded-xl border bg-card p-4 sm:p-5"
            >
                <h2 class="font-medium">When</h2>

                <p
                    v-if="datesLocked"
                    class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                >
                    The date is being voted on. Propose dates and choose one on
                    the event page.
                </p>

                <label
                    v-if="allowDatePoll"
                    class="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="voting"
                        @update:model-value="setVoting"
                    />
                    Not sure when? Let people vote on the date
                </label>

                <fieldset v-if="voting" class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        Dates to vote on
                    </legend>
                    <div
                        v-for="(row, index) in rows"
                        :key="index"
                        class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-end gap-x-2 gap-y-1.5 rounded-lg border p-2.5"
                        :data-test="`date-option-${index}`"
                    >
                        <label class="grid gap-1 text-xs text-muted-foreground">
                            Day
                            <Input
                                v-model="row.date"
                                type="date"
                                :min="today"
                                :aria-label="`Date ${index + 1}: day`"
                                required
                            />
                        </label>
                        <label class="grid gap-1 text-xs text-muted-foreground">
                            From
                            <Input
                                :model-value="row.start"
                                type="time"
                                :aria-label="`Date ${index + 1}: from`"
                                required
                                @update:model-value="
                                    setRowTime(index, 'start', String($event))
                                "
                            />
                        </label>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :disabled="rows.length <= 2"
                            :aria-label="`Remove date ${index + 1}`"
                            @click="removeRow(index)"
                        >
                            <X class="size-3.5" />
                        </Button>
                        <label class="grid gap-1 text-xs text-muted-foreground">
                            Until day (if later)
                            <Input
                                :model-value="row.endDate"
                                type="date"
                                :min="row.date || today"
                                :aria-label="`Date ${index + 1}: until day (optional)`"
                                @update:model-value="
                                    setRowTime(index, 'endDate', String($event))
                                "
                            />
                        </label>
                        <label class="grid gap-1 text-xs text-muted-foreground">
                            Until (optional)
                            <Input
                                :model-value="row.end"
                                type="time"
                                :aria-label="`Date ${index + 1}: until (optional)`"
                                @update:model-value="
                                    setRowTime(index, 'end', String($event))
                                "
                            />
                        </label>
                    </div>
                    <Button
                        v-if="rows.length < 10"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="self-start"
                        @click="addRow"
                    >
                        <Plus class="size-3.5" />Another date
                    </Button>
                    <p class="text-xs text-muted-foreground">
                        New dates take the first date's times and length.
                        Without an until day it ends the same day, or the next
                        day if the until time is before the start.
                    </p>
                    <InputError :message="dateOptionsError" />
                </fieldset>

                <div
                    v-if="!voting && !datesLocked"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div class="grid gap-2">
                        <Label for="starts_at">Starts</Label>
                        <Input
                            id="starts_at"
                            :model-value="form.starts_at ?? ''"
                            type="datetime-local"
                            :min="now"
                            required
                            @update:model-value="
                                form.starts_at = String($event) || null
                            "
                        />
                        <InputError :message="form.errors.starts_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ends_at"
                            >Ends
                            <span class="text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="ends_at"
                            :model-value="form.ends_at ?? ''"
                            type="datetime-local"
                            :min="form.starts_at || now"
                            @update:model-value="
                                form.ends_at = String($event) || null
                            "
                        />
                        <InputError :message="form.errors.ends_at" />
                    </div>
                </div>

                <div class="grid gap-2 sm:w-1/2 sm:pr-2">
                    <Label for="rsvp_by"
                        >Answer by
                        <span class="text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <Input
                        id="rsvp_by"
                        :model-value="form.rsvp_by ?? ''"
                        type="datetime-local"
                        :min="now"
                        :max="form.starts_at || undefined"
                        @update:model-value="
                            form.rsvp_by = String($event) || null
                        "
                    />
                    <InputError :message="form.errors.rsvp_by" />
                </div>
                <p class="-mt-2 text-xs text-muted-foreground">
                    Times are in {{ form.timezone }}. After the answer-by time,
                    people can only say they can't make it.
                </p>
            </section>

            <section
                class="flex flex-col gap-5 rounded-xl border bg-card p-4 sm:p-5"
            >
                <h2 class="font-medium">Where and what</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="location">Where</Label>
                        <Input
                            id="location"
                            :model-value="form.location ?? ''"
                            placeholder="The cabin"
                            @update:model-value="
                                form.location = String($event) || null
                            "
                        />
                        <InputError :message="form.errors.location" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="location_url"
                            >Map link
                            <span class="text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="location_url"
                            :model-value="form.location_url ?? ''"
                            type="url"
                            placeholder="https://maps.app.goo.gl/…"
                            @update:model-value="
                                form.location_url = String($event) || null
                            "
                        />
                        <InputError :message="form.errors.location_url" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="description">Details</Label>
                    <textarea
                        id="description"
                        :value="form.description ?? ''"
                        rows="4"
                        :class="field"
                        placeholder="What to expect, what to wear, where to park…"
                        @input="
                            form.description =
                                ($event.target as HTMLTextAreaElement).value ||
                                null
                        "
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <fieldset v-if="hostChoices.length" class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Co-hosts</legend>
                    <p class="-mt-1 mb-1 text-xs text-muted-foreground">
                        They can edit the event, just like you.
                        <template v-if="form.host_ids?.length">
                            {{ form.host_ids.length }} picked.
                        </template>
                    </p>
                    <Input
                        v-if="hostChoices.length > 6"
                        v-model="hostFilter"
                        type="search"
                        placeholder="Find someone"
                        aria-label="Find a co-host"
                    />
                    <div
                        class="grid max-h-48 gap-1.5 overflow-y-auto rounded-md border p-2 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <p
                            v-if="visibleHosts.length === 0"
                            class="col-span-full px-1.5 py-1 text-sm text-muted-foreground"
                        >
                            Nobody called “{{ hostFilter }}”.
                        </p>
                        <label
                            v-for="member in visibleHosts"
                            :key="member.id"
                            class="flex items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-accent/50"
                        >
                            <Checkbox
                                :model-value="
                                    form.host_ids?.includes(member.id) ?? false
                                "
                                @update:model-value="
                                    toggleHost(member.id, $event)
                                "
                            />
                            <MemberAvatar :member="member" />
                            <span class="truncate">{{ member.name }}</span>
                        </label>
                    </div>
                </fieldset>
            </section>
        </div>

        <slot name="extras" />

        <div
            class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6"
        >
            <slot name="actions" />
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>
