<script setup lang="ts">
import CalculationParameterBadge from '@/features/calculations/components/CalculationParameterBadge.vue';

defineProps<{
    role: 'primary' | 'secondary';
    name?: string | null;
    multiplier?: number | null;
}>();
</script>

<template>
    <span
        v-if="multiplier !== null && multiplier !== undefined"
        class="inline-flex items-center gap-1 font-mono text-sm"
    >
        <span class="font-semibold text-muted-foreground">(</span>
        <CalculationParameterBadge :role="role" :name="name" />
        <span class="font-semibold text-muted-foreground"
            >× {{ multiplier }}</span
        >
        <span class="font-semibold text-muted-foreground">)</span>
    </span>
    <CalculationParameterBadge v-else :role="role" :name="name" />
</template>
