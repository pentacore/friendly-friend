<script setup lang="ts">
import { InfiniteScroll, router } from '@inertiajs/vue3';
import { ArchiveRestore } from '@lucide/vue';
import { restore } from '@/actions/App/Http/Controllers/QuoteController';
import Heading from '@/components/Heading.vue';
import QuoteCard from '@/components/quotes/QuoteCard.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/admin/removed-quotes';
import type { PageProps } from '@/typefinder';

defineProps<PageProps['admin/RemovedQuotes']>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Removed quotes', href: index() }] },
});
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 xl:max-w-6xl"
    >
        <Heading
            title="Removed quotes"
            description="Quotes taken off the site on servers you moderate. Restoring brings back their votes and history."
        />

        <InfiniteScroll
            data="quotes"
            class="grid items-start gap-4 xl:grid-cols-2"
        >
            <div
                v-for="quote in quotes.data"
                :key="quote.id"
                class="flex flex-col gap-2"
            >
                <QuoteCard :quote="quote" show-server />
                <Button
                    v-if="quote.can.restore"
                    size="sm"
                    variant="secondary"
                    class="self-end"
                    @click="
                        router.visit(restore(quote.id), {
                            preserveScroll: true,
                        })
                    "
                >
                    <ArchiveRestore class="size-4" />Restore quote
                </Button>
            </div>
        </InfiniteScroll>

        <p
            v-if="quotes.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            Nothing removed. Quotes you remove land here, so they can be
            restored.
        </p>
    </div>
</template>
