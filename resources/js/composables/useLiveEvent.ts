import { router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import type { BroadcastEvents } from '@/typefinder';

type EventChanged = BroadcastEvents['event.changed'];
export type EventSection = EventChanged['section'];

/**
 * Reload the props of whichever part of an event someone else just
 * changed (Reverb, `events.{id}`). Call once in the event page.
 */
export function useLiveEvent(
    eventId: number,
    propsBySection: Record<EventSection, string[]>,
): void {
    useEcho<EventChanged>(`events.${eventId}`, '.event.changed', (change) => {
        router.reload({ only: propsBySection[change.section] });
    });
}
