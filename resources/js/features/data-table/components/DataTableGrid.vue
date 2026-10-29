<script setup lang="ts">
import {
    ArrowUpDown,
    ChartColumn,
    Check,
    Database,
    Eye,
    EyeOff,
    History,
    LineChart,
    Loader2,
    MessageSquare,
    Pencil,
    Trash2,
    X,
} from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type {
    AverageInfo,
    DataRow,
    ParameterItem,
    SelectedCellItem,
    SystemColorStyle,
} from '@/features/data-table/types';
import EwmaAlert from '@/shared/components/EwmaAlert.vue';

const props = withDefaults(
    defineProps<{
        parameters: ParameterItem[];
        visibleParameters: ParameterItem[];
        rows: DataRow[];
        averages: Record<number, AverageInfo>;
        isMultiSystem: boolean;
        isLoading: boolean;
        fitToScreen: boolean;
        batchSelectionEnabled?: boolean;
        isSavingBatch?: boolean;
        systemColorMap: Record<number, SystemColorStyle>;
        isSystemCollapsed: (sysId: number) => boolean;
        getSystemStyle: (sysId: number) => SystemColorStyle;
        confirmingDeleteTimestamp: string | null;
        isDeletingRow: string | null;
        isEditingThisCell: (timestamp: string, paramId: number) => boolean;
        isEditingRowDate: (timestamp: string) => boolean;
    }>(),
    {
        batchSelectionEnabled: false,
        isSavingBatch: false,
    },
);

const emit = defineEmits<{
    toggleCollapseSystem: [sysId: number];
    startDeleteRow: [timestamp: string];
    cancelDeleteRow: [];
    confirmDeleteRow: [row: DataRow];
    openCellEdit: [row: DataRow, param: ParameterItem, event: MouseEvent];
    openRowDateEdit: [row: DataRow, event: MouseEvent];
    openDetails: [row: DataRow, tab: 'history' | 'comments'];
    openChart: [parameter: ParameterItem, type: 'line' | 'bar'];
    applyBatchValue: [cells: SelectedCellItem[], value: string];
    clearBatchCells: [cells: SelectedCellItem[]];
}>();

const selectedCells = ref<SelectedCellItem[]>([]);
const selectedCellKeys = ref<Set<string>>(new Set());
const isDraggingSelection = ref(false);
const dragStartCoord = ref<{ rowIndex: number; colIndex: number } | null>(null);
const batchInputValue = ref('');

function cellKey(timestamp: string, parameterId: number): string {
    return `${timestamp}::${parameterId}`;
}

function isCellSelected(timestamp: string, parameterId: number): boolean {
    return selectedCellKeys.value.has(cellKey(timestamp, parameterId));
}

function updateRectangularSelection(
    startRow: number,
    startCol: number,
    endRow: number,
    endCol: number,
) {
    const minRow = Math.max(0, Math.min(startRow, endRow));
    const maxRow = Math.min(props.rows.length - 1, Math.max(startRow, endRow));
    const minCol = Math.max(0, Math.min(startCol, endCol));
    const maxCol = Math.min(
        props.visibleParameters.length - 1,
        Math.max(startCol, endCol),
    );

    const nextCells: SelectedCellItem[] = [];
    const nextKeys = new Set<string>();

    for (let r = minRow; r <= maxRow; r++) {
        const row = props.rows[r];

        if (!row) {
            continue;
        }

        for (let c = minCol; c <= maxCol; c++) {
            const param = props.visibleParameters[c];

            if (!param) {
                continue;
            }

            const key = cellKey(row.timestamp, param.id);
            nextKeys.add(key);
            nextCells.push({
                timestamp: row.timestamp,
                parameterId: param.id,
            });
        }
    }

    selectedCells.value = nextCells;
    selectedCellKeys.value = nextKeys;
}

function onCellMouseDown(
    rowIndex: number,
    colIndex: number,
    event: MouseEvent,
) {
    if (!props.batchSelectionEnabled || event.button !== 0) {
        return;
    }

    event.preventDefault();
    isDraggingSelection.value = true;
    dragStartCoord.value = { rowIndex, colIndex };
    updateRectangularSelection(rowIndex, colIndex, rowIndex, colIndex);
}

