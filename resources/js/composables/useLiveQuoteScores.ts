import { useEcho } from '@laravel/echo-vue';
import type { ComputedRef, InjectionKey } from 'vue';
import { computed, inject, provide, reactive } from 'vue';
import type { BroadcastEvents } from '@/typefinder';

type ScoreChanged = BroadcastEvents['quote.score-changed'];

const liveScoresKey: InjectionKey<Map<number, number>> = Symbol('liveScores');

/**
 * Listen once per server for live score changes (Reverb) and share them
 * with every vote button on the page. Call in a page's <script setup>.
 */
export function provideLiveQuoteScores(guildIds: number[]): void {
    const scores = reactive(new Map<number, number>());

    // Live updates are browser-only: during the server render there's no socket to open.
    for (const guildId of import.meta.env.SSR ? [] : new Set(guildIds)) {
        useEcho<ScoreChanged>(
            `guilds.${guildId}.quotes`,
            '.quote.score-changed',
            (event) => scores.set(event.quote_id, event.score),
        );
    }

    provide(liveScoresKey, scores);
}

/** The latest live score of a quote, if one arrived since the page loaded. */
export function useLiveQuoteScore(
    quoteId: () => number,
): ComputedRef<number | undefined> {
    const scores = inject(liveScoresKey, undefined);

    return computed(() => scores?.get(quoteId()));
}
