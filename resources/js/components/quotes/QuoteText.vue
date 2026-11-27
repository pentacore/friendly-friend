<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show as showPerson } from '@/routes/people';
import type { QuoteResource } from '@/typefinder';

const props = withDefaults(
    defineProps<{
        lines: QuoteResource['lines'];
        size?: 'md' | 'lg';
    }>(),
    { size: 'md' },
);

/** One speaker: `"text" - Person`. Several: a small conversation. */
const singleSpeaker = computed(() =>
    props.lines.every(
        (line) => line.speaker_name === props.lines[0]?.speaker_name,
    ),
);

const text = computed(() => props.lines.map((line) => line.text).join('\n'));
</script>

<template>
    <figure class="min-w-0">
        <template v-if="singleSpeaker && lines[0]">
            <blockquote
                class="relative isolate font-quote leading-snug font-medium tracking-tight whitespace-pre-line text-foreground"
                :class="size === 'lg' ? 'text-3xl sm:text-4xl' : 'text-xl'"
            >
                <span
                    aria-hidden="true"
                    class="relative mr-[0.08em] inline-block font-bold text-black before:absolute before:inset-x-[-0.22em] before:top-[0.18em] before:bottom-[0.12em] before:-z-10 before:-rotate-6 before:rounded-[0.18em] before:bg-highlight"
                    >“</span
                >{{ text }}<span aria-hidden="true" class="font-bold">”</span>
            </blockquote>
            <figcaption
                class="mt-3 text-sm font-medium text-muted-foreground"
                :class="size === 'lg' ? 'text-base' : ''"
            >
                -
                <Link
                    v-if="lines[0].person"
                    :href="showPerson(lines[0].person.slug)"
                    class="text-foreground underline-offset-4 hover:underline"
                >
                    {{ lines[0].speaker_name }}
                </Link>
                <span v-else class="text-foreground">{{
                    lines[0].speaker_name
                }}</span>
            </figcaption>
        </template>

        <dl
            v-else
            class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2"
            :class="size === 'lg' ? 'text-2xl' : 'text-lg'"
        >
            <template v-for="(line, index) in lines" :key="index">
                <dt
                    class="pt-[0.2em] text-right text-sm font-semibold whitespace-nowrap text-muted-foreground"
                >
                    <Link
                        v-if="line.person"
                        :href="showPerson(line.person.slug)"
                        class="underline-offset-4 hover:text-foreground hover:underline"
                    >
                        {{ line.speaker_name }}
                    </Link>
                    <template v-else>{{ line.speaker_name }}</template>
                </dt>
                <dd
                    class="font-quote leading-snug font-medium tracking-tight whitespace-pre-line text-foreground"
                >
                    {{ line.text }}
                </dd>
            </template>
        </dl>
    </figure>
</template>
