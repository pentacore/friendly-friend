<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';

defineProps<{
    member: { name: string; avatar_url: string };
    size?: 'sm' | 'md';
}>();

const { getInitials } = useInitials();
</script>

<template>
    <Avatar
        :class="[
            'shrink-0 ring-2 ring-card',
            size === 'md' ? 'size-8' : 'size-6',
        ]"
    >
        <AvatarImage :src="member.avatar_url" :alt="member.name" />
        <AvatarFallback class="text-[10px]">
            {{ getInitials(member.name) }}
        </AvatarFallback>
    </Avatar>
</template>
