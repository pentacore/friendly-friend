<script setup lang="ts" generic="T">
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    description?: string;
    rows: T[];
    value: (row: T) => number;
    empty?: string;
}>();

defineSlots<{
    label(props: { row: T }): unknown;
    value(props: { row: T }): unknown;
}>();

const max = computed(() => Math.max(1, ...props.rows.map(props.value)));
</script>

<template>
    <section class="flex flex-col gap-3 rounded-xl border bg-card p-4">
        <header>
            <h2 class="font-medium">{{ title }}</h2>
            <p v-if="description" class="text-xs text-muted-foreground">
                {{ description }}
            </p>
        </header>

        <ol v-if="rows.length" class="flex flex-col gap-1">
            <li
                v-for="(row, index) in rows"
                :key="index"
                class="relative flex items-center gap-2.5 overflow-hidden rounded-md px-2 py-1.5 text-sm"
            >
                <span
                    class="absolute inset-y-0 left-0 rounded-md bg-foreground/[0.06]"
                    :style="{ width: `${(value(row) / max) * 100}%` }"
                    aria-hidden="true"
                />
                <span
                    class="relative w-4 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                >
                    {{ index + 1 }}
                </span>
                <span class="relative flex min-w-0 flex-1 items-center gap-2">
                    <slot name="label" :row="row" />
                </span>
                <span
                    class="relative shrink-0 text-muted-foreground tabular-nums"
                >
                    <slot name="value" :row="row" />
                </span>
            </li>
        </ol>
        <p v-else class="py-4 text-center text-sm text-muted-foreground">
            {{ empty ?? 'Nothing yet.' }}
        </p>
    </section>
</template>
