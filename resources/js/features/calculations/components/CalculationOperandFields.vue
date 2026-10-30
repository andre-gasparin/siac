<script setup lang="ts">
import { computed } from 'vue';
import type { CalculationSystem } from '@/features/calculations/types';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';

const props = defineProps<{
    role: string;
    label: string;
    systems: CalculationSystem[];
    withMultiplier?: boolean;
}>();
const system = defineModel<number | null>('system', { required: true });
const parameter = defineModel<number | null>('parameter', { required: true });
const multiplier = defineModel<number | null>('multiplier', { default: null });
const parameters = computed(
    () =>
        props.systems.find((item) => item.id === system.value)?.parameters ??
        [],
);

function toggleMultiplier(event: Event) {
    multiplier.value = (event.target as HTMLInputElement).checked ? 1000 : null;
}
</script>

<template>
    <fieldset
        class="grid gap-4 rounded-lg border p-4 transition-colors sm:grid-cols-2"
        :class="{
            'border-emerald-500/30 bg-emerald-500/[0.02]':
                role === 'destination',
            'border-sky-500/30 bg-sky-500/[0.02]': role === 'primary',
            'border-purple-500/30 bg-purple-500/[0.02]': role === 'secondary',
        }"
    >
        <legend class="flex items-center gap-1.5 px-2 text-sm font-semibold">
            <span
                class="size-2 rounded-full"
                :class="{
                    'bg-emerald-500': role === 'destination',
                    'bg-sky-500': role === 'primary',
                    'bg-purple-500': role === 'secondary',
                }"
            />
            {{ label }}
        </legend>
        <div class="grid gap-2">
            <Label :for="`${role}-system`">Sistema</Label>
            <select
                :id="`${role}-system`"
                v-model="system"
                required
                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                @change="parameter = null"
            >
                <option :value="null" disabled>Selecione o sistema</option>
                <option v-for="item in systems" :key="item.id" :value="item.id">
                    {{ item.name }}
                </option>
            </select>
        </div>
        <div class="grid gap-2">
            <Label :for="`${role}-parameter`">Parâmetro</Label>
            <select
                :id="`${role}-parameter`"
                v-model="parameter"
                required
                :disabled="system === null"
                class="h-10 w-full rounded-md border bg-background px-3 text-sm disabled:opacity-50"
            >
                <option :value="null" disabled>Selecione o parâmetro</option>
                <option
                    v-for="item in parameters"
                    :key="item.id"
                    :value="item.id"
                >
                    {{ item.name }}
                </option>
            </select>
        </div>
        <div v-if="withMultiplier" class="grid gap-2 sm:col-span-2">
            <Label :for="`${role}-enabled`" class="flex items-center gap-2">
                <input
                    :id="`${role}-enabled`"
                    type="checkbox"
                    :checked="multiplier !== null"
                    @change="toggleMultiplier"
                />
                Multiplicar esta entrada antes do cálculo
            </Label>
            <Input
                :id="`${role}-multiplier`"
                :model-value="multiplier ?? ''"
                type="number"
                step="any"
                :disabled="multiplier === null"
                :required="multiplier !== null"
                :aria-label="`Multiplicador ${label}`"
                @update:model-value="
                    (value) => {
                        multiplier = value === '' ? null : Number(value);
                    }
                "
            />
        </div>
    </fieldset>
</template>
