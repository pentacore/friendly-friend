<script setup lang="ts">
import { InfiniteScroll, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import PersonAdminRow from '@/components/admin/PersonAdminRow.vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/people';
import type { PageProps } from '@/typefinder';

defineOptions({
    layout: { breadcrumbs: [{ title: 'People', href: index() }] },
});

const props = defineProps<PageProps['admin/People']>();

const search = ref(props.search);

const searchSoon = useDebounceFn(() => {
    router.visit(index({ query: { q: search.value || undefined } }), {
        preserveState: true,
        replace: true,
        reset: ['people'],
    });
}, 300);
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="People"
            description="Everyone who has been quoted. Add nicknames so quotes find the right person, link people to their Discord account, and merge duplicates."
        />

        <Input
            v-model="search"
            type="search"
            placeholder="Search names and nicknames…"
            aria-label="Search people"
            @input="searchSoon"
        />

        <InfiniteScroll data="people" class="flex flex-col gap-3">
            <PersonAdminRow
                v-for="person in people.data"
                :key="person.id"
                :person="person"
                :discord-users="discord_users"
                :all-people="all_people"
            />
        </InfiniteScroll>

        <p
            v-if="people.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            {{
                search
                    ? `Nobody called “${search}”.`
                    : 'Nobody has been quoted yet.'
            }}
        </p>
    </div>
</template>
