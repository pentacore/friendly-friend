<script setup lang="ts">
import { Minus, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    claim,
    destroyBringItem,
    storeBringItem,
} from '@/actions/App/Http/Controllers/EventFoodController';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEventAction } from '@/composables/useEventAction';
import type { Food } from './types';

const props = defineProps<{
    eventId: number;
    items: Food['bring'];
    me: number;
    canEdit: boolean;
    canParticipate: boolean;
}>();

const { busy, send } = useEventAction(['food']);
const label = ref('');
const quantity = ref(1);

function mine(item: Food['bring'][number]): number {
    return item.claims.find((c) => c.member.id === props.me)?.quantity ?? 0;
}

function setClaim(item: Food['bring'][number], amount: number): void {
    send(claim([props.eventId, item.id]), { quantity: Math.max(0, amount) });
}

function add(): void {
    send(
        storeBringItem(props.eventId),
        { label: label.value, quantity_needed: quantity.value },
        () => {
            label.value = '';
            quantity.value = 1;
        },
    );
}

const stillNeeded = computed(
    () => props.items.filter((i) => i.claimed < i.quantity_needed).length,
);
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-xl border bg-card p-4"
        data-test="bring-list"
    >
        <header class="flex items-baseline justify-between gap-2">
            <h2 class="font-medium">Bring list</h2>
            <span v-if="items.length" class="text-xs text-muted-foreground">
                {{
                    stillNeeded === 0
                        ? 'All covered'
                        : `${stillNeeded} still needed`
                }}
            </span>
        </header>

        <ul class="flex flex-col divide-y">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5"
            >
                <div class="min-w-0 flex-1">
                    <p class="text-sm">
                        {{ item.label }}
                        <span
                            :class="[
                                'ml-1 text-xs tabular-nums',
                                item.claimed >= item.quantity_needed
                                    ? 'text-muted-foreground'
                                    : 'font-medium',
                            ]"
                        >
                            {{ item.claimed }} / {{ item.quantity_needed }}
                        </span>
                    </p>
                    <p
                        v-if="item.claims.length"
                        class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span
                            v-for="c in item.claims"
                            :key="c.member.id"
                            class="inline-flex items-center gap-1"
                        >
                            <MemberAvatar :member="c.member" />
                            {{ c.member.name
                            }}<template v-if="c.quantity > 1">
                                ×{{ c.quantity }}</template
                            >
                            <template v-if="c.note"> ({{ c.note }})</template>
                        </span>
                    </p>
                </div>
                <div v-if="canParticipate" class="flex items-center gap-1">
                    <template v-if="mine(item) > 0">
                        <Button
                            size="icon"
                            variant="outline"
                            class="size-8"
                            :disabled="busy"
                            aria-label="Bring one less"
                            @click="setClaim(item, mine(item) - 1)"
                        >
                            <Minus class="size-3.5" />
                        </Button>
                        <span class="w-6 text-center text-sm tabular-nums">{{
                            mine(item)
                        }}</span>
                        <Button
                            size="icon"
                            variant="outline"
                            class="size-8"
                            :disabled="busy"
                            aria-label="Bring one more"
                            @click="setClaim(item, mine(item) + 1)"
                        >
                            <Plus class="size-3.5" />
                        </Button>
                    </template>
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        :disabled="busy"
                        @click="setClaim(item, 1)"
                        >I'll bring it</Button
                    >
                    <Button
                        v-if="canEdit"
                        size="icon"
                        variant="ghost"
                        class="size-8"
                        :aria-label="`Remove ${item.label}`"
                        :disabled="busy"
                        @click="send(destroyBringItem([eventId, item.id]))"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
            </li>
        </ul>
        <p v-if="items.length === 0" class="text-sm text-muted-foreground">
            {{
                canEdit
                    ? 'Add what people should bring: chairs, a speaker, dessert…'
                    : 'Nothing to bring yet.'
            }}
        </p>

        <form v-if="canEdit" class="flex gap-2" @submit.prevent="add">
            <Input
                v-model="label"
                placeholder="Add something to bring"
                aria-label="Item"
                required
                maxlength="120"
            />
            <Input
                v-model.number="quantity"
                type="number"
                min="1"
                max="99"
                class="w-20"
                aria-label="How many"
            />
            <Button type="submit" variant="secondary" :disabled="busy"
                >Add</Button
            >
        </form>
    </section>
</template>
