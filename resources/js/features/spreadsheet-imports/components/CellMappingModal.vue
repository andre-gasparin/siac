<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { CellMapping } from '@/features/spreadsheet-imports/types';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';

export interface ParameterOption {
    id: number;
    monitored_system_id: number;
    name: string;
    code?: string | null;
    unit?: string | null;
    decimals?: number;
}

export interface SystemOption {
    id: number;
    name: string;
    parameters: ParameterOption[];
}

const props = defineProps<{
    open: boolean;
    cellCoordinate: string;
    existingMapping?: CellMapping | null;
    defaultDateCell?: string;
    defaultTimeCell?: string;
    systems: SystemOption[];
    dateMode?: string;
    dateRow?: number | string | null;
    dateColumn?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'save', mapping: CellMapping): void;
    (e: 'remove', cell: string): void;
}>();

const isSeriesMode = computed(() => {
    return (
        props.dateMode === 'horizontal_series' ||
        props.dateMode === 'vertical_series' ||
        props.dateMode === 'upload_date_match' ||
        props.dateMode === 'all_dates_scan'
    );
});

const isHorizontal = computed(() => props.dateMode === 'horizontal_series');
const isVertical = computed(() => {
    return (
        props.dateMode === 'vertical_series' ||
        props.dateMode === 'upload_date_match' ||
        props.dateMode === 'all_dates_scan'
    );
});

const targetRow = computed(() => {
    const match = props.cellCoordinate.match(/\d+$/);

    return match ? parseInt(match[0], 10) : 1;
});

const targetCol = computed(() => {
    const match = props.cellCoordinate.match(/^[A-Z]+/i);

    return match ? match[0].toUpperCase() : 'A';
});

const titleLabel = computed(() => {
    if (isHorizontal.value) {
        return `Mapear Parâmetro para a Linha ${targetRow.value}`;
    }

    if (isVertical.value) {
        return `Mapear Parâmetro para a Coluna ${targetCol.value}`;
    }

    return `Mapear Célula ${props.cellCoordinate}`;
});

const descriptionLabel = computed(() => {
    if (isHorizontal.value) {
        return `Os valores desta linha serão vinculados à data encontrada na Linha ${props.dateRow ?? '2'}.`;
    }

    if (isVertical.value) {
        return `Os valores desta coluna serão vinculados à data encontrada na Coluna ${props.dateColumn ?? 'A'}.`;
    }

    return 'Configure o sistema, parâmetro, multiplicador e referências de data/hora para esta célula.';
});

const selectedSystemId = ref<number>(props.systems[0]?.id ?? 0);
const selectedParameterId = ref<number>(0);
const multiplier = ref<number>(1.0);
const customMultiplier = ref<string>('1');
const dateCell = ref<string>('');
const timeCell = ref<string>('');

const currentSystem = computed(() =>
    props.systems.find((s) => s.id === selectedSystemId.value),
);

const availableParameters = computed(() => {
    return currentSystem.value?.parameters ?? [];
});

const multiplierPresets = [
    { label: 'x1 (Normal)', value: 1.0 },
    { label: 'x100', value: 100.0 },
    { label: '÷100 (0.01)', value: 0.01 },
    { label: 'x1.000', value: 1000.0 },
    { label: '÷1.000 (0.001)', value: 0.001 },
    { label: 'x100.000', value: 100000.0 },
];

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.existingMapping) {
                selectedSystemId.value =
                    props.existingMapping.monitored_system_id;
                selectedParameterId.value = props.existingMapping.parameter_id;
                multiplier.value = props.existingMapping.multiplier;
                customMultiplier.value = String(
                    props.existingMapping.multiplier,
                );
                dateCell.value =
                    props.existingMapping.date_cell ??
                    props.defaultDateCell ??
                    '';
                timeCell.value =
                    props.existingMapping.time_cell ??
                    props.defaultTimeCell ??
                    '';
            } else {
                selectedSystemId.value = props.systems[0]?.id ?? 0;
                selectedParameterId.value =
                    props.systems[0]?.parameters[0]?.id ?? 0;
                multiplier.value = 1.0;
                customMultiplier.value = '1';
                dateCell.value = props.defaultDateCell ?? '';
                timeCell.value = props.defaultTimeCell ?? '';
            }
        }
    },
    { immediate: true },
);

watch(selectedSystemId, () => {
    if (
        !availableParameters.value.some(
            (p) => p.id === selectedParameterId.value,
        )
    ) {
        selectedParameterId.value = availableParameters.value[0]?.id ?? 0;
    }
});

function applyMultiplierPreset(val: number) {
    multiplier.value = val;
    customMultiplier.value = String(val);
}

function onCustomMultiplierChange() {
    const parsed = parseFloat(customMultiplier.value.replace(',', '.'));

    if (!isNaN(parsed) && parsed !== 0) {
        multiplier.value = parsed;
    }
}

