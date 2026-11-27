<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import {
    Car,
    ChefHat,
    CircleHelp,
    Coins,
    Package,
    Plus,
    ShoppingCart,
    X,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { nowInputIn, todayIn } from '@/lib/eventDates';
import type {
    MealSlot,
    QuestionKind,
    RideDirection,
    SaveEventRequest,
} from '@/typefinder';

/**
 * Optional parts of a new event that can be planned right away. Each one
 * is off until picked; everything can also be added later on the event's
 * tabs.
 */
const props = defineProps<{ form: InertiaForm<SaveEventRequest> }>();

type Section = 'questions' | 'bring' | 'shopping' | 'meals' | 'ride' | 'costs';

const sections: {
    id: Section;
    label: string;
    icon: Component;
    hint: string;
}[] = [
    {
        id: 'questions',
        label: 'Questions',
        icon: CircleHelp,
        hint: 'Ask everyone coming something, like "Will you eat crayfish?"',
    },
    {
        id: 'bring',
        label: 'Bring list',
        icon: Package,
        hint: 'Things people can sign up to bring.',
    },
    {
        id: 'shopping',
        label: 'Shopping',
        icon: ShoppingCart,
        hint: 'What needs buying; people tick it off.',
    },
    {
        id: 'meals',
        label: 'Meals',
        icon: ChefHat,
        hint: 'Who cooks what, meal by meal.',
    },
    {
        id: 'ride',
        label: 'My ride',
        icon: Car,
        hint: 'You’re driving and have free seats.',
    },
    {
        id: 'costs',
        label: 'Costs',
        icon: Coins,
        hint: 'What you paid, split among everyone going.',
    },
];

const open = ref<Set<Section>>(new Set());

function toggle(section: Section): void {
    const next = new Set(open.value);

    if (next.has(section)) {
        next.delete(section);
        clear(section);
    } else {
        next.add(section);
        start(section);
    }

    open.value = next;
}

/** A section starts with one empty row, ready to type in. */
function start(section: Section): void {
    const f = props.form;

    if (section === 'questions') {
        f.questions = [{ prompt: '', kind: 'yes_no', options: [] }];
    } else if (section === 'bring') {
        f.bring = [{ label: '', quantity: 1 }];
    } else if (section === 'shopping') {
        f.shopping = [{ label: '', quantity: null }];
    } else if (section === 'meals') {
        f.meals = [{ date: firstDay.value, slot: 'dinner', title: '' }];
    } else if (section === 'ride') {
        f.ride = {
            direction: 'both',
            seats: 3,
            departs_from: null,
            departs_at: null,
        };
    } else {
        f.costs = [{ description: '', amount: '' }];
    }
}

type ListSection = Exclude<Section, 'ride'>;

/** Removing the last row removes the section. */
function removeRow(section: ListSection, index: number): void {
    props.form[section]?.splice(index, 1);

    if ((props.form[section]?.length ?? 0) === 0) {
        toggle(section);
    }
}

function clear(section: Section): void {
    if (section === 'ride') {
        props.form.ride = null;
    } else {
        props.form[section] = null;
    }
}

/** Meals default to the event's (first) day. */
const firstDay = computed(
    () =>
        (
            props.form.starts_at ??
            props.form.date_options?.[0]?.starts_at ??
            ''
        ).split('T')[0] || todayIn(props.form.timezone),
);

const kinds: Record<QuestionKind, string> = {
    yes_no: 'Yes or no',
    choice: 'Pick one',
    text: 'Free text',
};
const slots: Record<MealSlot, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snack: 'Snack',
};
const directions: Record<RideDirection, string> = {
    to: 'There',
    from: 'Back',
    both: 'There and back',
};

/** Errors for a section, whichever row they're on. */
function errorFor(section: Section): string | undefined {
    return Object.entries(
        props.form.errors as Record<string, string | undefined>,
    ).find(([key]) => key === section || key.startsWith(`${section}.`))?.[1];
}

const field =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
</script>

