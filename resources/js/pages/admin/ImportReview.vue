<script setup lang="ts">
import { router, setLayoutProps } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { ref } from 'vue';
import {
    approve,
    approveAll,
    reject,
} from '@/actions/App/Http/Controllers/Admin/ImportController';
import Heading from '@/components/Heading.vue';
import QuoteEditForm from '@/components/quotes/QuoteEditForm.vue';
import QuoteText from '@/components/quotes/QuoteText.vue';
import { Button } from '@/components/ui/button';
import { formatSaidAt } from '@/lib/quoteDates';
import { index, show } from '@/routes/admin/imports';
import type { PageProps } from '@/typefinder';

const props = defineProps<PageProps['admin/ImportReview']>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Imports', href: index() },
        {
            title: `#${props.quote_import.channel_name ?? props.quote_import.channel_id}`,
            href: show(props.quote_import.id),
        },
    ],
});

const editing = ref<number | null>(null);

function decide(quoteId: number, verdict: 'approve' | 'reject'): void {
    const route = verdict === 'approve' ? approve : reject;
    router.visit(route([props.quote_import.id, quoteId]), {
        preserveScroll: true,
    });
}

function approveEverything(): void {
    router.visit(approveAll(props.quote_import.id), { preserveScroll: true });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                :title="`Review #${quote_import.channel_name ?? quote_import.channel_id}`"
                :description="`${pending.length} ${pending.length === 1 ? 'quote' : 'quotes'} from ${quote_import.guild.name} waiting. Approved quotes keep their original date.`"
            />
            <Button v-if="pending.length > 1" @click="approveEverything">
                Approve all {{ pending.length }}
            </Button>
        </div>

        <ul class="flex flex-col gap-4">
            <li
                v-for="quote in pending"
                :key="quote.id"
                class="rounded-xl border p-5"
                :data-test="`pending-${quote.id}`"
            >
                <QuoteEditForm
                    v-if="editing === quote.id"
                    :quote="quote"
                    @done="editing = null"
                />
                <template v-else>
                    <QuoteText :lines="quote.lines" />
                    <p
                        v-if="quote.context"
                        class="mt-2 text-sm text-muted-foreground italic"
                    >
                        {{ quote.context }}
                    </p>
                    <div
                        class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground"
                    >
                        <span v-if="quote.said_at">{{
                            formatSaidAt(quote.said_at, quote.said_at_precision)
                        }}</span>
                        <span>posted by {{ quote.submitted_by.name }}</span>
                        <a
                            v-if="quote.source_message_url"
                            :href="quote.source_message_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 underline-offset-4 hover:text-foreground hover:underline"
                        >
                            Original message <ExternalLink class="size-3" />
                        </a>
                        <span class="ml-auto flex gap-2">
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="editing = quote.id"
                                >Edit</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                @click="decide(quote.id, 'reject')"
                                >Not a quote</Button
                            >
                            <Button
                                size="sm"
                                @click="decide(quote.id, 'approve')"
                                >Approve</Button
                            >
                        </span>
                    </div>
                </template>
            </li>
        </ul>

        <p
            v-if="pending.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            All reviewed. Approved quotes are on the site now.
        </p>
    </div>
</template>
