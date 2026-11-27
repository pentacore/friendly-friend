<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import GuildSettingsCard from '@/components/admin/GuildSettingsCard.vue';
import Heading from '@/components/Heading.vue';
import { index } from '@/routes/admin/guilds';
import type { PageProps } from '@/typefinder';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Servers', href: index() }],
    },
});

defineProps<PageProps['admin/Guilds']>();
</script>

<template>
    <Head title="Servers" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Servers"
            description="Servers the bot has joined. Enable a server and choose which of its roles may log in. Admin roles also get the admin tools."
        />

        <p
            v-if="guilds.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            The bot hasn't joined any servers yet. Invite it (see
            docs/discord-app.md) and make sure the bot is running. Servers show
            up here once it connects.
        </p>

        <div class="grid gap-6 xl:grid-cols-2">
            <GuildSettingsCard
                v-for="guild in guilds"
                :key="guild.id"
                :guild="guild"
            />
        </div>
    </div>
</template>
