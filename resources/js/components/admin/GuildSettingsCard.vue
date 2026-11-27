<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Admin/GuildController';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/composables/useInitials';
import type { GuildResource } from '@/typefinder';

const props = defineProps<{ guild: GuildResource }>();

const { getInitials } = useInitials();

const form = useForm({
    enabled: props.guild.enabled,
    allow_all_members: props.guild.allow_all_members,
    allowed_role_ids: [...props.guild.allowed_role_ids],
    admin_role_ids: [...props.guild.admin_role_ids],
    quotes_channel_id: props.guild.quotes_channel_id,
});

type RoleList = 'allowed_role_ids' | 'admin_role_ids';

function setRole(list: RoleList, roleId: string, checked: boolean): void {
    form[list] = checked
        ? [...form[list], roleId]
        : form[list].filter((id) => id !== roleId);
}

function roleColor(color: number): string {
    return color === 0
        ? 'var(--muted-foreground)'
        : `#${color.toString(16).padStart(6, '0')}`;
}

const roleErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.includes('role_ids'))
        .map(([, message]) => message),
);

function save(): void {
    form.submit(update(props.guild.id), { preserveScroll: true });
}
</script>

<template>
    <Card :data-test="`guild-${guild.discord_id}`">
        <CardHeader class="flex flex-row items-center gap-4">
            <Avatar class="size-12 rounded-xl">
                <AvatarImage
                    v-if="guild.icon_url"
                    :src="guild.icon_url"
                    :alt="guild.name"
                />
                <AvatarFallback class="rounded-xl">
                    {{ getInitials(guild.name) }}
                </AvatarFallback>
            </Avatar>
            <div class="grid flex-1 gap-1">
                <CardTitle>{{ guild.name }}</CardTitle>
                <CardDescription class="flex flex-wrap items-center gap-2">
                    <Badge :variant="guild.enabled ? 'default' : 'secondary'">
                        {{ guild.enabled ? 'Enabled' : 'Disabled' }}
                    </Badge>
                    <Badge v-if="!guild.bot_present" variant="destructive">
                        Bot not in server
                    </Badge>
                    <span>{{ guild.member_count }} members synced</span>
                </CardDescription>
            </div>
        </CardHeader>

        <CardContent class="grid gap-6">
            <div class="grid gap-3">
                <Label class="flex items-center gap-3">
                    <Checkbox v-model="form.enabled" name="enabled" />
                    <span>
                        Enabled: the bot records this server, and its members
                        can log in
                    </span>
                </Label>
                <Label class="flex items-center gap-3">
                    <Checkbox
                        v-model="form.allow_all_members"
                        name="allow_all_members"
                    />
                    <span>Let every member of this server log in</span>
                </Label>
            </div>

            <div v-if="guild.roles.length" class="grid gap-6 sm:grid-cols-2">
                <fieldset
                    v-for="list in [
                        {
                            key: 'allowed_role_ids' as const,
                            title: 'Roles that may log in',
                        },
                        {
                            key: 'admin_role_ids' as const,
                            title: 'Admin roles',
                        },
                    ]"
                    :key="list.key"
                    class="grid gap-2"
                    :disabled="
                        list.key === 'allowed_role_ids' &&
                        form.allow_all_members
                    "
                >
                    <legend class="mb-2 text-sm font-medium">
                        {{ list.title }}
                    </legend>
                    <Label
                        v-for="role in guild.roles"
                        :key="role.id"
                        class="flex items-center gap-3 font-normal"
                    >
                        <Checkbox
                            :model-value="form[list.key].includes(role.id)"
                            @update:model-value="
                                (checked) =>
                                    setRole(list.key, role.id, checked === true)
                            "
                        />
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: roleColor(role.color) }"
                        />
                        {{ role.name }}
                    </Label>
                </fieldset>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No roles synced yet. They appear once the bot has connected to
                this server.
            </p>

            <div class="grid gap-2">
                <Label for="quotes-channel">Post saved quotes in</Label>
                <select
                    id="quotes-channel"
                    v-model="form.quotes_channel_id"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <option :value="null">Don't post</option>
                    <option
                        v-for="channel in guild.channels"
                        :key="channel.id"
                        :value="channel.id"
                    >
                        #{{ channel.name }}
                    </option>
                </select>
                <InputError :message="form.errors.quotes_channel_id" />
            </div>

            <InputError
                v-for="message in roleErrors"
                :key="message"
                :message="message"
            />
        </CardContent>

        <CardFooter class="justify-end">
            <Button :disabled="!form.isDirty || form.processing" @click="save">
                <Spinner v-if="form.processing" />
                Save
            </Button>
        </CardFooter>
    </Card>
</template>
