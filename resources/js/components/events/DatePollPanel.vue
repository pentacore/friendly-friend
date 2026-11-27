<script setup lang="ts">
import { CalendarCheck, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import {
    choose,
    destroy,
    store,
    vote,
} from '@/actions/App/Http/Controllers/EventDatePollController';
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEventAction } from '@/composables/useEventAction';
import { formatEventWhen } from '@/lib/eventDates';
import type { DateVote, EventResource, PageProps } from '@/typefinder';

type Option = PageProps['events/Show']['date_poll'][number];

const props = defineProps<{
    event: EventResource;
    options: Option[];
    errors: Record<string, string>;
}>();

const { busy, send } = useEventAction([
    'event',
    'date_poll',
    'attendees',
    'errors',
]);

const answers: { value: DateVote; label: string }[] = [
    { value: 'yes', label: 'Yes' },
    { value: 'if_need_be', label: 'If need be' },
    { value: 'no', label: 'No' },
];

function cast(option: Option, value: DateVote): void {
    send(vote([props.event.id, option.id]), {
        answer: option.mine === value ? null : value,
    });
}

const confirming = ref<number | null>(null);
const adding = ref(false);
const draft = ref({ starts_at: '', ends_at: '' });

function add(): void {
    send(
        store(props.event.id),
        {
            starts_at: draft.value.starts_at,
            ends_at: draft.value.ends_at || null,
        },
        () => {
            adding.value = false;
            draft.value = { starts_at: '', ends_at: '' };
        },
    );
}
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-xl border bg-card p-4"
        data-test="date-poll"
    >
        <header>
            <h2 class="font-medium">Which dates work for you?</h2>
            <p class="text-xs text-muted-foreground">
                Answer every date.
                {{
                    event.can.edit
                        ? 'Choose one when it’s clear.'
                        : 'The organisers pick one when it’s clear.'
                }}
            </p>
        </header>

        <article
            v-for="option in options"
            :key="option.id"
            :class="[
                'flex flex-col gap-2 rounded-lg border p-3',
                option.best ? 'border-foreground/40' : '',
            ]"
            :data-test="`date-${option.id}`"
        >
            <div class="flex items-start gap-2">
                <p class="min-w-0 flex-1 text-sm font-medium">
                    {{
                        formatEventWhen(
                            option.starts_at,
                            option.ends_at,
                            event.timezone,
                        )
                    }}
                </p>
                <Badge v-if="option.best" variant="secondary"
                    >Best so far</Badge
                >
                <Button
                    v-if="event.can.edit"
                    size="icon"
                    variant="ghost"
                    class="size-7"
                    aria-label="Withdraw this date"
                    :disabled="busy"
                    @click="send(destroy([event.id, option.id]))"
                >
                    <Trash2 class="size-3.5" />
                </Button>
            </div>

            <div
                class="grid grid-cols-3 gap-1.5"
                role="group"
                :aria-label="`Can you make ${formatEventWhen(option.starts_at, option.ends_at, event.timezone)}?`"
            >
                <Button
                    v-for="a in answers"
                    :key="a.value"
                    size="sm"
                    :variant="option.mine === a.value ? 'default' : 'outline'"
                    :aria-pressed="option.mine === a.value"
                    :disabled="busy || !event.can.participate"
                    @click="cast(option, a.value)"
                >
                    {{ a.label }}
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ option.yes }} yes · {{ option.if_need_be }} if need be ·
                {{ option.no }} no
            </p>
            <p
                v-if="option.votes.length"
                class="flex flex-wrap gap-1.5 text-xs"
            >
                <span
                    v-for="v in option.votes"
                    :key="v.member.id"
                    :class="[
                        'inline-flex items-center gap-1',
                        v.answer === 'no'
                            ? 'text-muted-foreground line-through'
                            : '',
                    ]"
                    :title="answers.find((a) => a.value === v.answer)?.label"
                >
                    <MemberAvatar :member="v.member" />{{ v.member.name
                    }}<template v-if="v.answer === 'if_need_be'">?</template>
                </span>
            </p>

            <template v-if="event.can.edit">
                <Button
                    v-if="confirming !== option.id"
                    size="sm"
                    variant="secondary"
                    class="self-start"
                    :disabled="busy"
                    @click="confirming = option.id"
                >
                    <CalendarCheck class="size-4" />Choose this date
                </Button>
                <div v-else class="flex flex-wrap items-center gap-2 text-sm">
                    <span>Everyone who said yes will be going.</span>
                    <Button
                        size="sm"
                        :disabled="busy"
                        @click="send(choose([event.id, option.id]))"
                        >Choose it</Button
                    >
                    <Button size="sm" variant="ghost" @click="confirming = null"
                        >Not yet</Button
                    >
                </div>
            </template>
        </article>

        <template v-if="event.can.edit">
            <Button
                v-if="!adding"
                variant="outline"
                size="sm"
                class="self-start"
                @click="adding = true"
            >
                <Plus class="size-4" />Propose another date
            </Button>
            <form v-else class="grid gap-2" @submit.prevent="add">
                <div class="grid gap-1.5">
                    <Label for="date-option-start">Starts</Label>
                    <Input
                        id="date-option-start"
                        v-model="draft.starts_at"
                        type="datetime-local"
                        required
                    />
                    <InputError :message="errors.starts_at" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="date-option-end"
                        >Ends
                        <span class="text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <Input
                        id="date-option-end"
                        v-model="draft.ends_at"
                        type="datetime-local"
                    />
                    <InputError :message="errors.ends_at" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="adding = false"
                        >Never mind</Button
                    >
                    <Button type="submit" size="sm" :disabled="busy"
                        >Add date</Button
                    >
                </div>
            </form>
        </template>
    </section>
</template>
