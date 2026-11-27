<script setup lang="ts">
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import type { EventResource } from '@/typefinder';
import BringList from './food/BringList.vue';
import MealPlan from './food/MealPlan.vue';
import ShoppingList from './food/ShoppingList.vue';
import type { Food } from './food/types';

defineProps<{
    event: EventResource;
    food: Food;
    me: number;
    firstDay: string;
}>();
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        <section
            v-if="food.dietary && food.dietary.length"
            class="flex flex-col gap-2 rounded-xl border bg-card p-4 lg:col-span-2 2xl:col-span-3"
            data-test="dietary"
        >
            <h2 class="font-medium">Dietary needs</h2>
            <p class="-mt-1 text-xs text-muted-foreground">
                Only organisers see this. From people going or maybe going.
            </p>
            <ul class="grid gap-2 sm:grid-cols-2">
                <li
                    v-for="d in food.dietary"
                    :key="d.member.id"
                    class="flex items-start gap-2 text-sm"
                >
                    <MemberAvatar :member="d.member" />
                    <span
                        ><span class="font-medium">{{ d.member.name }}</span>:
                        {{ d.notes }}</span
                    >
                </li>
            </ul>
        </section>
        <BringList
            :event-id="event.id"
            :items="food.bring"
            :me="me"
            :can-edit="event.can.edit"
            :can-participate="event.can.participate"
        />
        <ShoppingList
            :event-id="event.id"
            :items="food.shopping"
            :can-participate="event.can.participate"
        />
        <MealPlan
            class="lg:col-span-2 2xl:col-span-1"
            :event-id="event.id"
            :meals="food.meals"
            :me="me"
            :first-day="firstDay"
            :can-edit="event.can.edit"
            :can-participate="event.can.participate"
        />
    </div>
</template>
