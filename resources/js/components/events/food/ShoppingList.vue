<script setup lang="ts">
import { X } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroyShoppingItem,
    storeShoppingItem,
    tick,
} from '@/actions/App/Http/Controllers/EventFoodController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useEventAction } from '@/composables/useEventAction';
import type { Food } from './types';

const props = defineProps<{
    eventId: number;
    items: Food['shopping'];
    canParticipate: boolean;
}>();

const { busy, send } = useEventAction(['food']);
const label = ref('');
const quantity = ref('');

function add(): void {
    send(
        storeShoppingItem(props.eventId),
        { label: label.value, quantity: quantity.value || null },
        () => {
            label.value = '';
            quantity.value = '';
        },
    );
}
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-xl border bg-card p-4"
        data-test="shopping-list"
    >
        <h2 class="font-medium">Shopping list</h2>
        <ul class="flex flex-col">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex items-center gap-2.5 py-1.5"
            >
                <Checkbox
                    :model-value="item.checked_by !== null"
                    :disabled="!canParticipate || busy"
                    :aria-label="`Bought ${item.label}`"
                    @update:model-value="
                        send(tick([eventId, item.id]), {
                            bought: $event === true,
                        })
                    "
                />
                <span
                    :class="[
                        'min-w-0 flex-1 text-sm',
                        item.checked_by
                            ? 'text-muted-foreground line-through'
                            : '',
                    ]"
                >
                    {{ item.label }}
                    <span v-if="item.quantity" class="text-muted-foreground"
                        >· {{ item.quantity }}</span
                    >
                </span>
                <span
                    v-if="item.checked_by"
                    class="shrink-0 text-xs text-muted-foreground"
                    >{{ item.checked_by.name }} got it</span
                >
                <Button
                    v-if="item.can_remove"
                    size="icon"
                    variant="ghost"
                    class="size-7"
                    :aria-label="`Remove ${item.label}`"
                    :disabled="busy"
                    @click="send(destroyShoppingItem([eventId, item.id]))"
                >
                    <X class="size-3.5" />
                </Button>
            </li>
        </ul>
        <p v-if="items.length === 0" class="text-sm text-muted-foreground">
            Nothing to buy yet.
        </p>

        <form v-if="canParticipate" class="flex gap-2" @submit.prevent="add">
            <Input
                v-model="label"
                placeholder="Add to the list"
                aria-label="Item"
                required
                maxlength="120"
            />
            <Input
                v-model="quantity"
                placeholder="Amount"
                aria-label="How much"
                class="w-28 shrink-0"
                maxlength="40"
            />
            <Button type="submit" variant="secondary" :disabled="busy"
                >Add</Button
            >
        </form>
    </section>
</template>