function handleSave() {
    if (!selectedParameterId.value || !selectedSystemId.value) {
        return;
    }

    const selectedParam = availableParameters.value.find(
        (p) => p.id === selectedParameterId.value,
    );

    emit('save', {
        cell: props.cellCoordinate.toUpperCase(),
        monitored_system_id: selectedSystemId.value,
        parameter_id: selectedParameterId.value,
        date_cell:
            !isSeriesMode.value && dateCell.value
                ? dateCell.value.toUpperCase().trim()
                : undefined,
        time_cell:
            !isSeriesMode.value && timeCell.value
                ? timeCell.value.toUpperCase().trim()
                : undefined,
        multiplier: multiplier.value,
        description: selectedParam
            ? `${currentSystem.value?.name ?? ''} - ${selectedParam.name}`
            : undefined,
        row_or_col: isHorizontal.value
            ? targetRow.value
            : isVertical.value
              ? targetCol.value
              : undefined,
    });

    emit('update:open', false);
}

function handleRemove() {
    emit('remove', props.cellCoordinate.toUpperCase());
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <span>{{ titleLabel }}</span>
                    <span
                        class="rounded bg-primary/10 px-2 py-0.5 font-mono text-base font-bold text-primary"
                    >
                        {{
                            isHorizontal
                                ? `Linha ${targetRow}`
                                : isVertical
                                  ? `Coluna ${targetCol}`
                                  : cellCoordinate
                        }}
                    </span>
                </DialogTitle>
                <DialogDescription>
                    {{ descriptionLabel }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-3">
                <!-- Informative Banner for Series Mode -->
                <div
                    v-if="isSeriesMode"
                    class="flex items-center gap-2 rounded-lg border border-sky-500/30 bg-sky-500/10 p-2.5 text-xs text-sky-800 dark:text-sky-200"
                >
                    <span class="text-base">📅</span>
                    <span>
                        {{
                            isHorizontal
                                ? `Série Horizontal: Todos os valores da Linha ${targetRow} serão associados à data correspondente na Linha ${dateRow ?? '2'}.`
                                : `Série Vertical: Todos os valores da Coluna ${targetCol} serão associados à data correspondente na Coluna ${dateColumn ?? 'A'}.`
                        }}
                    </span>
                </div>

                <!-- Sistema -->
                <div class="grid gap-1.5">
                    <Label for="system-select">Sistema Monitorado</Label>
                    <select
                        id="system-select"
                        v-model="selectedSystemId"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option
                            v-for="system in systems"
                            :key="system.id"
                            :value="system.id"
                        >
                            {{ system.name }}
                        </option>
                    </select>
                </div>

                <!-- Parâmetro -->
                <div class="grid gap-1.5">
                    <Label for="parameter-select">Parâmetro de Medição</Label>
                    <select
                        id="parameter-select"
                        v-model="selectedParameterId"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option
                            v-for="param in availableParameters"
                            :key="param.id"
                            :value="param.id"
                        >
                            {{ param.name }}
                            {{ param.unit ? `(${param.unit})` : '' }}
                            {{ param.code ? `[${param.code}]` : '' }}
                        </option>
                    </select>
                </div>

                <!-- Multiplicador / Fator de Escala -->
                <div class="grid gap-2 border-t pt-2">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs">Fator Multiplicador</Label>
                        <span class="font-mono text-xs font-bold text-primary">
                            x{{ multiplier }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="preset in multiplierPresets"
                            :key="preset.label"
                            type="button"
                            :class="[
                                'rounded border px-2.5 py-1 text-xs font-medium transition-colors',
                                multiplier === preset.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-muted/50 text-muted-foreground hover:bg-muted',
                            ]"
                            @click="applyMultiplierPreset(preset.value)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <span class="text-xs text-muted-foreground"
                            >Personalizado:</span
                        >
                        <Input
                            v-model="customMultiplier"
                            class="h-8 w-32 font-mono text-xs"
                            placeholder="ex: 0.05"
                            @input="onCustomMultiplierChange"
                        />
                    </div>
                </div>

                <!-- Referências de Data e Hora (Apenas no modo Células Fixas) -->
                <div
                    v-if="!isSeriesMode"
                    class="grid grid-cols-2 gap-3 border-t pt-1"
                >
                    <div class="grid gap-1.5">
                        <Label for="date-cell" class="text-xs"
                            >Célula de Data (opcional)</Label
                        >
                        <Input
                            id="date-cell"
                            v-model="dateCell"
                            class="h-8 font-mono text-xs uppercase"
                            placeholder="ex: A7 ou B6"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="time-cell" class="text-xs"
                            >Célula de Hora (opcional)</Label
                        >
                        <Input
                            id="time-cell"
                            v-model="timeCell"
                            class="h-8 font-mono text-xs uppercase"
                            placeholder="ex: C7"
                        />
                    </div>
                </div>
            </div>

            <DialogFooter class="flex sm:justify-between">
                <div>
                    <Button
                        v-if="existingMapping"
                        variant="destructive"
                        size="sm"
                        type="button"
                        @click="handleRemove"
                    >
                        Remover Mapeamento
                    </Button>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        type="button"
                        @click="emit('update:open', false)"
                    >
                        Cancelar
                    </Button>
                    <Button size="sm" type="button" @click="handleSave">
                        Salvar Mapeamento
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
