<script setup lang="ts">
import { Car } from '@lucide/vue';
import { computed } from 'vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import type { PageProps, RsvpStatus } from '@/typefinder';

const props = defineProps<{
    attendees: PageProps['events/Show']['attendees'];
}>();

const groups = computed(() =>
    (
        [
            ['going', 'Going'],
            ['maybe', 'Maybe'],
            ['declined', 'Can’t make it'],
        ] as [RsvpStatus, string][]
    )
        .map(([status, label]) => ({
            status,
            label,
            people: props.attendees.filter((a) => a.status === status),
        }))
        .filter((group) => group.people.length > 0),
);
</script>

<template>
    <section class="flex flex-col gap-4">
        <div
            v-for="group in groups"
            :key="group.status"
            class="flex flex-col gap-2"
        >
            <h3 class="text-sm font-medium text-muted-foreground">
                {{ group.label }} · {{ group.people.length }}
            </h3>
            <ul class="grid gap-1.5 sm:grid-cols-2">
                <li
                    v-for="person in group.people"
                    :key="person.member.id"
                    :class="[
                        'flex items-center gap-2 text-sm',
                        group.status === 'declined'
                            ? 'text-muted-foreground'
                            : '',
                    ]"
                >
                    <MemberAvatar :member="person.member" size="md" />
                    <span class="min-w-0">
                        <span class="block truncate">
                            {{ person.member.name }}
                            <span
                                v-if="person.guests"
                                class="text-muted-foreground"
                                >+{{ person.guests }}</span
                            >
                        </span>
                        <span
                            v-if="person.note"
                            class="block truncate text-xs text-muted-foreground"
                            >{{ person.note }}</span
                        >
                    </span>
                    <Car
                        v-if="person.needs_ride"
                        class="ml-auto size-4 shrink-0 text-muted-foreground"
                        aria-label="Needs a ride"
                    />
                </li>
            </ul>
        </div>
        <p v-if="groups.length === 0" class="text-sm text-muted-foreground">
            Nobody has answered yet.
        </p>
    </section>
</template>
