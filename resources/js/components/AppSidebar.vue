<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChartColumn,
    LayoutGrid,
    Quote,
    Scale,
    Server,
    Shuffle,
    Trophy,
    Users,
    Inbox,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, stats, top } from '@/routes';
import { index as guildsIndex } from '@/routes/admin/guilds';
import { index as importsIndex } from '@/routes/admin/imports';
import { index as peopleIndex } from '@/routes/admin/people';
import { index as quotesIndex, random as randomQuote } from '@/routes/quotes';
import { show as thisOrThat } from '@/routes/this-or-that';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    { title: 'Quotes', href: quotesIndex(), icon: Quote },
    { title: 'Random quote', href: randomQuote(), icon: Shuffle },
    { title: 'This or that', href: thisOrThat(), icon: Scale },
    { title: 'Top lists', href: top(), icon: Trophy },
    { title: 'Stats', href: stats(), icon: ChartColumn },
    ...(page.props.auth.user?.is_admin
        ? [
              { title: 'People', href: peopleIndex(), icon: Users },
              { title: 'Imports', href: importsIndex(), icon: Inbox },
          ]
        : []),
    ...(page.props.auth.user?.is_owner
        ? [{ title: 'Servers', href: guildsIndex(), icon: Server }]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
