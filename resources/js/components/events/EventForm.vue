<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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

const voting = ref((props.form.date_options?.length ?? 0) > 0);

function setVoting(on: boolean | 'indeterminate'): void {
    voting.value = on === true;
    props.form.date_options = voting.value
        ? [
              { starts_at: props.form.starts_at ?? '', ends_at: null },
              { starts_at: '', ends_at: null },
          ]
        : null;
}

/** The first error about the proposed dates, whichever row it's on. */
const dateOptionsError = computed(
    () =>
        Object.entries(
            props.form.errors as Record<string, string | undefined>,
        ).find(([key]) => key.startsWith('date_options'))?.[1],
);

function addDateOption(): void {
    props.form.date_options = [
        ...(props.form.date_options ?? []),
        { starts_at: '', ends_at: null },
    ];
}

function removeDateOption(index: number): void {
    props.form.date_options = (props.form.date_options ?? []).filter(
        (_, i) => i !== index,
    );
}

defineEmits<{ submit: [] }>();

const hostChoices = computed(() =>
    props.members.filter((m) => m.id !== props.excludeHostId),
);

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
    <form class="flex flex-col gap-5" @submit.prevent="$emit('submit')">
        <slot name="before" />

        <div class="grid gap-2">
            <Label for="title">What</Label>
            <Input
                id="title"
                v-model="form.title"
                placeholder="Crayfish party at the cabin"
                required
            />
            <InputError :message="form.errors.title" />
        </div>

        <p
            v-if="datesLocked"
            class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
        >
            The date is being voted on. Propose dates and choose one on the
            event page.
        </p>

        <label v-if="allowDatePoll" class="flex items-center gap-2 text-sm">
            <Checkbox :model-value="voting" @update:model-value="setVoting" />
            Not sure when? Let people vote on the date
        </label>

        <fieldset v-if="voting" class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">Dates to vote on</legend>
            <div
                v-for="(option, index) in form.date_options ?? []"
                :key="index"
                class="grid grid-cols-[1fr_1fr_auto] items-center gap-2"
            >
                <Input
                    v-model="option.starts_at"
                    type="datetime-local"
                    :aria-label="`Date ${index + 1} starts`"
                    required
                />
                <Input
                    :model-value="option.ends_at ?? ''"
                    type="datetime-local"
                    :aria-label="`Date ${index + 1} ends (optional)`"
                    @update:model-value="
                        option.ends_at = String($event) || null
                    "
                />
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    :disabled="(form.date_options?.length ?? 0) <= 2"
                    :aria-label="`Remove date ${index + 1}`"
                    @click="removeDateOption(index)"
                >
                    <X class="size-3.5" />
                </Button>
            </div>
            <Button
                v-if="(form.date_options?.length ?? 0) < 10"
                type="button"
                variant="ghost"
                size="sm"
                class="self-start"
                @click="addDateOption"
            >
                <Plus class="size-3.5" />Another date
            </Button>
            <InputError :message="dateOptionsError" />
        </fieldset>

        <div v-if="!voting && !datesLocked" class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="starts_at">Starts</Label>
                <Input
                    id="starts_at"
                    :model-value="form.starts_at ?? ''"
                    @update:model-value="
                        form.starts_at = String($event) || null
                    "
                    type="datetime-local"
                    :required="!voting"
                />
                <InputError :message="form.errors.starts_at" />
            </div>
            <div class="grid gap-2">
                <Label for="ends_at"
                    >Ends
                    <span class="text-muted-foreground">(optional)</span></Label
                >
                <Input
                    id="ends_at"
                    :model-value="form.ends_at ?? ''"
                    type="datetime-local"
                    @update:model-value="form.ends_at = String($event) || null"
                />
                <InputError :message="form.errors.ends_at" />
            </div>
        </div>
        <div class="grid gap-2 sm:w-1/2 sm:pr-2">
            <Label for="rsvp_by"
                >Answer by
                <span class="text-muted-foreground">(optional)</span></Label
            >
            <Input
                id="rsvp_by"
                :model-value="form.rsvp_by ?? ''"
                type="datetime-local"
                @update:model-value="form.rsvp_by = String($event) || null"
            />
            <InputError :message="form.errors.rsvp_by" />
        </div>
        <p class="-mt-3 text-xs text-muted-foreground">
            Times are in {{ form.timezone }}. After the answer-by time, people
            can only say they can't make it.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="location">Where</Label>
                <Input
                    id="location"
                    :model-value="form.location ?? ''"
                    placeholder="The cabin"
                    @update:model-value="form.location = String($event) || null"
                />
                <InputError :message="form.errors.location" />
            </div>
            <div class="grid gap-2">
                <Label for="location_url"
                    >Map link
                    <span class="text-muted-foreground">(optional)</span></Label
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
                rows="5"
                :class="field"
                placeholder="What to expect, what to wear, where to park…"
                @input="
                    form.description =
                        ($event.target as HTMLTextAreaElement).value || null
                "
            />
            <InputError :message="form.errors.description" />
        </div>

        <fieldset v-if="hostChoices.length" class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">Co-hosts</legend>
            <p class="-mt-1 mb-1 text-xs text-muted-foreground">
                They can edit the event, just like you.
            </p>
            <div
                class="grid max-h-56 gap-1.5 overflow-y-auto rounded-md border p-2 sm:grid-cols-2"
            >
                <label
                    v-for="member in hostChoices"
                    :key="member.id"
                    class="flex items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-accent/50"
                >
                    <Checkbox
                        :model-value="
                            form.host_ids?.includes(member.id) ?? false
                        "
                        @update:model-value="toggleHost(member.id, $event)"
                    />
                    <img
                        :src="member.avatar_url"
                        alt=""
                        class="size-5 rounded-full"
                    />
                    <span class="truncate">{{ member.name }}</span>
                </label>
            </div>
        </fieldset>

        <div class="flex justify-end gap-2">
            <slot name="actions" />
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>
