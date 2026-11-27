<script setup lang="ts">
import { ArrowRight, Check, Trash2, Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroyExpense,
    destroyPayment,
    storeExpense,
    storePayment,
} from '@/actions/App/Http/Controllers/EventCostController';
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEventAction } from '@/composables/useEventAction';
import { formatMoney } from '@/lib/money';
import type { EventResource, PageProps, SplitMode } from '@/typefinder';

type Costs = PageProps['events/Show']['costs'];

const props = defineProps<{
    event: EventResource;
    costs: Costs;
    me: number;
    errors: Record<string, string>;
}>();

const { busy, send } = useEventAction(['costs', 'errors']);
const money = (minor: number) => formatMoney(minor, props.costs.currency);

const draft = ref({
    description: '',
    amount: '',
    paid_by: props.me,
    split_mode: 'equal' as SplitMode,
    people: props.costs.people.map((p) => p.id),
    exact: {} as Record<number, string>,
});
const adding = ref(false);

function togglePerson(id: number, on: boolean | 'indeterminate'): void {
    draft.value.people =
        on === true
            ? [...new Set([...draft.value.people, id])]
            : draft.value.people.filter((p) => p !== id);
}

function addExpense(): void {
    send(storeExpense(props.event.id), { ...draft.value }, () => {
        if (Object.keys(props.errors).length === 0) {
            adding.value = false;
            draft.value = {
                ...draft.value,
                description: '',
                amount: '',
                exact: {},
            };
        }
    });
}

function markPaid(transfer: Costs['settle_up'][number]): void {
    send(storePayment(props.event.id), {
        from: transfer.from.id,
        to: transfer.to.id,
        amount: (transfer.amount / 100).toFixed(2),
    });
}

const myBalance = computed(
    () =>
        props.costs.balances.find((b) => b.member.id === props.me)?.balance ??
        0,
);

const field =
    'h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
</script>