function onCellMouseOver(rowIndex: number, colIndex: number) {
    if (
        !props.batchSelectionEnabled ||
        !isDraggingSelection.value ||
        !dragStartCoord.value
    ) {
        return;
    }

    updateRectangularSelection(
        dragStartCoord.value.rowIndex,
        dragStartCoord.value.colIndex,
        rowIndex,
        colIndex,
    );
}

function onGlobalMouseUp() {
    if (isDraggingSelection.value) {
        isDraggingSelection.value = false;
    }
}

function onCellClick(row: DataRow, param: ParameterItem, event: MouseEvent) {
    if (props.batchSelectionEnabled) {
        return;
    }

    emit('openCellEdit', row, param, event);
}

function clearSelection() {
    selectedCells.value = [];
    selectedCellKeys.value = new Set();
    dragStartCoord.value = null;
    isDraggingSelection.value = false;
    batchInputValue.value = '';
}

function submitBatchValue() {
    if (
        !selectedCells.value.length ||
        batchInputValue.value.trim() === '' ||
        props.isSavingBatch
    ) {
        return;
    }

    emit('applyBatchValue', [...selectedCells.value], batchInputValue.value);
}

function submitClearBatch() {
    if (!selectedCells.value.length || props.isSavingBatch) {
        return;
    }

    emit('clearBatchCells', [...selectedCells.value]);
}

watch(
    () => [props.batchSelectionEnabled, props.rows, props.visibleParameters],
    () => {
        clearSelection();
    },
);

onMounted(() => {
    window.addEventListener('mouseup', onGlobalMouseUp);
});

onBeforeUnmount(() => {
    window.removeEventListener('mouseup', onGlobalMouseUp);
});

defineExpose({ clearSelection });

function formatValue(
    value: number | null | undefined,
    decimals: number,
): string {
    return value == null || !Number.isFinite(value)
        ? ''
        : value.toLocaleString('pt-BR', {
              minimumFractionDigits: decimals,
              maximumFractionDigits: decimals,
          });
}
function isOutside(row: DataRow, parameter: ParameterItem): boolean {
    const value = row.values[parameter.id];

    return (
        value != null &&
        ((parameter.alert_1_min != null && value < parameter.alert_1_min) ||
            (parameter.alert_1_max != null && value > parameter.alert_1_max))
    );
}
</script>

