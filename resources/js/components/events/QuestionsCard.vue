<script setup lang="ts">
import { Plus, Trash2, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/EventQuestionController';
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/stats/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEventAction } from '@/composables/useEventAction';
import type { EventResource, PageProps, QuestionKind } from '@/typefinder';

type Question = PageProps['events/Show']['questions'][number];

const props = defineProps<{
    event: EventResource;
    questions: Question[];
    errors: Record<string, string>;
}>();

const { busy, send } = useEventAction(['questions', 'errors']);

const kinds: Record<QuestionKind, string> = {
    yes_no: 'Yes or no',
    choice: 'Pick one',
    text: 'Free text',
};
const adding = ref(false);
const draft = ref({
    prompt: '',
    kind: 'yes_no' as QuestionKind,
    options: ['', ''],
});

function label(question: Question, value: string): string {
    return question.kind === 'yes_no'
        ? value === 'yes'
            ? 'Yes'
            : 'No'
        : value;
}

function max(question: Question): number {
    return Math.max(1, ...question.counts.map((c) => c.total));
}

function add(): void {
    send(
        store(props.event.id),
        {
            ...draft.value,
            options:
                draft.value.kind === 'choice'
                    ? draft.value.options.filter((o) => o.trim() !== '')
                    : [],
        },
        () => {
            adding.value = false;
            draft.value = { prompt: '', kind: 'yes_no', options: ['', ''] };
        },
    );
}

const field =
    'h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
</script>

<template>
    <section
        v-if="questions.length || event.can.edit"
        class="flex flex-col gap-4 rounded-xl border bg-card p-4"
        data-test="questions"
    >
        <header>
            <h2 class="font-medium">Questions</h2>
            <p class="text-xs text-muted-foreground">
                Answered in "Are you coming?". Counts are from people going or
                maybe.
            </p>
        </header>

        <article
            v-for="question in questions"
            :key="question.id"
            class="flex flex-col gap-2"
        >
            <div class="flex items-start gap-2">
                <p class="min-w-0 flex-1 text-sm font-medium">
                    {{ question.prompt }}
                </p>
                <span class="shrink-0 text-xs text-muted-foreground"
                    >{{ question.answered }} answered</span
                >
                <Button
                    v-if="event.can.edit"
                    size="icon"
                    variant="ghost"
                    class="size-7"
                    :aria-label="`Remove question ${question.prompt}`"
                    :disabled="busy"
                    @click="send(destroy([event.id, question.id]))"
                >
                    <Trash2 class="size-3.5" />
                </Button>
            </div>

            <ul v-if="question.kind !== 'text'" class="flex flex-col gap-1">
                <li
                    v-for="count in question.counts"
                    :key="count.value"
                    class="flex flex-col gap-1"
                >
                    <div
                        class="relative flex items-center gap-2 overflow-hidden rounded-md px-2 py-1 text-sm"
                    >
                        <span
                            class="absolute inset-y-0 left-0 rounded-md bg-foreground/[0.06]"
                            :style="{
                                width: `${(count.total / max(question)) * 100}%`,
                            }"
                            aria-hidden="true"
                        />
                        <span class="relative flex-1">{{
                            label(question, count.value)
                        }}</span>
                        <span
                            class="relative text-muted-foreground tabular-nums"
                            >{{ count.total }}</span
                        >
                    </div>
                    <p
                        v-if="question.answers && count.total"
                        class="flex flex-wrap gap-1.5 px-2 text-xs text-muted-foreground"
                    >
                        <span
                            v-for="a in question.answers.filter(
                                (a) => a.value === count.value,
                            )"
                            :key="a.member.id"
                            class="inline-flex items-center gap-1"
                        >
                            <MemberAvatar :member="a.member" />{{
                                a.member.name
                            }}
                        </span>
                    </p>
                </li>
            </ul>
            <ul
                v-else-if="question.answers"
                class="flex flex-col gap-1.5 text-sm"
            >
                <li
                    v-for="a in question.answers"
                    :key="a.member.id"
                    class="flex items-start gap-2"
                >
                    <MemberAvatar :member="a.member" />
                    <span
                        ><span class="font-medium">{{ a.member.name }}:</span>
                        {{ a.value }}</span
                    >
                </li>
            </ul>
        </article>

        <p v-if="questions.length === 0" class="text-sm text-muted-foreground">
            Ask everyone coming something: "Will you eat crayfish?", "Which
            sauna slot?"
        </p>

        <template v-if="event.can.edit">
            <Button
                v-if="!adding"
                variant="outline"
                class="self-start"
                @click="adding = true"
            >
                <Plus class="size-4" />Add a question
            </Button>
            <form
                v-else
                class="grid gap-3 rounded-lg border p-3"
                @submit.prevent="add"
            >
                <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <div class="grid gap-1.5">
                        <Label for="question-prompt">Question</Label>
                        <Input
                            id="question-prompt"
                            v-model="draft.prompt"
                            placeholder="Will you eat crayfish?"
                            required
                            maxlength="200"
                        />
                        <InputError :message="errors.prompt" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="question-kind">Answer</Label>
                        <select
                            id="question-kind"
                            v-model="draft.kind"
                            :class="field"
                        >
                            <option
                                v-for="(name, kind) in kinds"
                                :key="kind"
                                :value="kind"
                            >
                                {{ name }}
                            </option>
                        </select>
                    </div>
                </div>
                <fieldset v-if="draft.kind === 'choice'" class="grid gap-1.5">
                    <legend class="mb-1 text-sm font-medium">Options</legend>
                    <div
                        v-for="(_, i) in draft.options"
                        :key="i"
                        class="flex gap-2"
                    >
                        <Input
                            v-model="draft.options[i]"
                            :aria-label="`Option ${i + 1}`"
                            maxlength="80"
                        />
                        <Button
                            v-if="draft.options.length > 2"
                            type="button"
                            size="icon"
                            variant="ghost"
                            :aria-label="`Remove option ${i + 1}`"
                            @click="draft.options.splice(i, 1)"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="self-start"
                        @click="draft.options.push('')"
                    >
                        <Plus class="size-3.5" />Another option
                    </Button>
                    <InputError
                        :message="errors.options || errors['options.0']"
                    />
                </fieldset>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="adding = false"
                        >Never mind</Button
                    >
                    <Button type="submit" :disabled="busy">Add question</Button>
                </div>
            </form>
        </template>
    </section>
</template>
