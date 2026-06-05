<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <!-- Skip link: first focusable element, jumps keyboard users past the chrome -->
        <a
            href="#main-content"
            class="sr-only rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            Skip to content
        </a>
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div
                id="main-content"
                tabindex="-1"
                class="flex flex-1 flex-col outline-none"
            >
                <slot />
            </div>
        </AppContent>
        <Toaster />
        <!-- Global confirmation host for useConfirm() (no native browser dialogs) -->
        <ConfirmDialog />
    </AppShell>
</template>
