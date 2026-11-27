<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show } from '@/routes/people';

defineProps<{
    member: { name: string; person_slug: string | null };
}>();
</script>

<template>
    <Link
        v-if="member.person_slug"
        :href="show(member.person_slug)"
        class="truncate underline-offset-4 hover:underline"
    >
        {{ member.name }}
    </Link>
    <span v-else class="truncate">{{ member.name }}</span>
</template>
