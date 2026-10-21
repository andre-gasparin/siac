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
import type {
    AverageInfo,
    DataRow,
    ParameterItem,
    SystemColorStyle,
} from '@/features/data-table/types';
import EwmaAlert from '@/shared/components/EwmaAlert.vue';

defineProps<{
    parameters: ParameterItem[];
    visibleParameters: ParameterItem[];
    rows: DataRow[];
    averages: Record<number, AverageInfo>;
    isMultiSystem: boolean;
    isLoading: boolean;
    fitToScreen: boolean;
    systemColorMap: Record<number, SystemColorStyle>;
    isSystemCollapsed: (sysId: number) => boolean;
    getSystemStyle: (sysId: number) => SystemColorStyle;
    confirmingDeleteTimestamp: string | null;
    isDeletingRow: string | null;
    isEditingThisCell: (timestamp: string, paramId: number) => boolean;
    isEditingRowDate: (timestamp: string) => boolean;
}>();

const emit = defineEmits<{
    toggleCollapseSystem: [sysId: number];
    startDeleteRow: [timestamp: string];
    cancelDeleteRow: [];
    confirmDeleteRow: [row: DataRow];
    openCellEdit: [row: DataRow, param: ParameterItem, event: MouseEvent];
    openRowDateEdit: [row: DataRow, event: MouseEvent];
    openDetails: [row: DataRow, tab: 'history' | 'comments'];
    openChart: [parameter: ParameterItem, type: 'line' | 'bar'];
}>();

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
                class="w-full border-collapse text-center text-[11px]"
                :class="fitToScreen ? 'min-w-[500px] table-fixed' : 'min-w-max'"
                data-test="data-table-grid"
            >
                <colgroup>
                    <col class="w-20" />
                    <col class="w-20" />
                    <col class="w-12" />
                    <col v-for="param in visibleParameters" :key="param.id" />
                </colgroup>
                <thead class="sticky top-0 z-20 bg-card shadow-sm">
                    <tr v-if="isMultiSystem" class="border-b">
                        <th colspan="3" class="border-r bg-card"></th>
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-1 py-1 text-[10px] font-bold wrap-anywhere"
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
                    <tr class="border-b">
                        <th class="border-r bg-card px-1 py-2">Ações</th>
                        <th class="border-r bg-card px-1 py-2">Data</th>
                        <th class="border-r bg-card px-1 py-2">Hora</th>
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-1 py-2 align-top"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                                !fitToScreen && 'min-w-[90px]',
                            ]"
                        >
                            <div class="space-y-0.5 wrap-anywhere">
                                <div class="leading-tight font-bold">
                                    {{ param.name }}
                                </div>
                                <div
                                    v-if="param.tag || param.code"
                                    class="text-[10px] font-medium text-primary"
                                >
                                    {{ param.tag || param.code }}
                                </div>
                                <div
                                    class="text-[10px] font-normal text-muted-foreground"
                                >
                                    {{ param.unit }}
                                </div>
                                <div class="flex justify-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded p-1 text-muted-foreground hover:bg-accent hover:text-primary"
                                        :aria-label="
                                            'Gráfico de linha de ' + param.name
                                        "
                                        @click="
                                            emit('openChart', param, 'line')
                                        "
                                    >
                                        <LineChart class="size-3" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-muted-foreground hover:bg-accent hover:text-primary"
                                        :aria-label="
                                            'Gráfico de barras de ' + param.name
                                        "
                                        @click="emit('openChart', param, 'bar')"
                                    >
                                        <ChartColumn class="size-3" />
                                    </button>
                                </div>
                            </div>
                        </th>
                    </tr>
                    <tr
                        v-for="limit in ['min', 'max'] as const"
                        :key="limit"
                        class="border-b text-[10px] text-muted-foreground"
                    >
                        <th
                            colspan="3"
                            class="border-r bg-card px-2 py-1 text-left font-medium"
                        >
                            Limite
                            {{ limit === 'min' ? 'mínimo' : 'máximo' }} Al.1
                        </th>
                        <th
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-1 py-1 font-normal wrap-anywhere"
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
                        v-for="(row, index) in rows"
                        :key="row.timestamp"
                        class="group/row border-b hover:[&>td]:bg-accent/70"
                        :class="{
                            '[&>td]:border-t-2 [&>td]:border-t-foreground/40':
                                index > 0 && row.date !== rows[index - 1].date,
                            'opacity-50': isDeletingRow === row.timestamp,
                        }"
                        :data-day-start="
                            index > 0 && row.date !== rows[index - 1].date
                        "
                    >
                        <td class="border-r bg-card px-1 py-1">
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
                                    class="rounded p-1 text-muted-foreground hover:text-primary"
                                    title="Histórico de alterações"
                                    @click="emit('openDetails', row, 'history')"
                                >
                                    <History class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:text-primary"
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
                            class="relative cursor-pointer border-r bg-card px-1 py-2 font-medium"
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
                            class="cursor-pointer border-r bg-card px-1 py-2 text-muted-foreground"
                            title="Editar data e hora"
                            @click="emit('openRowDateEdit', row, $event)"
                        >
                            {{ row.time }}
                        </td>
                        <td
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="group/cell relative cursor-pointer border-r px-1 py-2 text-center font-mono tabular-nums"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                                isOutside(row, param) &&
                                    'font-bold text-red-600 dark:text-red-400',
                                isEditingThisCell(row.timestamp, param.id) &&
                                    'ring-2 ring-primary ring-inset',
                            ]"
                            title="Clique para editar este valor"
                            @click="emit('openCellEdit', row, param, $event)"
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
                    <tr class="border-t-2 bg-muted font-bold">
                        <td class="border-r p-2">
                            <ArrowUpDown class="mx-auto size-3" />
                        </td>
                        <td colspan="2" class="border-r p-2">Média</td>
                        <td
                            v-for="param in visibleParameters"
                            :key="param.id"
                            class="border-r px-1 py-2 text-center font-mono wrap-anywhere tabular-nums"
                        >
                            {{ averages[param.id]?.formatted || 'SR' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
