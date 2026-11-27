<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, Users } from '@lucide/vue';
import EventDateBadge from '@/components/events/EventDateBadge.vue';
import { Badge } from '@/components/ui/badge';
import { formatEventWhen, relativeToNow } from '@/lib/eventDates';
import { show } from '@/routes/events';
import type { EventResource } from '@/typefinder';

defineProps<{ event: EventResource; showServer?: boolean }>();

const answer = {
    going: 'You’re going',
    maybe: 'You might go',
    declined: 'You can’t make it',
} as const;
</script>

<template>
    <Link
        :href="show(event.id)"
        class="flex gap-4 rounded-xl border bg-card p-4 transition-colors hover:bg-accent/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        :data-test="`event-${event.id}`"
    >
        <EventDateBadge
            :starts-at="event.starts_at"
            :timezone="event.timezone"
            :cancelled="event.status === 'cancelled'"
        />
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="truncate font-medium">{{ event.title }}</h3>
                <Badge
                    v-if="event.status === 'cancelled'"
                    variant="destructive"
                >
                    Cancelled
                </Badge>
                <Badge v-else-if="event.my_rsvp" variant="secondary">
                    {{ answer[event.my_rsvp.status] }}
                </Badge>
            </div>
            <p class="text-sm text-muted-foreground">
                {{
                    formatEventWhen(
                        event.starts_at,
                        event.ends_at,
                        event.timezone,
                    )
                }}
                · {{ relativeToNow(event.starts_at) }}
            </p>
            <p
                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
            >
                <span
                    v-if="event.location"
                    class="inline-flex items-center gap-1"
                >
                    <MapPin class="size-3.5" />{{ event.location }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <Users class="size-3.5" />{{ event.counts.going
                    }}<template v-if="event.counts.guests">
                        + {{ event.counts.guests }}</template
                    >
                    going<template v-if="event.counts.maybe">
                        · {{ event.counts.maybe }} maybe</template
                    >
                </span>
                <span v-if="showServer">{{ event.guild.name }}</span>
                <span
                    v-if="
                        event.rsvp_by &&
                        !event.rsvp_closed &&
                        event.status === 'planned' &&
                        (!event.my_rsvp || event.my_rsvp.status === 'maybe')
                    "
                    class="font-medium text-foreground"
                >
                    Answer by
                    {{ formatEventWhen(event.rsvp_by, null, event.timezone) }}
                </span>
            </p>
        </div>
    </Link>
</template>
