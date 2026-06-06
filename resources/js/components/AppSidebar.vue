<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    ClipboardCheck,
    KeyRound,
    Settings,
    SlidersHorizontal,
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
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { edit as editModelRoles } from '@/routes/model-roles';
import { edit as editProfile } from '@/routes/profile';
import { edit as editProvider } from '@/routes/provider';
import { index as reviewsIndex } from '@/routes/reviews';
import { index as usageIndex } from '@/routes/usage';
import type { NavItem } from '@/types';

const { isCurrentOrParentUrl } = useCurrentUrl();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Workspace',
        href: dashboard(),
        icon: BookOpen,
        isActive:
            isCurrentOrParentUrl(dashboard()) ||
            isCurrentOrParentUrl('/stories'),
    },
    {
        title: 'Review',
        href: reviewsIndex(),
        icon: ClipboardCheck,
        isActive: isCurrentOrParentUrl('/reviews'),
    },
    {
        title: 'Provider',
        href: editProvider(),
        icon: KeyRound,
        isActive: isCurrentOrParentUrl('/provider'),
    },
    {
        title: 'Model Roles',
        href: editModelRoles(),
        icon: SlidersHorizontal,
        isActive: isCurrentOrParentUrl('/model-roles'),
    },
    {
        title: 'Usage',
        href: usageIndex(),
        icon: BarChart3,
        isActive: isCurrentOrParentUrl('/usage'),
    },
    {
        title: 'Settings',
        href: editProfile(),
        icon: Settings,
        isActive: isCurrentOrParentUrl('/settings'),
    },
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