<template>
    <section class="flex flex-col gap-4" data-test="plan-ahead">
        <header class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h2 class="font-medium">
                    Plan ahead
                    <span class="font-normal text-muted-foreground"
                        >(optional)</span
                    >
                </h2>
                <p class="text-xs text-muted-foreground">
                    Add what you already know. Everything can also be added
                    later on the event page.
                </p>
            </div>
        </header>

        <div class="flex flex-wrap gap-2" role="group" aria-label="Plan ahead">
            <Button
                v-for="section in sections"
                :key="section.id"
                type="button"
                size="sm"
                :variant="open.has(section.id) ? 'default' : 'outline'"
                :aria-pressed="open.has(section.id)"
                :title="section.hint"
                @click="toggle(section.id)"
            >
                <component :is="section.icon" class="size-4" />
                {{ section.label }}
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            <!-- Questions -->
            <div
                v-if="open.has('questions')"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                data-test="plan-questions"
            >
                <header class="flex items-center gap-2">
                    <h3
                        class="flex flex-1 items-center gap-2 text-sm font-medium"
                    >
                        <CircleHelp class="size-4" />Questions
                    </h3>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        aria-label="Remove this section"
                        @click="toggle('questions')"
                    >
                        <X class="size-3.5" />
                    </Button>
                </header>
                <div
                    v-for="(q, i) in form.questions ?? []"
                    :key="i"
                    class="grid gap-2 rounded-lg border p-2.5"
                >
                    <div class="flex gap-2">
                        <Input
                            v-model="q.prompt"
                            placeholder="Will you eat crayfish?"
                            :aria-label="`Question ${i + 1}`"
                            maxlength="200"
                        />
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :aria-label="`Remove question ${i + 1}`"
                            @click="removeRow('questions', i)"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </div>
                    <select
                        v-model="q.kind"
                        :class="field"
                        :aria-label="`Answer to question ${i + 1}`"
                    >
                        <option
                            v-for="(name, kind) in kinds"
                            :key="kind"
                            :value="kind"
                        >
                            {{ name }}
                        </option>
                    </select>
                    <textarea
                        v-if="q.kind === 'choice'"
                        :value="(q.options ?? []).join('\n')"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        placeholder="One option per line"
                        :aria-label="`Options for question ${i + 1}`"
                        @input="
                            q.options = (
                                $event.target as HTMLTextAreaElement
                            ).value.split('\n')
                        "
                    />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="self-start"
                    @click="
                        form.questions = [
                            ...(form.questions ?? []),
                            { prompt: '', kind: 'yes_no', options: [] },
                        ]
                    "
                >
                    <Plus class="size-3.5" />Another question
                </Button>
                <InputError :message="errorFor('questions')" />
            </div>

            <!-- Bring list -->
            <div
                v-if="open.has('bring')"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                data-test="plan-bring"
            >
                <header class="flex items-center gap-2">
                    <h3
                        class="flex flex-1 items-center gap-2 text-sm font-medium"
                    >
                        <Package class="size-4" />Bring list
                    </h3>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        aria-label="Remove this section"
                        @click="toggle('bring')"
                    >
                        <X class="size-3.5" />
                    </Button>
                </header>
                <div
                    v-for="(item, i) in form.bring ?? []"
                    :key="i"
                    class="grid grid-cols-[1fr_5rem_auto] gap-2"
                >
                    <Input
                        v-model="item.label"
                        placeholder="Folding chairs"
                        :aria-label="`Bring item ${i + 1}`"
                        maxlength="120"
                    />
                    <Input
                        v-model.number="item.quantity"
                        type="number"
                        min="1"
                        max="99"
                        :aria-label="`How many of item ${i + 1}`"
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :aria-label="`Remove item ${i + 1}`"
                        @click="removeRow('bring', i)"
                    >
                        <X class="size-3.5" />
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="self-start"
                    @click="
                        form.bring = [
                            ...(form.bring ?? []),
                            { label: '', quantity: 1 },
                        ]
                    "
                >
                    <Plus class="size-3.5" />Another item
                </Button>
                <InputError :message="errorFor('bring')" />
            </div>

            <!-- Shopping -->
            <div
                v-if="open.has('shopping')"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                data-test="plan-shopping"
            >
                <header class="flex items-center gap-2">
                    <h3
                        class="flex flex-1 items-center gap-2 text-sm font-medium"
                    >
                        <ShoppingCart class="size-4" />Shopping list
                    </h3>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        aria-label="Remove this section"
                        @click="toggle('shopping')"
                    >
                        <X class="size-3.5" />
                    </Button>
                </header>
                <div
                    v-for="(item, i) in form.shopping ?? []"
                    :key="i"
                    class="grid grid-cols-[1fr_7rem_auto] gap-2"
                >
                    <Input
                        v-model="item.label"
                        placeholder="Crisps"
                        :aria-label="`Shopping item ${i + 1}`"
                        maxlength="120"
                    />
                    <Input
                        :model-value="item.quantity ?? ''"
                        placeholder="Amount"
                        :aria-label="`Amount of shopping item ${i + 1}`"
                        maxlength="40"
                        @update:model-value="
                            item.quantity = String($event) || null
                        "
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :aria-label="`Remove shopping item ${i + 1}`"
                        @click="removeRow('shopping', i)"
                    >
                        <X class="size-3.5" />
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="self-start"
                    @click="
                        form.shopping = [
                            ...(form.shopping ?? []),
                            { label: '', quantity: null },
                        ]
                    "
                >
                    <Plus class="size-3.5" />Another item
                </Button>
                <InputError :message="errorFor('shopping')" />
            </div>

            <!-- Meals -->
            <div
                v-if="open.has('meals')"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                data-test="plan-meals"
            >
                <header class="flex items-center gap-2">
                    <h3
                        class="flex flex-1 items-center gap-2 text-sm font-medium"
                    >
                        <ChefHat class="size-4" />Meals
                    </h3>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        aria-label="Remove this section"
                        @click="toggle('meals')"
                    >
                        <X class="size-3.5" />
                    </Button>
                </header>
                <div
                    v-for="(meal, i) in form.meals ?? []"
                    :key="i"
                    class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-2"
                >
                    <Input
                        v-model="meal.date"
                        type="date"
                        :min="todayIn(form.timezone)"
                        :aria-label="`Day of meal ${i + 1}`"
                    />
                    <select
                        v-model="meal.slot"
                        :class="field"
                        :aria-label="`Which meal ${i + 1}`"
                    >
                        <option
                            v-for="(name, slot) in slots"
                            :key="slot"
                            :value="slot"
                        >
                            {{ name }}
                        </option>
                    </select>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :aria-label="`Remove meal ${i + 1}`"
                        @click="removeRow('meals', i)"
                    >
                        <X class="size-3.5" />
                    </Button>
                    <Input
                        v-model="meal.title"
                        class="col-span-3"
                        placeholder="Tacos"
                        :aria-label="`What's for meal ${i + 1}`"
                        maxlength="120"
                    />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="self-start"
                    @click="
                        form.meals = [
                            ...(form.meals ?? []),
                            { date: firstDay, slot: 'dinner', title: '' },
                        ]
                    "
                >
                    <Plus class="size-3.5" />Another meal
                </Button>
                <InputError :message="errorFor('meals')" />
            </div>

            <!-- My ride -->
            <div
                v-if="open.has('ride') && form.ride"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                data-test="plan-ride"
            >
                <header class="flex items-center gap-2">
                    <h3
                        class="flex flex-1 items-center gap-2 text-sm font-medium"
                    >
                        <Car class="size-4" />My ride
                    </h3>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        aria-label="Remove this section"
                        @click="toggle('ride')"
                    >
                        <X class="size-3.5" />
                    </Button>
                </header>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-1.5">
                        <Label for="plan-ride-direction">Which way</Label>
                        <select
                            id="plan-ride-direction"
                            v-model="form.ride.direction"
                            :class="field"
                        >
                            <option
                                v-for="(name, value) in directions"
                                :key="value"
                                :value="value"
                            >
                                {{ name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="plan-ride-seats">Free seats</Label>
                        <Input
                            id="plan-ride-seats"
                            v-model.number="form.ride.seats"
                            type="number"
                            min="1"
                            max="8"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="plan-ride-from">Leaving from</Label>
                        <Input
                            id="plan-ride-from"
                            :model-value="form.ride.departs_from ?? ''"
                            placeholder="Central station"
                            maxlength="120"
                            @update:model-value="
                                form.ride.departs_from = String($event) || null
                            "
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="plan-ride-at">Leaving at</Label>
                        <Input
                            id="plan-ride-at"
                            :model-value="form.ride.departs_at ?? ''"
                            type="datetime-local"
                            :min="nowInputIn(form.timezone)"
                            @update:model-value="
                                form.ride.departs_at = String($event) || null
                            "
                        />
                    </div>
                </div>
                <InputError :message="errorFor('ride')" />
            </div>

            <!-- Costs -->
            <div
                v-if="open.has('costs')"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                data-test="plan-costs"
            >
                <header class="flex items-center gap-2">
                    <h3
                        class="flex flex-1 items-center gap-2 text-sm font-medium"
                    >
                        <Coins class="size-4" />Costs
                        <span class="font-normal text-muted-foreground">
                            in {{ form.currency ?? 'SEK' }}
                        </span>
                    </h3>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        aria-label="Remove this section"
                        @click="toggle('costs')"
                    >
                        <X class="size-3.5" />
                    </Button>
                </header>
                <p class="-mt-1 text-xs text-muted-foreground">
                    Paid by you, split among everyone going (guests count),
                    worked out as people answer.
                </p>
                <div
                    v-for="(cost, i) in form.costs ?? []"
                    :key="i"
                    class="grid grid-cols-[1fr_7rem_auto] gap-2"
                >
                    <Input
                        v-model="cost.description"
                        placeholder="Cabin rent"
                        :aria-label="`Cost ${i + 1}`"
                        maxlength="120"
                    />
                    <Input
                        v-model="cost.amount"
                        inputmode="decimal"
                        placeholder="4000"
                        :aria-label="`Amount of cost ${i + 1}`"
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :aria-label="`Remove cost ${i + 1}`"
                        @click="removeRow('costs', i)"
                    >
                        <X class="size-3.5" />
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="self-start"
                    @click="
                        form.costs = [
                            ...(form.costs ?? []),
                            { description: '', amount: '' },
                        ]
                    "
                >
                    <Plus class="size-3.5" />Another cost
                </Button>
                <InputError :message="errorFor('costs')" />
            </div>
        </div>
    </section>
</template>
