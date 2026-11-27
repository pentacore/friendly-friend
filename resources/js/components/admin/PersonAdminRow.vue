<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroyAlias,
    link,
    merge,
    storeAlias,
} from '@/actions/App/Http/Controllers/Admin/PeopleController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { Input } from '@/components/ui/input';
import { show } from '@/routes/people';
import type { PageProps, PersonAdminResource } from '@/typefinder';

const props = defineProps<{
    person: PersonAdminResource;
    discordUsers: PageProps['admin/People']['discord_users'];
    allPeople: PageProps['admin/People']['all_people'];
}>();

const aliasForm = useForm({ alias: '' });
const linkForm = useForm({
    discord_user_id: props.person.discord_user?.id ?? null,
});
const mergeForm = useForm({ into_person_id: null as number | null });
const mergeOpen = ref(false);

const selectClass =
    'h-9 min-w-0 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

function addAlias(): void {
    aliasForm.submit(storeAlias(props.person.id), {
        preserveScroll: true,
        onSuccess: () => aliasForm.reset(),
    });
}

function removeAlias(aliasId: number): void {
    router.visit(destroyAlias([props.person.id, aliasId]), {
        preserveScroll: true,
    });
}

function saveLink(): void {
    linkForm.submit(link(props.person.id), { preserveScroll: true });
}

function mergeInto(): void {
    mergeForm.submit(merge(props.person.id), {
        preserveScroll: true,
        onSuccess: () => (mergeOpen.value = false),
    });
}
</script>

<template>
    <article class="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
        <div class="flex flex-col gap-3">
            <div>
                <Link
                    :href="show(person.slug)"
                    class="font-quote text-lg font-bold underline-offset-4 hover:underline"
                >
                    {{ person.display_name }}
                </Link>
                <p class="text-xs text-muted-foreground">
                    {{ person.quote_count }}
                    {{ person.quote_count === 1 ? 'quote' : 'quotes' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-1.5" aria-label="Also known as">
                <Badge
                    v-for="alias in person.aliases"
                    :key="alias.id"
                    variant="secondary"
                    class="gap-1"
                >
                    {{ alias.alias }}
                    <button
                        type="button"
                        class="rounded-full hover:text-destructive focus-visible:outline-none"
                        :aria-label="`Remove nickname ${alias.alias}`"
                        @click="removeAlias(alias.id)"
                    >
                        <X class="size-3" />
                    </button>
                </Badge>
            </div>

            <form class="flex gap-2" @submit.prevent="addAlias">
                <Input
                    v-model="aliasForm.alias"
                    placeholder="Add a nickname"
                    :aria-label="`Nickname for ${person.display_name}`"
                />
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="!aliasForm.alias"
                >
                    Add
                </Button>
            </form>
            <InputError :message="aliasForm.errors.alias" />
        </div>

        <div class="flex flex-col gap-3">
            <form class="grid gap-2" @submit.prevent="saveLink">
                <label class="text-sm font-medium" :for="`link-${person.id}`"
                    >Discord account</label
                >
                <div class="flex gap-2">
                    <select
                        :id="`link-${person.id}`"
                        v-model="linkForm.discord_user_id"
                        :class="selectClass"
                        class="flex-1"
                    >
                        <option :value="null">Not on Discord</option>
                        <option
                            v-for="user in discordUsers"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }}
                        </option>
                    </select>
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="!linkForm.isDirty"
                    >
                        Save
                    </Button>
                </div>
                <InputError :message="linkForm.errors.discord_user_id" />
            </form>

            <Dialog v-model:open="mergeOpen">
                <DialogTrigger as-child>
                    <Button variant="ghost" size="sm" class="self-start">
                        Merge into someone else…
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle
                            >Merge {{ person.display_name }}</DialogTitle
                        >
                        <DialogDescription>
                            Their quotes and nicknames move to the person you
                            pick, and “{{ person.display_name }}” becomes a
                            nickname. This can't be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <select
                        v-model="mergeForm.into_person_id"
                        :class="selectClass"
                        aria-label="Merge into"
                    >
                        <option :value="null" disabled>Pick a person</option>
                        <option
                            v-for="other in allPeople.filter(
                                (p) => p.id !== person.id,
                            )"
                            :key="other.id"
                            :value="other.id"
                        >
                            {{ other.display_name }}
                        </option>
                    </select>
                    <InputError :message="mergeForm.errors.into_person_id" />
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button variant="ghost">Cancel</Button>
                        </DialogClose>
                        <Button
                            :disabled="!mergeForm.into_person_id"
                            @click="mergeInto"
                        >
                            Merge
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </article>
</template>
