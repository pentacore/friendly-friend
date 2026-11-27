<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import ActivityHeatmap from '@/components/stats/ActivityHeatmap.vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import MemberName from '@/components/stats/MemberName.vue';
import StatsBoard from '@/components/stats/StatsBoard.vue';
import { Button } from '@/components/ui/button';
import { formatDuration, formatNumber, plural } from '@/lib/statsFormat';
import { stats } from '@/routes';
import { edit as privacySettings } from '@/routes/privacy';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['stats/Index']>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stats', href: stats() }] },
});

const periods = [
    { days: 7, label: 'Week' },
    { days: 30, label: 'Month' },
    { days: 365, label: 'Year' },
];

const guild = ref<number | null>(props.filters.guild);

function show(days: number, guildId: number | null): void {
    router.visit(
        stats({
            query: {
                guild: guildId ?? undefined,
                days: days === 30 ? undefined : days,
            },
        }),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const periodText = computed(() =>
    props.filters.days === 365
        ? 'the last year'
        : `the last ${props.filters.days} days`,
);

const nothingYet = computed(
    () =>
        props.totals.messages === 0 &&
        props.totals.voice_seconds === 0 &&
        props.totals.reactions === 0,
);
</script>

<template>
    <Head title="Stats" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Stats"
                description="Who's around, when, and what they're up to."
            />

            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-if="guilds.length > 1"
                    v-model="guild"
                    aria-label="Server"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    @change="show(filters.days, guild)"
                >
                    <option :value="null">All servers</option>
                    <option v-for="g in guilds" :key="g.id" :value="g.id">
                        {{ g.name }}
                    </option>
                </select>
                <div
                    class="inline-flex rounded-md border p-0.5"
                    role="group"
                    aria-label="Period"
                >
                    <Button
                        v-for="period in periods"
                        :key="period.days"
                        size="sm"
                        :variant="
                            filters.days === period.days ? 'secondary' : 'ghost'
                        "
                        class="h-8"
                        :aria-pressed="filters.days === period.days"
                        @click="show(period.days, filters.guild)"
                    >
                        {{ period.label }}
                    </Button>
                </div>
            </div>
        </div>

        <p
            v-if="nothingYet"
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            Nothing counted in {{ periodText }}. The bot counts messages,
            reactions, voice time and games in enabled servers while it's
            running.
        </p>

        <template v-else>
            <p class="max-w-3xl text-lg leading-relaxed text-pretty">
                In {{ periodText }}:
                <strong class="font-semibold">{{
                    plural(totals.messages, 'message', 'messages')
                }}</strong
                >,
                <strong class="font-semibold">{{
                    formatDuration(totals.voice_seconds)
                }}</strong>
                in voice and
                <strong class="font-semibold">{{
                    plural(totals.reactions, 'reaction', 'reactions')
                }}</strong>
                from {{ plural(totals.members, 'person', 'people') }}.
            </p>

            <section class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <header>
                    <h2 class="font-medium">When it's busy</h2>
                    <p class="text-xs text-muted-foreground">
                        Messages by weekday and hour. Hover a square for the
                        count.
                    </p>
                </header>
                <ActivityHeatmap :cells="heatmap" />
            </section>

            <div class="grid gap-4 md:grid-cols-2">
                <StatsBoard
                    title="Most messages"
                    :rows="messages"
                    :value="(row) => row.total"
                >
                    <template #label="{ row }">
                        <MemberAvatar :member="row.member" />
                        <MemberName :member="row.member" />
                    </template>
                    <template #value="{ row }">
                        {{ formatNumber(row.total) }}
                    </template>
                </StatsBoard>

                <StatsBoard
                    title="Most time in voice"
                    :rows="voice"
                    :value="(row) => row.total"
                    empty="Nobody's been in voice."
                >
                    <template #label="{ row }">
                        <MemberAvatar :member="row.member" />
                        <MemberName :member="row.member" />
                    </template>
                    <template #value="{ row }">
                        {{ formatDuration(row.total) }}
                    </template>
                </StatsBoard>

                <StatsBoard
                    title="Time together"
                    description="Two people in the same voice channel at once."
                    :rows="voice_pairs"
                    :value="(row) => row.total"
                    empty="Nobody's shared a voice channel yet."
                >
                    <template #label="{ row }">
                        <span class="flex -space-x-2">
                            <MemberAvatar :member="row.first" />
                            <MemberAvatar :member="row.second" />
                        </span>
                        <span class="flex min-w-0 gap-1">
                            <MemberName :member="row.first" />
                            <span class="text-muted-foreground">&amp;</span>
                            <MemberName :member="row.second" />
                        </span>
                    </template>
                    <template #value="{ row }">
                        {{ formatDuration(row.total) }}
                    </template>
                </StatsBoard>

                <StatsBoard
                    title="Most played"
                    :rows="games"
                    :value="(row) => row.total"
                    empty="No games seen yet."
                >
                    <template #label="{ row }">
                        <span class="truncate">{{ row.name }}</span>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ plural(row.players, 'player', 'players') }}
                        </span>
                    </template>
                    <template #value="{ row }">
                        {{ formatDuration(row.total) }}
                    </template>
                </StatsBoard>

                <StatsBoard
                    title="Favourite reactions"
                    :rows="emoji"
                    :value="(row) => row.total"
                    empty="No reactions yet."
                >
                    <template #label="{ row }">
                        <img
                            v-if="row.image_url"
                            :src="row.image_url"
                            :alt="row.name"
                            class="size-6 object-contain"
                        />
                        <span v-else class="text-xl leading-none">{{
                            row.name
                        }}</span>
                        <span
                            v-if="row.image_url"
                            class="truncate text-muted-foreground"
                            >:{{ row.name }}:</span
                        >
                    </template>
                    <template #value="{ row }">
                        {{ formatNumber(row.total) }}
                    </template>
                </StatsBoard>

                <StatsBoard
                    title="Who reacts to whom"
                    description="Reactions on someone else's messages."
                    :rows="reaction_pairs"
                    :value="(row) => row.total"
                    empty="No reactions yet."
                >
                    <template #label="{ row }">
                        <MemberAvatar :member="row.first" />
                        <span class="flex min-w-0 items-center gap-1">
                            <MemberName :member="row.first" />
                            <span class="text-muted-foreground">→</span>
                            <MemberName :member="row.second" />
                        </span>
                    </template>
                    <template #value="{ row }">
                        {{ formatNumber(row.total) }}
                    </template>
                </StatsBoard>

                <StatsBoard
                    title="Most reactions given"
                    :rows="reactors"
                    :value="(row) => row.total"
                    empty="No reactions yet."
                >
                    <template #label="{ row }">
                        <MemberAvatar :member="row.member" />
                        <MemberName :member="row.member" />
                    </template>
                    <template #value="{ row }">
                        {{ formatNumber(row.total) }}
                    </template>
                </StatsBoard>
            </div>
        </template>

        <p class="text-xs text-muted-foreground">
            Message text is never stored, only counts. People who opted out
            aren't counted and don't appear here.
            <Link
                :href="privacySettings()"
                class="underline underline-offset-4 hover:text-foreground"
                >Your privacy settings</Link
            >
        </p>
    </div>
</template>
