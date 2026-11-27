<script setup lang="ts">
import { ChefHat, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    cook,
    destroyMeal,
    storeMeal,
} from '@/actions/App/Http/Controllers/EventFoodController';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEventAction } from '@/composables/useEventAction';
import type { MealSlot } from '@/typefinder';
import type { Food } from './types';

const props = defineProps<{
    eventId: number;
    meals: Food['meals'];
    me: number;
    firstDay: string;
    canEdit: boolean;
    canParticipate: boolean;
}>();

const { busy, send } = useEventAction(['food']);

const slots: Record<MealSlot, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snack: 'Snack',
};

const days = computed(() => {
    const byDay = new Map<string, Food['meals']>();

    for (const meal of props.meals) {
        byDay.set(meal.date, [...(byDay.get(meal.date) ?? []), meal]);
    }

    return [...byDay.entries()];
});

function dayLabel(date: string): string {
    return new Date(`${date}T12:00:00`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
    });
}

const draft = ref({
    date: props.firstDay,
    slot: 'dinner' as MealSlot,
    title: '',
    notes: '',
});

function add(): void {
    send(
        storeMeal(props.eventId),
        { ...draft.value, notes: draft.value.notes || null },
        () => {
            draft.value = { ...draft.value, title: '', notes: '' };
        },
    );
}

const field =
    'h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border bg-card p-4"
        data-test="meal-plan"
    >
        <h2 class="font-medium">Meal plan</h2>

        <div
            v-for="[date, dayMeals] in days"
            :key="date"
            class="flex flex-col gap-2"
        >
            <h3
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ dayLabel(date) }}
            </h3>
            <article
                v-for="meal in dayMeals"
                :key="meal.id"
                class="flex flex-wrap items-start gap-3 rounded-lg border p-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-muted-foreground">
                        {{ slots[meal.slot] }}
                    </p>
                    <p class="font-medium">{{ meal.title }}</p>
                    <p
                        v-if="meal.notes"
                        class="mt-1 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ meal.notes }}
                    </p>
                    <p
                        class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <ChefHat class="size-3.5" />
                        <template v-if="meal.cooks.length">
                            <span
                                v-for="c in meal.cooks"
                                :key="c.id"
                                class="inline-flex items-center gap-1"
                            >
                                <MemberAvatar :member="c" />{{ c.name }}
                            </span>
                        </template>
                        <span v-else>No cook yet</span>
                    </p>
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        v-if="canParticipate"
                        size="sm"
                        :variant="
                            meal.cooks.some((c) => c.id === me)
                                ? 'secondary'
                                : 'outline'
                        "
                        :disabled="busy"
                        @click="
                            send(cook([eventId, meal.id]), {
                                cooking: !meal.cooks.some((c) => c.id === me),
                            })
                        "
                    >
                        {{
                            meal.cooks.some((c) => c.id === me)
                                ? 'Not cooking'
                                : 'I’ll cook'
                        }}
                    </Button>
                    <Button
                        v-if="canEdit"
                        size="icon"
                        variant="ghost"
                        class="size-8"
                        :aria-label="`Remove ${meal.title}`"
                        :disabled="busy"
                        @click="send(destroyMeal([eventId, meal.id]))"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
            </article>
        </div>
        <p v-if="meals.length === 0" class="text-sm text-muted-foreground">
            {{
                canEdit
                    ? 'Plan who cooks what, meal by meal.'
                    : 'No meals planned yet.'
            }}
        </p>

        <form
            v-if="canEdit"
            class="grid grid-cols-2 gap-2"
            @submit.prevent="add"
        >
            <input
                v-model="draft.date"
                type="date"
                :class="[field, 'min-w-0']"
                aria-label="Day"
                required
            />
            <select v-model="draft.slot" :class="field" aria-label="Meal">
                <option v-for="(name, slot) in slots" :key="slot" :value="slot">
                    {{ name }}
                </option>
            </select>
            <Input
                v-model="draft.title"
                placeholder="Tacos"
                aria-label="What"
                required
                maxlength="120"
                class="col-span-2 sm:col-span-1"
            />
            <Button
                type="submit"
                variant="secondary"
                class="col-span-2 sm:col-span-1"
                :disabled="busy"
                >Add meal</Button
            >
        </form>
    </section>
</template>
