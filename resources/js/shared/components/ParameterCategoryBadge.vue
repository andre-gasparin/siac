<script setup lang="ts">
import { computed } from 'vue';
import type { ParameterCategory } from '@/shared/types';

const props = defineProps<{
    category: ParameterCategory | string;
}>();

const CATEGORY_META: Record<
    ParameterCategory,
    { label: string; badgeClass: string }
> = {
    ON: {
        label: 'Online',
        badgeClass:
            'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
    },
    LAB: {
        label: 'Laboratório',
        badgeClass:
            'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300',
    },
    OP: {
        label: 'Operação',
        badgeClass:
            'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-300',
    },
    CALC: {
        label: 'Calculado',
        badgeClass:
            'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
    },
    PORT: {
        label: 'Portátil',
        badgeClass:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
    },
};

const categoryMeta = computed(() => {
    const key = props.category as ParameterCategory;

    return CATEGORY_META[key] ?? null;
});
</script>

<template>
    <span
        v-if="categoryMeta"
        :title="categoryMeta.label"
        :data-parameter-category="category"
        class="inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[10px] leading-none font-bold tracking-wide uppercase select-none"
        :class="categoryMeta.badgeClass"
    >
        {{ category }}
    </span>
</template>