<template>
    <div
        class="flex w-full min-w-0 flex-col overflow-hidden rounded-lg border bg-card shadow-sm"
    >
        <div
            v-if="parameters.length"
            class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/20 px-3 py-2 text-xs"
        >
            <div class="flex flex-wrap items-center gap-2">
                <strong>Sistema:</strong>
                <strong v-if="!isMultiSystem" class="text-primary">{{
                    parameters[0]?.system_name
                }}</strong>
                <template v-else>
                    <button
                        v-for="(style, systemId) in systemColorMap"
                        :key="systemId"
                        type="button"
                        class="inline-flex items-center gap-1 rounded border px-2 py-1 text-[11px]"
                        :class="
                            isSystemCollapsed(Number(systemId))
                                ? 'border-dashed text-muted-foreground line-through'
                                : [
                                      style.headerBg,
                                      style.headerText,
                                      style.borderCol,
                                  ]
                        "
                        :title="
                            isSystemCollapsed(Number(systemId))
                                ? 'Exibir sistema'
                                : 'Ocultar sistema'
                        "
                        @click="emit('toggleCollapseSystem', Number(systemId))"
                    >
                        <EyeOff
                            v-if="isSystemCollapsed(Number(systemId))"
                            class="size-3"
                        /><Eye v-else class="size-3" />
                        {{
                            parameters.find(
                                (p) =>
                                    p.monitored_system_id === Number(systemId),
                            )?.system_name
                        }}
                    </button>
                </template>
            </div>
            <span class="text-muted-foreground"
                >{{ visibleParameters.length }} de
                {{ parameters.length }} colunas</span
            >
        </div>
        <div
            v-if="isLoading"
            class="flex items-center justify-center gap-2 p-10 text-xs text-muted-foreground"
        >
            <Loader2 class="size-5 animate-spin" />Carregando dados da tabela...
        </div>
        <div
            v-else-if="!parameters.length"
            class="p-10 text-center text-xs text-muted-foreground"
        >
            <Database class="mx-auto mb-3 size-5" />Selecione os sistemas e o
            período e clique em Carregar.
        </div>
        <div
            v-else
            class="relative max-h-[calc(100vh-210px)] w-full overflow-auto"
        >
            <table
                class="w-full border-collapse text-center text-xs"
                :class="fitToScreen ? 'min-w-[500px] table-fixed' : 'min-w-max'"
                data-test="data-table-grid"
            >
                <colgroup>
                    <col class="w-24" />
                    <col class="w-24" />
                    <col class="w-16" />
                    <col v-for="param in visibleParameters" :key="param.id" />
                </colgroup>
                <thead class="sticky top-0 z-20 bg-card shadow-sm">
                    <tr v-if="isMultiSystem" class="border-b">
                        <th colspan="3" class="border-r bg-card"></th>
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-2 py-1 text-[11px] font-bold wrap-anywhere"
                            :class="[
                                getSystemStyle(param.monitored_system_id)
                                    .headerBg,
                                getSystemStyle(param.monitored_system_id)
                                    .headerText,
                            ]"
                        >
                            {{ param.system_name }}
                        </th>
                    </tr>
                    <!-- Linha 1: Nome do Parâmetro + colunas fixas com rowspan="4" -->
                    <tr class="border-b">
                        <th
                            rowspan="4"
                            class="border-r bg-card px-2 py-2 align-middle text-xs font-semibold"
                        >
                            Ações
                        </th>
                        <th
                            rowspan="4"
                            class="border-r bg-card px-2 py-2 align-middle text-xs font-semibold"
                        >
                            Data
                        </th>
                        <th
                            rowspan="4"
                            class="border-r bg-card px-2 py-2 align-middle text-xs font-semibold"
                        >
                            Hora
                        </th>
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-2 pt-2 pb-1 align-middle text-xs leading-tight font-bold wrap-anywhere"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                                !fitToScreen && 'min-w-[96px]',
                            ]"
                        >
                            {{ param.name }}
                        </th>
                    </tr>
                    <!-- Linha 2: Tag / Código -->
                    <tr class="border-b">
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-2 py-1 align-middle text-[11px] font-medium wrap-anywhere text-primary"
                            :class="
                                getSystemStyle(param.monitored_system_id).colBg
                            "
                        >
                            {{ param.tag || param.code || '—' }}
                        </th>
                    </tr>
                    <!-- Linha 3: Unidade -->
                    <tr class="border-b">
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-2 py-1 align-middle text-[11px] font-normal wrap-anywhere text-muted-foreground"
                            :class="
                                getSystemStyle(param.monitored_system_id).colBg
                            "
                        >
                            {{ param.unit || '—' }}
                        </th>
                    </tr>
                    <!-- Linha 4: Ícones de Ação (Gráfico de Linha e Gráfico de Coluna/Barra) -->
                    <tr class="border-b">
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-2 py-1.5 align-middle"
                            :class="
                                getSystemStyle(param.monitored_system_id).colBg
                            "
                        >
                            <div class="flex items-center justify-center gap-1">
                                <button
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:bg-accent hover:text-primary"
                                    :aria-label="
                                        'Gráfico de linha de ' + param.name
                                    "
                                    @click="emit('openChart', param, 'line')"
                                >
                                    <LineChart class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:bg-accent hover:text-primary"
                                    :aria-label="
                                        'Gráfico de barras de ' + param.name
                                    "
                                    @click="emit('openChart', param, 'bar')"
                                >
                                    <ChartColumn class="size-3.5" />
                                </button>
                            </div>
                        </th>
                    </tr>
                    <tr
                        v-for="limit in ['min', 'max'] as const"
                        :key="limit"
                        class="border-b text-[11px] text-muted-foreground"
                    >
                        <th
                            colspan="3"
                            class="border-r bg-card px-2 py-1.5 text-left font-medium"
                        >
                            Limite
                            {{ limit === 'min' ? 'mínimo' : 'máximo' }} Al.1
                        </th>
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-2 py-1.5 font-normal wrap-anywhere tabular-nums"
                            :class="
                                getSystemStyle(param.monitored_system_id).colBg
                            "
                        >
                            {{
                                formatValue(
                                    limit === 'min'
                                        ? param.alert_1_min
                                        : param.alert_1_max,
                                    param.decimals,
                                )
                            }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, rowIndex) in rows"
                        :key="row.timestamp"
                        class="group/row border-b hover:[&>td]:bg-accent/70"
                        :class="{
                            '[&>td]:border-t-2 [&>td]:border-t-foreground/40':
                                rowIndex > 0 &&
                                row.date !== rows[rowIndex - 1].date,
                            'opacity-50': isDeletingRow === row.timestamp,
                        }"
                        :data-day-start="
                            rowIndex > 0 && row.date !== rows[rowIndex - 1].date
                        "
                    >
                        <td class="border-r bg-card px-2 py-2">
                            <div
                                class="flex items-center justify-center gap-0.5"
                            >
                                <template
                                    v-if="
                                        confirmingDeleteTimestamp ===
                                        row.timestamp
                                    "
                                >
                                    <button
                                        type="button"
                                        class="rounded p-1 text-destructive"
                                        title="Confirmar exclusão"
                                        :disabled="isDeletingRow !== null"
                                        @click="emit('confirmDeleteRow', row)"
                                    >
                                        <Check class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded p-1"
                                        title="Cancelar exclusão"
                                        @click="emit('cancelDeleteRow')"
                                    >
                                        <X class="size-3.5" />
                                    </button>
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:text-destructive"
                                    title="Excluir linha"
                                    :disabled="isDeletingRow !== null"
                                    @click="
                                        emit('startDeleteRow', row.timestamp)
                                    "
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 transition-colors hover:text-primary"
                                    :class="
                                        row.has_modifications
                                            ? 'bg-amber-500/10 font-bold text-amber-600 dark:text-amber-400'
                                            : 'text-muted-foreground'
                                    "
                                    title="Histórico de alterações"
                                    @click="emit('openDetails', row, 'history')"
                                >
                                    <History class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 transition-colors hover:text-primary"
                                    :class="
                                        row.has_comments
                                            ? 'bg-blue-500/10 font-bold text-blue-600 dark:text-blue-400'
                                            : 'text-muted-foreground'
                                    "
                                    title="Comentários"
                                    @click="
                                        emit('openDetails', row, 'comments')
                                    "
                                >
                                    <MessageSquare class="size-3.5" />
                                </button>
                            </div>
                        </td>
                        <td
                            class="relative cursor-pointer border-r bg-card px-2 py-2 text-xs font-medium tabular-nums"
                            :class="{
                                'ring-2 ring-primary ring-inset':
                                    isEditingRowDate(row.timestamp),
                            }"
                            title="Editar data e hora"
                            @click="emit('openRowDateEdit', row, $event)"
                        >
                            {{ row.date }}
                        </td>
                        <td
                            class="cursor-pointer border-r bg-card px-2 py-2 text-xs text-muted-foreground tabular-nums"
                            title="Editar data e hora"
                            @click="emit('openRowDateEdit', row, $event)"
                        >
                            {{ row.time }}
                        </td>
                        <td
                            v-for="(param, colIndex) in visibleParameters"
                            :key="param.id"
                            class="group/cell relative cursor-pointer border-r px-2 py-2 text-center font-mono text-xs font-medium tabular-nums md:text-[13px]"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                                isOutside(row, param) &&
                                    'font-bold text-red-600 dark:text-red-400',
                                isEditingThisCell(row.timestamp, param.id) &&
                                    'ring-2 ring-primary ring-inset',
                                batchSelectionEnabled && 'select-none',
                                isCellSelected(row.timestamp, param.id) &&
                                    'bg-primary/20 ring-2 ring-primary ring-inset',
                            ]"
                            :title="
                                batchSelectionEnabled
                                    ? 'Clique e arraste para selecionar células em lote'
                                    : 'Clique para editar este valor'
                            "
                            @mousedown="
                                onCellMouseDown(rowIndex, colIndex, $event)
                            "
                            @mouseover="onCellMouseOver(rowIndex, colIndex)"
                            @click="onCellClick(row, param, $event)"
                        >
                            <span class="block wrap-anywhere">{{
                                formatValue(
                                    row.values[param.id],
                                    param.decimals,
                                )
                            }}</span>
                            <span
                                v-if="row.ewma?.[param.id]"
                                class="absolute top-0.5 right-0.5"
                                ><EwmaAlert
                                    :result="row.ewma[param.id]"
                                    :parameter-name="param.name"
                            /></span>
                            <Pencil
                                v-else
                                class="pointer-events-none absolute top-0.5 right-0.5 size-2.5 opacity-0 group-hover/cell:opacity-60"
                            />
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td
                            :colspan="visibleParameters.length + 3"
                            class="p-6 text-muted-foreground"
                        >
                            Sem registros de leitura no período selecionado.
                        </td>
                    </tr>
                </tbody>
                <tfoot class="sticky bottom-0 z-10 bg-card shadow-sm">
                    <tr class="border-t-2 bg-muted text-xs font-bold">
                        <td class="border-r px-2 py-2">
                            <ArrowUpDown class="mx-auto size-3.5" />
                        </td>
                        <td colspan="2" class="border-r px-2 py-2">Média</td>
                        <td
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-2 py-2 text-center font-mono text-xs font-medium wrap-anywhere tabular-nums md:text-[13px]"
                        >
                            {{ averages[param.id]?.formatted || 'SR' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Barra flutuante inferior fixa para seleção em lote -->
        <div
            v-if="batchSelectionEnabled && selectedCells.length > 0"
            class="fixed bottom-4 left-1/2 z-50 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 flex-wrap items-center gap-2 rounded-lg border bg-card px-4 py-2.5 text-xs shadow-xl"
            data-test="batch-selection-bar"
        >
            <span
                class="rounded-full bg-primary/15 px-2.5 py-1 font-semibold text-primary"
            >
                {{ selectedCells.length }}
                {{
                    selectedCells.length === 1
                        ? 'célula selecionada'
                        : 'células selecionadas'
                }}
            </span>
            <div class="flex items-center gap-1.5">
                <input
                    v-model="batchInputValue"
                    type="text"
                    inputmode="decimal"
                    placeholder="Novo valor"
                    :disabled="isSavingBatch"
                    class="h-8 w-28 rounded-md border bg-background px-2 text-xs tabular-nums focus:ring-2 focus:ring-primary focus:outline-none"
                    @keydown.enter.prevent="submitBatchValue"
                />
                <button
                    type="button"
                    :disabled="isSavingBatch || batchInputValue.trim() === ''"
                    class="inline-flex h-8 items-center gap-1 rounded-md bg-primary px-3 font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    @click="submitBatchValue"
                >
                    <Loader2
                        v-if="isSavingBatch"
                        class="size-3.5 animate-spin"
                    />
                    Aplicar valor
                </button>
            </div>
            <button
                type="button"
                :disabled="isSavingBatch"
                class="inline-flex h-8 items-center gap-1 rounded-md border border-destructive/40 bg-destructive/10 px-3 font-medium text-destructive hover:bg-destructive/20 disabled:opacity-50"
                @click="submitClearBatch"
            >
                <Trash2 class="size-3.5" />
                Limpar selecionadas
            </button>
            <button
                type="button"
                :disabled="isSavingBatch"
                class="inline-flex h-8 items-center gap-1 rounded-md border px-2.5 text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-50"
                @click="clearSelection"
            >
                <X class="size-3.5" />
                Cancelar
            </button>
        </div>
    </div>
</template>
