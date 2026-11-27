<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onKeyStroke } from '@vueuse/core';
import { ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/ThisOrThatController';
import Heading from '@/components/Heading.vue';
import QuoteText from '@/components/quotes/QuoteText.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/quotes';
import { show } from '@/routes/this-or-that';
import type { PageProps, QuoteResource } from '@/typefinder';

defineOptions({
    layout: { breadcrumbs: [{ title: 'This or that', href: show() }] },
});

const props = defineProps<PageProps['quotes/ThisOrThat']>();

const picking = ref(false);

function pick(winner: QuoteResource): void {
    if (!props.pair || picking.value) {
        return;
    }

    picking.value = true;
    router.visit(store(), {
        data: {
            quote_a: props.pair[0].id,
            quote_b: props.pair[1].id,
            winner: winner.id,
        },
        preserveScroll: true,
        onFinish: () => (picking.value = false),
    });
}

function skip(): void {
    router.reload({ only: ['pair'] });
}

onKeyStroke('ArrowLeft', () => props.pair && pick(props.pair[0]));
onKeyStroke('ArrowRight', () => props.pair && pick(props.pair[1]));
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="This or that"
            description="Which one is funnier? Pick with a click, or the ← and → keys."
        />

        <div v-if="pair" class="grid gap-4 md:grid-cols-2">
            <button
                v-for="(quote, i) in pair"
                :key="quote.id"
                type="button"
                class="group flex flex-col justify-between gap-6 rounded-xl border bg-card p-6 text-left shadow-xs transition hover:-translate-y-0.5 hover:border-foreground/30 hover:shadow-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-60"
                :disabled="picking"
                :data-test="`pick-${i}`"
                @click="pick(quote)"
            >
                <QuoteText :lines="quote.lines" />
                <span
                    class="text-sm font-medium text-muted-foreground group-hover:text-foreground"
                >
                    {{ i === 0 ? '← This one' : 'This one →' }}
                </span>
            </button>
        </div>

        <div v-if="pair" class="flex justify-center">
            <Button variant="ghost" size="sm" @click="skip">
                Neither, show me two others
            </Button>
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            This needs at least two saved quotes.
            <Link :href="index()" class="underline underline-offset-4"
                >See the quotes</Link
            >
            or save some in Discord first.
        </p>
    </div>
</template>
