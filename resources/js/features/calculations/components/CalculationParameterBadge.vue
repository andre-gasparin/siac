<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name?: string | null;
        role: 'destination' | 'primary' | 'secondary';
        placeholderText?: string;
    }>(),
    {
        name: null,
        placeholderText: '',
    },
);

const defaultPlaceholder = computed(() => {
    switch (props.role) {
        case 'destination':
            return 'Destino';
        case 'primary':
            return 'Principal (P)';
        case 'secondary':
            return 'Secundário (S)';
        default:
            return '';
    }
});

const isPlaceholder = computed(() => !props.name);
const displayText = computed(
    () => props.name || props.placeholderText || defaultPlaceholder.value,
);

const roleClasses = computed(() => {
    switch (props.role) {
        case 'destination':
            return isPlaceholder.value
                ? 'border-dashed border-emerald-500/40 bg-emerald-500/5 text-emerald-700/70 dark:text-emerald-400/70'
                : 'border-emerald-500/30 bg-emerald-500/15 text-emerald-800 dark:text-emerald-200';
        case 'primary':
            return isPlaceholder.value
                ? 'border-dashed border-sky-500/40 bg-sky-500/5 text-sky-700/70 dark:text-sky-400/70'
                : 'border-sky-500/30 bg-sky-500/15 text-sky-800 dark:text-sky-200';
        case 'secondary':
            return isPlaceholder.value
                ? 'border-dashed border-purple-500/40 bg-purple-500/5 text-purple-700/70 dark:text-purple-400/70'
                : 'border-purple-500/30 bg-purple-500/15 text-purple-800 dark:text-purple-200';
        default:
            return '';
    }
});
</script>

<template>
    <span
        class="inline-flex max-w-full items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold whitespace-nowrap transition-colors"
        :class="roleClasses"
        :title="displayText"
    >
        <span class="truncate">{{ displayText }}</span>
    </span>
</template>
