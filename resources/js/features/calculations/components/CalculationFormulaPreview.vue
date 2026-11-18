<script setup lang="ts">
import { computed } from 'vue';
import CalculationOperand from '@/features/calculations/components/CalculationOperand.vue';
import CalculationParameterBadge from '@/features/calculations/components/CalculationParameterBadge.vue';
import type { CalculationSystem } from '@/features/calculations/types';

const props = defineProps<{
    type: string;
    destinationId?: number | null;
    primaryId?: number | null;
    secondaryId?: number | null;
    primaryMultiplier?: number | null;
    secondaryMultiplier?: number | null;
    resultMultiplier?: number | null;
    systems: CalculationSystem[];
    types?: Record<string, { label: string; formula: string }>;
}>();

function resolveParameterName(id?: number | null): string | null {
    if (!id) {
        return null;
    }

    for (const system of props.systems) {
        const parameter = system.parameters.find((item) => item.id === id);

        if (parameter) {
            return `${system.name} | ${parameter.name}`;
        }
    }

    return `Parâmetro #${id}`;
}

const destinationName = computed(() =>
    resolveParameterName(props.destinationId),
);
const primaryName = computed(() => resolveParameterName(props.primaryId));
const secondaryName = computed(() => resolveParameterName(props.secondaryId));

const hasResultMultiplier = computed(
    () =>
        props.resultMultiplier !== null && props.resultMultiplier !== undefined,
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5 text-sm leading-relaxed">
        <CalculationParameterBadge
            role="destination"
            :name="destinationName"
            placeholder-text="Destino"
        />

        <span class="font-semibold text-muted-foreground">=</span>

        <span
            v-if="hasResultMultiplier"
            class="font-semibold text-muted-foreground"
            >(</span
        >

        <!-- cycle: P ÷ S -->
        <template v-if="type === 'cycle'">
            <CalculationOperand
                role="primary"
                :name="primaryName"
                :multiplier="primaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">÷</span>
            <CalculationOperand
                role="secondary"
                :name="secondaryName"
                :multiplier="secondaryMultiplier"
            />
        </template>

        <!-- ratio: S ÷ P -->
        <template v-else-if="type === 'ratio'">
            <CalculationOperand
                role="secondary"
                :name="secondaryName"
                :multiplier="secondaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">÷</span>
            <CalculationOperand
                role="primary"
                :name="primaryName"
                :multiplier="primaryMultiplier"
            />
        </template>

        <!-- percentage: (S ÷ P) × 100 -->
        <template v-else-if="type === 'percentage'">
            <span class="font-semibold text-muted-foreground">(</span>
            <CalculationOperand
                role="secondary"
                :name="secondaryName"
                :multiplier="secondaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">÷</span>
            <CalculationOperand
                role="primary"
                :name="primaryName"
                :multiplier="primaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">) × 100</span>
        </template>

        <!-- efficiency: (1 − (S ÷ P)) × 100 -->
        <template v-else-if="type === 'efficiency'">
            <span class="font-semibold text-muted-foreground">( 1 − (</span>
            <CalculationOperand
                role="secondary"
                :name="secondaryName"
                :multiplier="secondaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">÷</span>
            <CalculationOperand
                role="primary"
                :name="primaryName"
                :multiplier="primaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">) ) × 100</span>
        </template>

        <!-- net_loss: P ÷ (S − 1) -->
        <template v-else-if="type === 'net_loss'">
            <CalculationOperand
                role="primary"
                :name="primaryName"
                :multiplier="primaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">÷ (</span>
            <CalculationOperand
                role="secondary"
                :name="secondaryName"
                :multiplier="secondaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">− 1 )</span>
        </template>

        <!-- ph: log10((P − (S ÷ 3)) ÷ 273) + 11 -->
        <template v-else-if="type === 'ph'">
            <span class="font-semibold text-muted-foreground">log10((</span>
            <CalculationOperand
                role="primary"
                :name="primaryName"
                :multiplier="primaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">− (</span>
            <CalculationOperand
                role="secondary"
                :name="secondaryName"
                :multiplier="secondaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground"
                >÷ 3 ) ) ÷ 273) + 11</span
            >
        </template>

        <!-- fallback -->
        <template v-else>
            <CalculationOperand
                role="primary"
                :name="primaryName"
                :multiplier="primaryMultiplier"
            />
            <span class="font-semibold text-muted-foreground">÷</span>
            <CalculationOperand
                role="secondary"
                :name="secondaryName"
                :multiplier="secondaryMultiplier"
            />
        </template>

        <span
            v-if="hasResultMultiplier"
            class="font-semibold text-muted-foreground"
            >) × {{ resultMultiplier }}</span
        >
    </div>
</template>
