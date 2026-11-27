<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { store } from '@/actions/App/Http/Controllers/EventController';
import EventForm from '@/components/events/EventForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/events';
import type { PageProps, SaveEventRequest } from '@/typefinder';

const props = defineProps<PageProps['events/Create']>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Events', href: index() },
            { title: 'New event', href: create() },
        ],
    },
});

const form = useForm<SaveEventRequest>({
    guild_id: props.guilds[0]?.id ?? 0,
    title: '',
    description: null,
    location: null,
    location_url: null,
    starts_at: '',
    ends_at: null,
    rsvp_by: null,
    date_options: null,
    timezone: props.default_timezone,
    host_ids: [],
});

const members = computed(() => props.members[form.guild_id] ?? []);

function submit(): void {
    form.submit(store());
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="New event"
            description="The basics now; food, rides and costs on the event page."
        />

        <EventForm
            :form="form"
            :members="members"
            submit-label="Create event"
            allow-date-poll
            @submit="submit"
        >
            <template v-if="guilds.length > 1" #before>
                <div class="grid gap-2">
                    <Label for="guild">Server</Label>
                    <select
                        id="guild"
                        v-model="form.guild_id"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        @change="form.host_ids = []"
                    >
                        <option v-for="g in guilds" :key="g.id" :value="g.id">
                            {{ g.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.guild_id" />
                </div>
            </template>
        </EventForm>
    </div>
</template>
