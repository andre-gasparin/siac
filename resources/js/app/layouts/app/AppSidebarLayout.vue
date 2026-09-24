<script setup lang="ts">
import AppContent from '@/app/components/AppContent.vue';
import AppShell from '@/app/components/AppShell.vue';
import AppSidebar from '@/app/components/AppSidebar.vue';
import AppSidebarHeader from '@/app/components/AppSidebarHeader.vue';
import { Toaster } from '@/shared/components/ui/sonner';
import type { BreadcrumbItem } from '@/shared/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
