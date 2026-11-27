<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    CalendarDays,
    ChartColumn,
    LayoutGrid,
    Quote,
    Scale,
    Server,
    Shuffle,
    SlidersHorizontal,
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
import { index as settingsIndex } from '@/routes/admin/settings';
import { index as eventsIndex } from '@/routes/events';
import { index as importsIndex } from '@/routes/admin/imports';
import { index as removedQuotesIndex } from '@/routes/admin/removed-quotes';
import { index as peopleIndex } from '@/routes/admin/people';
import { index as quotesIndex, random as randomQuote } from '@/routes/quotes';
import { show as thisOrThat } from '@/routes/this-or-that';
import type { NavItem } from '@/types';

const page = usePage();

type NavSection = { label?: string; items: NavItem[] };

/** The sidebar, grouped by what you came to do. Admin tools only show up for admins. */
const sections = computed<NavSection[]>(() => {
    const user = page.props.auth.user;
    const admin: NavItem[] = [
        ...(user?.is_admin
            ? [
                  { title: 'People', href: peopleIndex(), icon: Users },
                  { title: 'Imports', href: importsIndex(), icon: Inbox },
                  {
                      title: 'Removed quotes',
                      href: removedQuotesIndex(),
                      icon: ArchiveRestore,
                  },
              ]
            : []),
        ...(user?.is_owner
            ? [
                  { title: 'Servers', href: guildsIndex(), icon: Server },
                  {
                      title: 'Settings',
                      href: settingsIndex(),
                      icon: SlidersHorizontal,
                  },
              ]
            : []),
    ];

    return [
        {
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            ],
        },
        {
            label: 'Quotes',
            items: [
                { title: 'All quotes', href: quotesIndex(), icon: Quote },
                { title: 'Random quote', href: randomQuote(), icon: Shuffle },
                { title: 'This or that', href: thisOrThat(), icon: Scale },
                { title: 'Top lists', href: top(), icon: Trophy },
            ],
        },
        {
            label: 'Together',
            items: [
                { title: 'Events', href: eventsIndex(), icon: CalendarDays },
                { title: 'Stats', href: stats(), icon: ChartColumn },
            ],
        },
        ...(admin.length ? [{ label: 'Admin', items: admin }] : []),
    ];
});
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
            <NavMain
                v-for="(section, i) in sections"
                :key="section.label ?? i"
                :items="section.items"
                :label="section.label"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
