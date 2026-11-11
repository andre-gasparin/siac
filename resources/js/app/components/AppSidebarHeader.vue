<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/app/components/Breadcrumbs.vue';
import { Button } from '@/shared/components/ui/button';
import { SidebarTrigger } from '@/shared/components/ui/sidebar';
import { useAppearance } from '@/shared/composables/useAppearance';
import type { BreadcrumbItem } from '@/shared/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { resolvedAppearance, updateAppearance } = useAppearance();
const isDark = computed(() => resolvedAppearance.value === 'dark');
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="ml-auto"
            aria-label="Tema escuro"
            :aria-pressed="isDark"
            @click="updateAppearance(isDark ? 'light' : 'dark')"
        >
            <Moon v-if="isDark" aria-hidden="true" />
            <Sun v-else aria-hidden="true" />
        </Button>
    </header>
</template>
