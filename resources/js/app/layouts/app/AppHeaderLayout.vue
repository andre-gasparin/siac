<script setup lang="ts">
import AppContent from '@/app/components/AppContent.vue';
import AppHeader from '@/app/components/AppHeader.vue';
import AppShell from '@/app/components/AppShell.vue';
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
    <AppShell variant="header">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent variant="header">
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
