<script setup lang="ts">
import { Link, router, setLayoutProps } from '@inertiajs/vue3';
import { ArchiveRestore, ExternalLink, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroy,
    restore,
} from '@/actions/App/Http/Controllers/QuoteController';
import QuoteEditForm from '@/components/quotes/QuoteEditForm.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import QuoteText from '@/components/quotes/QuoteText.vue';
import VoteButtons from '@/components/quotes/VoteButtons.vue';
import { provideLiveQuoteScores } from '@/composables/useLiveQuoteScores';
import { formatSaidAt } from '@/lib/quoteDates';
import { index, show } from '@/routes/quotes';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['quotes/Show']>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Quotes', href: index() },
        { title: `#${props.quote.id}`, href: show(props.quote.id) },
    ],
});

const saidAt = computed(() =>
    formatSaidAt(props.quote.said_at, props.quote.said_at_precision),
);

provideLiveQuoteScores([props.quote.guild.id]);

const editing = ref(false);

function remove(): void {
    router.visit(destroy(props.quote.id));
}

const editedAt = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-10 p-4 sm:p-10">
        <section
            v-if="editing"
            aria-label="Edit quote"
            class="rounded-xl border p-5"
        >
            <QuoteEditForm :quote="quote" @done="editing = false" />
        </section>

        <section
            v-else
            class="flex flex-col gap-6"
            :data-test="`quote-${quote.id}`"
        >
            <div
                v-if="quote.status === 'removed'"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed p-3 text-sm"
                data-test="removed-banner"
            >
                <span class="text-muted-foreground">
                    Removed. Only admins of {{ quote.guild.name }} can see it.
                </span>
                <Button
                    v-if="quote.can.restore"
                    size="sm"
                    variant="secondary"
                    @click="router.visit(restore(quote.id))"
                >
                    <ArchiveRestore class="size-4" />Restore quote
                </Button>
            </div>

            <QuoteText :lines="quote.lines" size="lg" />

            <p v-if="quote.context" class="text-muted-foreground italic">
                {{ quote.context }}
            </p>

            <dl
                class="flex flex-wrap items-center gap-x-6 gap-y-2 border-t pt-4 text-sm text-muted-foreground"
            >
                <div>
                    <dt class="sr-only">Score</dt>
                    <dd><VoteButtons :quote="quote" /></dd>
                </div>
                <div v-if="saidAt">
                    <dt class="sr-only">Said</dt>
                    <dd>{{ saidAt }}</dd>
                </div>
                <div>
                    <dt class="sr-only">Server</dt>
                    <dd>{{ quote.guild.name }}</dd>
                </div>
                <div>
                    <dt class="sr-only">Saved by</dt>
                    <dd>saved by {{ quote.submitted_by.name }}</dd>
                </div>
                <div v-if="quote.source_message_url">
                    <dt class="sr-only">Source</dt>
                    <dd>
                        <a
                            :href="quote.source_message_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 underline-offset-4 hover:text-foreground hover:underline"
                        >
                            Original message
                            <ExternalLink class="size-3.5" />
                        </a>
                    </dd>
                </div>
            </dl>

            <div v-if="quote.can.update || quote.can.delete" class="flex gap-2">
                <Button
                    v-if="quote.can.update"
                    variant="outline"
                    size="sm"
                    @click="editing = true"
                >
                    <Pencil class="size-4" />
                    Edit
                </Button>
                <Dialog v-if="quote.can.delete">
                    <DialogTrigger as-child>
                        <Button variant="ghost" size="sm">
                            <Trash2 class="size-4" />
                            Remove
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Remove this quote?</DialogTitle>
                            <DialogDescription>
                                It disappears from the site, Discord commands
                                and top lists. The original Discord message
                                stays.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="ghost">Keep it</Button>
                            </DialogClose>
                            <Button variant="destructive" @click="remove">
                                Remove quote
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </section>

        <section v-if="revisions.length > 1" aria-labelledby="history">
            <h2 id="history" class="mb-3 text-sm font-semibold">
                Edit history
            </h2>
            <ol class="flex flex-col gap-3 text-sm">
                <li
                    v-for="revision in revisions"
                    :key="revision.id"
                    class="rounded-lg border p-3"
                >
                    <p class="mb-1 text-xs text-muted-foreground">
                        {{ revision.editor_name }} ·
                        {{
                            revision.created_at
                                ? editedAt.format(new Date(revision.created_at))
                                : ''
                        }}
                    </p>
                    <p v-for="(line, i) in revision.lines" :key="i">
                        <span class="font-medium">{{ line.speaker }}:</span>
                        {{ line.text }}
                    </p>
                </li>
            </ol>
        </section>

        <Link
            :href="index()"
            class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
            ← All quotes
        </Link>
    </div>
</template>
