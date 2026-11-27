<script setup lang="ts">
import { computed } from 'vue';
import { plural } from '@/lib/statsFormat';

const props = defineProps<{
    cells: { weekday: number; hour: number; count: number }[];
}>();

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const hours = Array.from({ length: 24 }, (_, hour) => hour);

const counts = computed(() => {
    const byKey = new Map<string, number>();

    for (const cell of props.cells) {
        byKey.set(`${cell.weekday}-${cell.hour}`, cell.count);
    }

    return byKey;
});

const max = computed(() => Math.max(1, ...props.cells.map((c) => c.count)));

function count(weekday: number, hour: number): number {
    return counts.value.get(`${weekday}-${hour}`) ?? 0;
}

/** Highlighter ink: never fully transparent for any activity, full at the busiest hour. */
function ink(value: number): string | undefined {
    return value === 0
        ? undefined
        : `opacity: ${0.15 + 0.85 * Math.sqrt(value / max.value)}`;
}

function label(weekday: number, hour: number): string {
    const pad = (h: number) => String(h).padStart(2, '0');

    return `${weekdays[weekday - 1]} ${pad(hour)}:00–${pad((hour + 1) % 24)}:00 · ${plural(count(weekday, hour), 'message', 'messages')}`;
}
</script>

<template>
    <div>
        <div
            class="grid gap-[2px] sm:gap-[3px]"
            style="grid-template-columns: 2rem repeat(24, minmax(0, 1fr))"
            role="img"
            aria-label="Messages by weekday and hour"
        >
            <template v-for="(day, index) in weekdays" :key="day">
                <span
                    class="self-center pr-1 text-xs text-muted-foreground"
                    aria-hidden="true"
                >
                    {{ day }}
                </span>
                <span
                    v-for="hour in hours"
                    :key="hour"
                    class="relative aspect-square rounded-[2px] bg-muted sm:rounded-[3px]"
                    :title="label(index + 1, hour)"
                >
                    <span
                        v-if="count(index + 1, hour) > 0"
                        class="absolute inset-0 rounded-[inherit] bg-highlight"
                        :style="ink(count(index + 1, hour))"
                    />
                </span>
            </template>
            <span />
            <span
                v-for="hour in hours"
                :key="`label-${hour}`"
                class="pt-1 text-[10px] text-muted-foreground tabular-nums"
                aria-hidden="true"
            >
                {{ hour % 6 === 0 ? String(hour).padStart(2, '0') : '' }}
            </span>
        </div>
    </div>
</template>
