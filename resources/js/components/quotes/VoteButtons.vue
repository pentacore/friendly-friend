<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ThumbsDown, ThumbsUp } from '@lucide/vue';
import { ref, watch } from 'vue';
import { vote } from '@/routes/quotes';
import { useLiveQuoteScore } from '@/composables/useLiveQuoteScores';
import type { QuoteResource, VoteResultResource } from '@/typefinder';

const props = defineProps<{ quote: QuoteResource }>();

const score = ref(props.quote.score);
const myVote = ref(props.quote.my_vote);

const http = useHttp<{ value: number }, VoteResultResource>({ value: 0 });

// Votes from other people arrive live; ours win while a request is running.
const liveScore = useLiveQuoteScore(() => props.quote.id);
watch(liveScore, (value) => {
    if (value !== undefined && !http.processing) {
        score.value = value;
    }
});

async function cast(value: 1 | -1): Promise<void> {
    const before = { score: score.value, myVote: myVote.value };
    const next = myVote.value === value ? 0 : value;

    // Optimistic: show the result straight away, roll back if it fails.
    score.value += next - myVote.value;
    myVote.value = next;

    http.value = value;

    try {
        const result = await http.submit(vote(props.quote.id));
        score.value = result.score;
        myVote.value = result.my_vote;
    } catch {
        score.value = before.score;
        myVote.value = before.myVote;
    }
}
</script>

<template>
    <div
        class="inline-flex items-center rounded-full border bg-background text-xs"
        role="group"
        :aria-label="`Score ${score}`"
    >
        <button
            type="button"
            class="inline-flex size-7 items-center justify-center rounded-full transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :class="myVote === 1 ? 'text-foreground' : 'text-muted-foreground'"
            :aria-pressed="myVote === 1"
            aria-label="Funny"
            @click="cast(1)"
        >
            <ThumbsUp
                class="size-3.5"
                :class="myVote === 1 ? 'fill-highlight' : ''"
            />
        </button>
        <span
            class="min-w-6 text-center font-semibold tabular-nums"
            data-test="score"
            >{{ score }}</span
        >
        <button
            type="button"
            class="inline-flex size-7 items-center justify-center rounded-full transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :class="myVote === -1 ? 'text-foreground' : 'text-muted-foreground'"
            :aria-pressed="myVote === -1"
            aria-label="Not funny"
            @click="cast(-1)"
        >
            <ThumbsDown
                class="size-3.5"
                :class="myVote === -1 ? 'fill-muted-foreground' : ''"
            />
        </button>
    </div>
</template>
