<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, ClipboardCheck, Settings } from '@lucide/vue';
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
import { edit as editProfile } from '@/routes/profile';
import { index as reviewsIndex } from '@/routes/reviews';
import type { NavItem } from '@/types';

const { isCurrentOrParentUrl } = useCurrentUrl();

// Computed so the active-area indicator re-evaluates on navigation. Settings
// matches the whole /settings/* area (profile, security, appearance), not just
// the profile landing it links to. Play is intentionally deferred to Phase 5.
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Workspace',
        href: dashboard(),
        icon: BookOpen,
        isActive: isCurrentOrParentUrl(dashboard()),
    },
    {
        title: 'Review',
        href: reviewsIndex(),
        icon: ClipboardCheck,
        isActive: isCurrentOrParentUrl('/reviews'),
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