<template>
    <div
        class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]"
        data-test="costs"
    >
        <div class="flex flex-col gap-4">
            <section class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <header class="flex items-baseline justify-between gap-2">
                    <h2 class="font-medium">Expenses</h2>
                    <span class="text-sm text-muted-foreground"
                        >{{ money(costs.total) }} in total</span
                    >
                </header>

                <ul v-if="costs.expenses.length" class="flex flex-col divide-y">
                    <li
                        v-for="expense in costs.expenses"
                        :key="expense.id"
                        class="flex items-start gap-3 py-2.5"
                    >
                        <MemberAvatar :member="expense.paid_by" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <span class="font-medium">{{
                                    expense.description
                                }}</span>
                                <span class="text-muted-foreground">
                                    · {{ expense.paid_by.name }} paid</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                <template
                                    v-if="expense.split_mode === 'attendees'"
                                >
                                    Split among everyone going, now
                                    {{
                                        expense.shares
                                            .map(
                                                (s) =>
                                                    `${s.member.name} ${money(s.amount)}`,
                                            )
                                            .join(', ')
                                    }}
                                </template>
                                <template
                                    v-else-if="expense.split_mode === 'equal'"
                                >
                                    Split {{ expense.shares.length }}
                                    {{
                                        expense.shares.length === 1
                                            ? 'way'
                                            : 'ways'
                                    }}:
                                    {{
                                        expense.shares
                                            .map((s) => s.member.name)
                                            .join(', ')
                                    }}
                                </template>
                                <template v-else>
                                    {{
                                        expense.shares
                                            .map(
                                                (s) =>
                                                    `${s.member.name} ${money(s.amount)}`,
                                            )
                                            .join(', ')
                                    }}
                                </template>
                            </p>
                        </div>
                        <span class="text-sm font-medium tabular-nums">{{
                            money(expense.amount)
                        }}</span>
                        <Button
                            v-if="expense.can_remove"
                            size="icon"
                            variant="ghost"
                            class="size-7"
                            :aria-label="`Remove ${expense.description}`"
                            :disabled="busy"
                            @click="
                                send(destroyExpense([event.id, expense.id]))
                            "
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    Nothing spent yet. Paid for something? Add it so it can be
                    split.
                </p>

                <Button
                    v-if="!adding"
                    variant="outline"
                    class="self-start"
                    @click="adding = true"
                    >Add an expense</Button
                >
                <form
                    v-else
                    class="grid gap-3 rounded-lg border p-3"
                    @submit.prevent="addExpense"
                >
                    <div class="grid gap-3 sm:grid-cols-[1fr_8rem]">
                        <div class="grid gap-1.5">
                            <Label for="expense-description">What</Label>
                            <Input
                                id="expense-description"
                                v-model="draft.description"
                                placeholder="Crayfish, 3 kg"
                                required
                                maxlength="120"
                            />
                            <InputError :message="errors.description" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="expense-amount"
                                >Amount ({{ costs.currency }})</Label
                            >
                            <Input
                                id="expense-amount"
                                v-model="draft.amount"
                                inputmode="decimal"
                                placeholder="450"
                                required
                            />
                            <InputError :message="errors.amount" />
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="expense-paid-by">Paid by</Label>
                            <select
                                id="expense-paid-by"
                                v-model="draft.paid_by"
                                :class="field"
                            >
                                <option
                                    v-for="p in costs.people"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{
                                        p.id === me ? `${p.name} (you)` : p.name
                                    }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="expense-split">Split</Label>
                            <select
                                id="expense-split"
                                v-model="draft.split_mode"
                                :class="field"
                            >
                                <option value="equal">Equally</option>
                                <option value="exact">Exact amounts</option>
                                <option value="attendees">
                                    Everyone going (updates as people answer)
                                </option>
                            </select>
                        </div>
                    </div>
                    <fieldset
                        v-if="draft.split_mode !== 'attendees'"
                        class="grid gap-1.5"
                    >
                        <legend class="mb-1 text-sm font-medium">
                            Between
                        </legend>
                        <div class="grid gap-1 sm:grid-cols-2">
                            <div
                                v-for="p in costs.people"
                                :key="p.id"
                                class="flex items-center gap-2 text-sm"
                            >
                                <label
                                    class="flex min-w-0 flex-1 items-center gap-2"
                                >
                                    <Checkbox
                                        :model-value="
                                            draft.people.includes(p.id)
                                        "
                                        @update:model-value="
                                            togglePerson(p.id, $event)
                                        "
                                    />
                                    <span class="truncate">{{ p.name }}</span>
                                </label>
                                <Input
                                    v-if="
                                        draft.split_mode === 'exact' &&
                                        draft.people.includes(p.id)
                                    "
                                    v-model="draft.exact[p.id]"
                                    inputmode="decimal"
                                    class="h-8 w-24"
                                    :aria-label="`${p.name}'s part`"
                                />
                            </div>
                        </div>
                        <InputError :message="errors.people || errors.exact" />
                    </fieldset>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="adding = false"
                            >Never mind</Button
                        >
                        <Button type="submit" :disabled="busy"
                            >Add expense</Button
                        >
                    </div>
                </form>
            </section>

            <section
                v-if="costs.payments.length"
                class="flex flex-col gap-2 rounded-xl border bg-card p-4"
            >
                <h2 class="font-medium">Paid back</h2>
                <ul class="flex flex-col gap-1.5">
                    <li
                        v-for="payment in costs.payments"
                        :key="payment.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Check class="size-4 text-muted-foreground" />
                        <span class="min-w-0 flex-1 truncate"
                            >{{ payment.from.name }} paid
                            {{ payment.to.name }}</span
                        >
                        <span class="tabular-nums">{{
                            money(payment.amount)
                        }}</span>
                        <Button
                            v-if="payment.can_remove"
                            size="icon"
                            variant="ghost"
                            class="size-7"
                            aria-label="Undo this payment"
                            :disabled="busy"
                            @click="
                                send(destroyPayment([event.id, payment.id]))
                            "
                        >
                            <Undo2 class="size-3.5" />
                        </Button>
                    </li>
                </ul>
            </section>
        </div>

        <aside class="order-first flex flex-col gap-4 lg:order-none">
            <section
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                data-test="settle-up"
            >
                <h2 class="font-medium">Settle up</h2>
                <p v-if="costs.total > 0" class="text-sm text-muted-foreground">
                    <template v-if="myBalance > 0"
                        >You're owed {{ money(myBalance) }}.</template
                    >
                    <template v-else-if="myBalance < 0"
                        >You owe {{ money(-myBalance) }}.</template
                    >
                    <template v-else>You're square.</template>
                </p>
                <ul v-if="costs.settle_up.length" class="flex flex-col gap-2">
                    <li
                        v-for="(transfer, i) in costs.settle_up"
                        :key="i"
                        class="flex flex-col gap-2 rounded-lg border p-3"
                    >
                        <p class="flex items-center gap-2 text-sm">
                            <MemberAvatar :member="transfer.from" />
                            <span class="truncate">{{
                                transfer.from.name
                            }}</span>
                            <ArrowRight
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            <MemberAvatar :member="transfer.to" />
                            <span class="truncate">{{ transfer.to.name }}</span>
                        </p>
                        <div class="flex items-center justify-between gap-2">
                            <span
                                class="font-quote text-lg font-bold tabular-nums"
                                >{{ money(transfer.amount) }}</span
                            >
                            <Button
                                size="sm"
                                variant="secondary"
                                :disabled="busy"
                                @click="markPaid(transfer)"
                                >Mark as paid</Button
                            >
                        </div>
                    </li>
                </ul>
                <p v-else-if="costs.total > 0" class="text-sm">
                    Everyone's square. 🎉
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Add expenses and this works out who pays whom.
                </p>
            </section>

            <section
                v-if="costs.balances.length"
                class="flex flex-col gap-2 rounded-xl border bg-card p-4"
            >
                <h2 class="font-medium">Balances</h2>
                <table class="w-full text-sm">
                    <thead class="text-xs text-muted-foreground">
                        <tr>
                            <th class="pb-1 text-left font-normal">Who</th>
                            <th class="pb-1 text-right font-normal">Paid</th>
                            <th class="pb-1 text-right font-normal">Share</th>
                            <th class="pb-1 text-right font-normal">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in costs.balances" :key="row.member.id">
                            <td class="truncate py-1">{{ row.member.name }}</td>
                            <td
                                class="py-1 text-right text-muted-foreground tabular-nums"
                            >
                                {{ money(row.paid) }}
                            </td>
                            <td
                                class="py-1 text-right text-muted-foreground tabular-nums"
                            >
                                {{ money(row.share) }}
                            </td>
                            <td
                                :class="[
                                    'py-1 text-right font-medium tabular-nums',
                                    row.balance < 0 ? 'text-destructive' : '',
                                ]"
                            >
                                {{ row.balance > 0 ? '+' : ''
                                }}{{ money(row.balance) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </aside>
    </div>
</template>
