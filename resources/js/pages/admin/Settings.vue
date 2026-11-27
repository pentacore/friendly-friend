<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Admin/SettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/admin/settings';
import type { PageProps } from '@/typefinder';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: index() }],
    },
});

const props = defineProps<PageProps['admin/Settings']>();

const form = useForm<Record<string, number>>(
    Object.fromEntries(
        props.settings.map((setting) => [setting.key, setting.value]),
    ),
);

/** The settings in the order they come, grouped under their heading. */
const groups = computed(() => {
    const byGroup = new Map<string, PageProps['admin/Settings']['settings']>();

    for (const setting of props.settings) {
        byGroup.set(setting.group, [
            ...(byGroup.get(setting.group) ?? []),
            setting,
        ]);
    }

    return [...byGroup.entries()].map(([title, settings]) => ({
        title,
        settings,
    }));
});

function save(): void {
    form.submit(update(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Settings" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Settings"
            description="Limits for the whole site and the bot. Each one starts at its default; change it here and it applies from the next run."
        />

        <form class="flex flex-col gap-6" @submit.prevent="save">
            <div class="grid gap-6 xl:grid-cols-2">
                <Card
                    v-for="group in groups"
                    :key="group.title"
                    :data-test="`settings-${group.title}`"
                >
                    <CardHeader>
                        <CardTitle>{{ group.title }}</CardTitle>
                    </CardHeader>

                    <CardContent class="grid gap-6">
                        <div
                            v-for="setting in group.settings"
                            :key="setting.key"
                            class="grid gap-2"
                        >
                            <Label :for="setting.key">{{
                                setting.label
                            }}</Label>
                            <div class="flex items-center gap-3">
                                <Input
                                    :id="setting.key"
                                    v-model.number="form[setting.key]"
                                    type="number"
                                    inputmode="numeric"
                                    :min="setting.min"
                                    :max="setting.max"
                                    class="w-28"
                                    :aria-describedby="`${setting.key}-help`"
                                />
                                <span class="text-sm text-muted-foreground">
                                    {{ setting.unit }}
                                </span>
                            </div>
                            <p
                                :id="`${setting.key}-help`"
                                class="text-sm text-muted-foreground"
                            >
                                {{ setting.help }} Default
                                {{ setting.default }}, {{ setting.min }}–{{
                                    setting.max
                                }}.
                            </p>
                            <InputError :message="form.errors[setting.key]" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="!form.isDirty || form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Save settings
                </Button>
            </div>
        </form>
    </div>
</template>
