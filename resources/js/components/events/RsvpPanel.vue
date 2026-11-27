<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Check, CircleHelp, X } from '@lucide/vue';
import { update as rsvp } from '@/actions/App/Http/Controllers/EventRsvpController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EventResource, RsvpStatus } from '@/typefinder';

const props = defineProps<{ event: EventResource }>();

const form = useForm({
    status: (props.event.my_rsvp?.status ?? null) as RsvpStatus | null,
    guests: props.event.my_rsvp?.guests ?? 0,
    note: props.event.my_rsvp?.note ?? '',
    needs_ride: props.event.my_rsvp?.needs_ride ?? false,
});

/** Past the answer-by time Going and Maybe are closed (organisers may still answer). */
const closed = computed(() => props.event.rsvp_closed && !props.event.can.edit);

const answerBy = computed(() =>
    props.event.rsvp_by
        ? new Intl.DateTimeFormat(undefined, {
              timeZone: props.event.timezone,
              weekday: 'short',
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(props.event.rsvp_by))
        : '',
);

const choices = [
    { status: 'going', label: 'Going', icon: Check },
    { status: 'maybe', label: 'Maybe', icon: CircleHelp },
    { status: 'declined', label: 'Can’t', icon: X },
] as const;

function answer(status?: RsvpStatus): void {
    if (status) {
        form.status = status;
    }

    if (form.status) {
        form.submit(rsvp(props.event.id), { preserveScroll: true });
    }
}
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border bg-card p-4"
        data-test="rsvp"
    >
        <h2 class="font-medium">Are you coming?</h2>
        <p v-if="event.rsvp_by" class="-mt-2 text-xs text-muted-foreground">
            <template v-if="closed">
                Answers closed {{ answerBy }}. You can still say you can't make
                it.
            </template>
            <template v-else>Please answer by {{ answerBy }}.</template>
        </p>
        <div
            class="grid grid-cols-3 gap-2"
            role="group"
            aria-label="Your answer"
        >
            <Button
                v-for="choice in choices"
                :key="choice.status"
                :variant="form.status === choice.status ? 'default' : 'outline'"
                :aria-pressed="form.status === choice.status"
                :disabled="
                    form.processing || (closed && choice.status !== 'declined')
                "
                @click="answer(choice.status)"
            >
                <component :is="choice.icon" class="size-4" />
                {{ choice.label }}
            </Button>
        </div>

        <InputError :message="form.errors.status" />

        <div
            v-if="form.status && form.status !== 'declined'"
            class="grid gap-3 sm:grid-cols-[auto_1fr] sm:items-end"
        >
            <div class="grid gap-1.5">
                <Label for="guests">Bringing</Label>
                <div class="flex items-center gap-2">
                    <Input
                        id="guests"
                        v-model.number="form.guests"
                        type="number"
                        min="0"
                        max="20"
                        class="w-20"
                        @change="answer()"
                    />
                    <span class="text-sm text-muted-foreground">{{
                        form.guests === 1 ? 'guest' : 'guests'
                    }}</span>
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label for="note">Note</Label>
                <Input
                    id="note"
                    v-model="form.note"
                    placeholder="Arriving late, bringing the dog…"
                    maxlength="200"
                    @change="answer()"
                />
            </div>
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <Checkbox
                    v-model="form.needs_ride"
                    @update:model-value="answer()"
                />
                I need a ride
            </label>
        </div>
    </section>
</template>
