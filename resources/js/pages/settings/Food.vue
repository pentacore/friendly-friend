<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Settings/FoodSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/food-settings';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['settings/Food']>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Food settings', href: edit() }] },
});

const form = useForm({ dietary_notes: props.dietary_notes });
</script>

<template>
    <Head title="Food settings" />

    <h1 class="sr-only">Food settings</h1>

    <form
        class="space-y-6"
        @submit.prevent="form.submit(update(), { preserveScroll: true })"
    >
        <Heading
            variant="small"
            title="Allergies and food preferences"
            description="Write it once. Organisers of events you're going to (or might go to) see it next to your name."
        />
        <div class="grid gap-2">
            <Label for="dietary_notes">Notes</Label>
            <textarea
                id="dietary_notes"
                v-model="form.dietary_notes"
                rows="3"
                maxlength="300"
                placeholder="Vegetarian, allergic to nuts, no coriander please"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            />
            <InputError :message="form.errors.dietary_notes" />
            <p class="text-xs text-muted-foreground">
                Leave empty if you eat anything.
            </p>
        </div>
        <Button type="submit" :disabled="form.processing">Save</Button>
    </form>
</template>
