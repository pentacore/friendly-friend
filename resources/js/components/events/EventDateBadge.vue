<script setup lang="ts">
import { computed } from 'vue';
import { eventDateBadge } from '@/lib/eventDates';

const props = defineProps<{
    startsAt: string;
    timezone: string;
    cancelled?: boolean;
    size?: 'md' | 'lg';
}>();

const badge = computed(() => eventDateBadge(props.startsAt, props.timezone));
</script>

<template>
    <div
        :class="[
            'flex shrink-0 flex-col items-center justify-center rounded-xl border bg-background leading-none',
            size === 'lg' ? 'size-20' : 'size-14',
            cancelled ? 'opacity-50' : '',
        ]"
        aria-hidden="true"
    >
        <span
            class="text-[10px] font-medium tracking-wider text-muted-foreground uppercase"
        >
            {{ badge.month }}
        </span>
        <span
            :class="[
                'font-quote font-bold',
                size === 'lg' ? 'text-3xl' : 'text-xl',
                cancelled ? 'line-through' : '',
            ]"
        >
            {{ badge.day }}
        </span>
        <span class="text-[10px] text-muted-foreground">{{
            badge.weekday
        }}</span>
    </div>
</template>
