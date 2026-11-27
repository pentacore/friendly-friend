<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { update } from '@/actions/App/Http/Controllers/QuoteController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { QuoteResource, UpdateQuoteRequest } from '@/typefinder';

const props = defineProps<{ quote: QuoteResource }>();
const emit = defineEmits<{ done: [] }>();

const form = useForm<UpdateQuoteRequest>({
    lines: props.quote.lines.map((line) => ({
        speaker: line.speaker_name,
        text: line.text,
    })),
    context: props.quote.context,
    said_at: props.quote.said_at?.slice(0, 10) ?? null,
    said_at_precision: props.quote.said_at_precision,
});

function addLine(): void {
    form.lines.push({ speaker: '', text: '' });
}

function removeLine(index: number): void {
    form.lines.splice(index, 1);
}

function save(): void {
    form.submit(update(props.quote.id), {
        preserveScroll: true,
        onSuccess: () => emit('done'),
    });
}
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="save">
        <fieldset class="flex flex-col gap-3">
            <legend class="mb-2 text-sm font-semibold">Who said what</legend>
            <div
                v-for="(line, index) in form.lines"
                :key="index"
                class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-start gap-2"
            >
                <Input
                    v-model="line.speaker"
                    :aria-label="`Speaker of line ${index + 1}`"
                    placeholder="Name"
                />
                <textarea
                    v-model="line.text"
                    :aria-label="`Line ${index + 1}`"
                    rows="2"
                    class="min-h-9 rounded-md border border-input bg-background px-3 py-2 font-quote text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="form.lines.length === 1"
                    :aria-label="`Remove line ${index + 1}`"
                    @click="removeLine(index)"
                >
                    <X class="size-4" />
                </Button>
                <InputError
                    class="col-span-3"
                    :message="
                        form.errors[`lines.${index}.speaker`] ??
                        form.errors[`lines.${index}.text`]
                    "
                />
            </div>
            <InputError :message="form.errors.lines" />
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="self-start"
                @click="addLine"
            >
                <Plus class="size-4" />
                Add a line
            </Button>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
            <div class="grid gap-2">
                <Label for="quote-context">Context</Label>
                <Input
                    id="quote-context"
                    :model-value="form.context ?? ''"
                    placeholder="Where or why it was said"
                    @update:model-value="
                        (value) => (form.context = String(value) || null)
                    "
                />
                <InputError :message="form.errors.context" />
            </div>
            <div class="grid gap-2">
                <Label for="quote-said-at">Said on</Label>
                <Input
                    id="quote-said-at"
                    :model-value="form.said_at ?? ''"
                    type="date"
                    @update:model-value="
                        (value) => (form.said_at = String(value) || null)
                    "
                />
                <InputError :message="form.errors.said_at" />
            </div>
        </div>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing || !form.isDirty">
                <Spinner v-if="form.processing" />
                Save changes
            </Button>
            <Button type="button" variant="ghost" @click="emit('done')">
                Cancel
            </Button>
        </div>
    </form>
</template>
