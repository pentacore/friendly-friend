<script setup lang="ts">
import { Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/EventController';
import EventForm from '@/components/events/EventForm.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/events';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['events/Edit']>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Events', href: index() },
        { title: props.event.title, href: show(props.event.id) },
        { title: 'Edit', href: edit(props.event.id) },
    ],
});

const form = useForm({
    ...props.form,
    description: props.form.description || null,
    location: props.form.location || null,
    location_url: props.form.location_url || null,
    ends_at: props.form.ends_at || null,
});

function submit(): void {
    form.submit(update(props.event.id));
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Heading :title="`Edit ${event.title}`" />

        <EventForm
            :form="form"
            :members="members"
            :exclude-host-id="event.created_by.id"
            submit-label="Save changes"
            @submit="submit"
        >
            <template #actions>
                <Button variant="ghost" as-child>
                    <Link :href="show(event.id)">Cancel</Link>
                </Button>
            </template>
        </EventForm>
    </div>
</template>
