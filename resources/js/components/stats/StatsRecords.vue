<script setup lang="ts">
import { computed } from 'vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import MemberName from '@/components/stats/MemberName.vue';
import { displayLocale, displayTimeZone } from '@/lib/locale';
import { formatDuration, formatNumber, plural } from '@/lib/statsFormat';
import type { PageProps } from '@/typefinder';

const props = defineProps<{ records: PageProps['stats/Index']['records'] }>();

/** A plain YYYY-MM-DD as "Fri 20 Nov" (no timezone shift). */
function day(value: string): string {
    return new Intl.DateTimeFormat(displayLocale(), {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function moment(iso: string): string {
    return new Intl.DateTimeFormat(displayLocale(), {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: displayTimeZone(),
    }).format(new Date(iso));
}

const tiles = computed(() => {
    const { longest_voice, busiest_day, longest_streak } = props.records;

    return [
        longest_voice && {
            key: 'voice',
            title: 'Longest voice session',
            value: formatDuration(longest_voice.seconds),
            member: longest_voice.member,
            when: `from ${moment(longest_voice.started_at)}`,
        },
        busiest_day && {
            key: 'day',
            title: 'Most messages in a day',
            value: formatNumber(busiest_day.total),
            member: busiest_day.member,
            when: `on ${day(busiest_day.day)}`,
        },
        longest_streak && {
            key: 'streak',
            title: 'Longest streak',
            value: plural(longest_streak.days, 'day', 'days'),
            member: longest_streak.member,
            when: `of messages, until ${day(longest_streak.ended_on)}`,
        },
    ].filter((tile) => tile !== null);
});
</script>

<template>
    <section v-if="tiles.length" aria-labelledby="records-heading">
        <h2 id="records-heading" class="sr-only">Records</h2>
        <ul class="grid gap-4 sm:grid-cols-3">
            <li
                v-for="tile in tiles"
                :key="tile.key"
                class="flex flex-col gap-1 rounded-xl border bg-card p-4"
            >
                <span class="text-xs text-muted-foreground">
                    {{ tile.title }}
                </span>
                <span class="text-2xl font-semibold tabular-nums">
                    {{ tile.value }}
                </span>
                <span class="flex min-w-0 items-center gap-2 text-sm">
                    <MemberAvatar :member="tile.member" />
                    <MemberName :member="tile.member" />
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ tile.when }}
                </span>
            </li>
        </ul>
    </section>
</template>
