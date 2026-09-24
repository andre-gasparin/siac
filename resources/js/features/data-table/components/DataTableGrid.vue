<script setup lang="ts">
import {
    ArrowUpDown,
    Check,
    Database,
    Eye,
    EyeOff,
    Info,
    LineChart,
    Loader2,
    MessageSquare,
    Pencil,
    Trash2,
    X as XIcon,
} from '@lucide/vue';
import type {
    AverageInfo,
    DataRow,
    ParameterItem,
    SystemColorStyle,
} from '@/features/data-table/types';

defineProps<{
    parameters: ParameterItem[];
    visibleParameters: ParameterItem[];
    rows: DataRow[];
    averages: Record<number, AverageInfo>;
    isMultiSystem: boolean;
    isLoading: boolean;
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
}>();

function formatValue(val: number | null | undefined, decimals: number): string {
    if (val === null || val === undefined || isNaN(val)) {
        return '';
    }

    return val.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function formatLimit(val: number | null | undefined, decimals: number): string {
    if (val === null || val === undefined || isNaN(val)) {
        return '';
    }

    return val.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}
</script>

<template>
    <div
        class="flex w-full min-w-0 flex-col overflow-hidden rounded-lg border bg-card shadow-sm"
    >
        <!-- Systems Summary Header Bar if loaded -->
        <div
            v-if="parameters.length > 0"
            class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b bg-muted/20 px-3 py-2 text-xs font-medium"
        >
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-foreground">Sistema:</span>
                <span v-if="!isMultiSystem" class="font-bold text-primary">
                    {{ parameters[0]?.system_name }}
                </span>
                <span v-else class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="(sysColor, sysId) in systemColorMap"
                        :key="sysId"
                        type="button"
                        @click="emit('toggleCollapseSystem', Number(sysId))"
                        class="inline-flex cursor-pointer items-center gap-1 rounded border px-2 py-1 text-[11px] font-semibold transition-all select-none hover:scale-[1.02]"
                        :class="
                            isSystemCollapsed(Number(sysId))
                                ? 'border-dashed border-muted-foreground/40 bg-muted/80 text-muted-foreground line-through opacity-70'
                                : [
                                      sysColor.headerBg,
                                      sysColor.headerText,
                                      sysColor.borderCol,
                                  ]
                        "
                        :title="
                            isSystemCollapsed(Number(sysId))
                                ? 'Clique para reexibir colunas'
                                : 'Clique para ocultar colunas'
                        "
                    >
                        <EyeOff
                            v-if="isSystemCollapsed(Number(sysId))"
                            class="h-3 w-3 opacity-60"
                        />
                        <Eye v-else class="h-3 w-3 opacity-70" />
                        <span>{{
                            parameters.find(
                                (p) => p.monitored_system_id === Number(sysId),
                            )?.system_name
                        }}</span>
                    </button>
                </span>
            </div>
            <div class="text-[11px] text-muted-foreground">
                Exibindo {{ visibleParameters.length }} de
                {{ parameters.length }} colunas
            </div>
        </div>

        <!-- Empty State Prompt Before Clicking Carregar -->
        <div
            v-if="parameters.length === 0 && !isLoading"
            class="p-10 text-center text-muted-foreground"
        >
            <div class="mb-3 flex justify-center">
                <div
                    class="flex size-9 items-center justify-center rounded-md bg-muted/50"
                >
                    <Database class="size-4 text-muted-foreground/70" />
                </div>
            </div>
            <p class="text-xs font-medium">
                Selecione o(s) sistema(s) e o intervalo de datas acima.
            </p>
            <p class="mt-1 text-[11px] text-muted-foreground/80">
                Clique no botão
                <span
                    class="font-semibold text-emerald-600 dark:text-emerald-400"
                    >"Carregar"</span
                >
                para visualizar a tabela de dados.
            </p>
        </div>

        <!-- Loading State -->
        <div
            v-else-if="isLoading"
            class="flex flex-col items-center justify-center gap-2 p-10 text-center text-muted-foreground"
        >
            <Loader2
                class="h-5 w-5 animate-spin text-emerald-600 dark:text-emerald-400"
            />
            <p class="text-xs font-medium">Carregando dados da tabela...</p>
        </div>

        <!-- Table with Sticky Header & Standardized Scrollbar -->
        <div
            v-else-if="parameters.length > 0"
            class="relative max-h-[calc(100vh-210px)] w-full overflow-x-auto overflow-y-auto"
        >
            <table
                class="w-full min-w-max border-collapse text-left text-[11px]"
            >
                <thead class="sticky top-0 z-20 bg-card shadow-sm">
                    <!-- Header Row 1: System Names -->
                    <tr
                        v-if="isMultiSystem"
                        class="border-b bg-muted/90 font-semibold backdrop-blur-sm"
                    >
                        <th
                            class="w-8 border-r bg-card px-1 py-1 text-center"
                            rowspan="1"
                        ></th>
                        <th
                            class="w-20 border-r bg-card px-2 py-1"
                            rowspan="1"
                        ></th>
                        <th
                            class="w-12 border-r bg-card px-2 py-1"
                            rowspan="1"
                        ></th>

                        <th
                            v-for="param in visibleParameters"
                            :key="'sys-' + param.id"
                            @click="
                                emit(
                                    'toggleCollapseSystem',
                                    param.monitored_system_id,
                                )
                            "
                            class="cursor-pointer border-r px-2 py-1 text-center text-[10px] font-bold tracking-tight uppercase transition-all select-none hover:brightness-95"
                            :class="[
                                getSystemStyle(param.monitored_system_id)
                                    .headerBg,
                                getSystemStyle(param.monitored_system_id)
                                    .headerText,
                                getSystemStyle(param.monitored_system_id)
                                    .borderCol,
                            ]"
                            title="Clique para ocultar colunas deste sistema"
                        >
                            {{ param.system_name }}
                        </th>

                        <th class="w-12 bg-card px-1 py-1" rowspan="1"></th>
                    </tr>

                    <!-- Header Row 2: Parameter Names & Units -->
                    <tr class="border-b bg-card font-semibold">
                        <th class="w-8 border-r bg-card px-1 py-1 text-center">
                            <Trash2
                                class="mx-auto h-3 w-3 text-muted-foreground"
                                title="Excluir linha"
                            />
                        </th>
                        <th
                            class="w-20 border-r bg-card px-2 py-1 text-foreground"
                        >
                            Data
                        </th>
                        <th
                            class="w-12 border-r bg-card px-2 py-1 text-foreground"
                        >
                            Hora
                        </th>

                        <th
                            v-for="param in visibleParameters"
                            :key="'param-' + param.id"
                            class="min-w-[90px] border-r px-2 py-1 text-center transition-colors"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                            ]"
                        >
                            <div class="flex flex-col items-center gap-0.5">
                                <span
                                    class="leading-tight font-bold text-foreground"
                                    >{{ param.name }}</span
                                >
                                <span
                                    v-if="param.unit"
                                    class="text-[10px] leading-tight font-normal text-muted-foreground"
                                >
                                    {{ param.unit }}
                                </span>
                                <div
                                    class="flex items-center gap-1 text-muted-foreground"
                                >
                                    <LineChart
                                        class="h-2.5 w-2.5 cursor-pointer transition-colors hover:text-primary"
                                        title="Ver Gráfico"
                                    />
                                </div>
                            </div>
                        </th>

                        <th class="w-12 bg-card px-1 py-1 text-center"></th>
                    </tr>

                    <!-- Header Row 3: Limite Min Al.1 -->
                    <tr class="border-b bg-card text-muted-foreground">
                        <th class="border-r bg-card px-1 py-0.5"></th>
                        <th
                            class="border-r bg-card px-2 py-0.5 text-[10px] font-medium"
                            colspan="2"
                        >
                            Limite Min Al.1
                        </th>
                        <th
                            v-for="param in visibleParameters"
                            :key="'min-' + param.id"
                            class="border-r px-2 py-0.5 text-center text-[10px] font-normal"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                            ]"
                        >
                            {{ formatLimit(param.alert_1_min, param.decimals) }}
                        </th>
                        <th class="bg-card px-1 py-0.5"></th>
                    </tr>

                    <!-- Header Row 4: Limite Max Al.1 -->
                    <tr class="border-b bg-card text-muted-foreground">
                        <th class="border-r bg-card px-1 py-0.5"></th>
                        <th
                            class="border-r bg-card px-2 py-0.5 text-[10px] font-medium"
                            colspan="2"
                        >
                            Limite Max Al.1
                        </th>
                        <th
                            v-for="param in visibleParameters"
                            :key="'max-' + param.id"
                            class="border-r px-2 py-0.5 text-center text-[10px] font-normal"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                            ]"
                        >
                            {{ formatLimit(param.alert_1_max, param.decimals) }}
                        </th>
                        <th class="bg-card px-1 py-0.5"></th>
                    </tr>
                </thead>

                <!-- Data Body -->
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.timestamp"
                        class="border-b transition-colors hover:bg-muted/30"
                    >
                        <!-- Delete Row Column with Confirmation Check -->
                        <td class="border-r bg-card px-1 py-1 text-center">
                            <Loader2
                                v-if="isDeletingRow === row.timestamp"
                                class="mx-auto h-3.5 w-3.5 animate-spin text-muted-foreground"
                            />

                            <div
                                v-else-if="
                                    confirmingDeleteTimestamp === row.timestamp
                                "
                                class="flex items-center justify-center gap-1"
                            >
                                <button
                                    type="button"
                                    @click.stop="emit('confirmDeleteRow', row)"
                                    class="rounded p-0.5 font-bold text-emerald-600 transition-all hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950"
                                    title="Confirmar exclusão da linha"
                                >
                                    <Check
                                        class="mx-auto h-3.5 w-3.5 stroke-[2.5]"
                                    />
                                </button>
                                <button
                                    type="button"
                                    @click.stop="emit('cancelDeleteRow')"
                                    class="rounded p-0.5 text-muted-foreground transition-all hover:bg-muted hover:text-foreground"
                                    title="Cancelar"
                                >
                                    <XIcon class="mx-auto h-3 w-3" />
                                </button>
                            </div>

                            <button
                                v-else
                                type="button"
                                @click.stop="
                                    emit('startDeleteRow', row.timestamp)
                                "
                                class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                title="Excluir esta linha de leituras"
                            >
                                <Trash2 class="mx-auto h-3.5 w-3.5" />
                            </button>
                        </td>

                        <!-- Date Column -->
                        <td
                            @click="emit('openRowDateEdit', row, $event)"
                            class="group cursor-pointer border-r bg-card px-2 py-1 font-medium whitespace-nowrap text-foreground transition-colors hover:bg-emerald-50/70 dark:hover:bg-emerald-950/40"
                            :class="{
                                'bg-emerald-100/90 font-bold ring-2 ring-emerald-500 ring-inset dark:bg-emerald-900/70':
                                    isEditingRowDate(row.timestamp),
                            }"
                            title="Clique para editar a data/hora da linha"
                        >
                            <div class="flex items-center gap-1">
                                <span>{{ row.date }}</span>
                                <Pencil
                                    class="h-2.5 w-2.5 text-emerald-600 opacity-0 transition-opacity group-hover:opacity-70 dark:text-emerald-400"
                                />
                            </div>
                        </td>

                        <!-- Time Column -->
                        <td
                            @click="emit('openRowDateEdit', row, $event)"
                            class="group cursor-pointer border-r bg-card px-2 py-1 whitespace-nowrap text-muted-foreground transition-colors hover:bg-emerald-50/70 dark:hover:bg-emerald-950/40"
                            :class="{
                                'bg-emerald-100/90 font-bold ring-2 ring-emerald-500 ring-inset dark:bg-emerald-900/70':
                                    isEditingRowDate(row.timestamp),
                            }"
                            title="Clique para editar a data/hora da linha"
                        >
                            <div class="flex items-center gap-1">
                                <span>{{ row.time }}</span>
                                <Pencil
                                    class="h-2.5 w-2.5 text-emerald-600 opacity-0 transition-opacity group-hover:opacity-70 dark:text-emerald-400"
                                />
                            </div>
                        </td>

                        <!-- Parameter Values -->
                        <td
                            v-for="param in visibleParameters"
                            :key="'val-' + param.id"
                            @click="emit('openCellEdit', row, param, $event)"
                            class="group relative cursor-pointer border-r px-2 py-1 text-center font-mono text-[11px] transition-all select-none hover:bg-emerald-50/80 hover:font-bold dark:hover:bg-emerald-950/60"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                                isEditingThisCell(row.timestamp, param.id)
                                    ? 'bg-emerald-100/90 font-bold ring-2 ring-emerald-500 ring-inset dark:bg-emerald-900/70'
                                    : '',
                            ]"
                            title="Clique para editar este valor"
                        >
                            <div class="flex items-center justify-center gap-1">
                                <span>{{
                                    formatValue(
                                        row.values[param.id],
                                        param.decimals,
                                    )
                                }}</span>
                                <Pencil
                                    class="h-2.5 w-2.5 text-emerald-600 opacity-0 transition-opacity group-hover:opacity-70 dark:text-emerald-400"
                                />
                            </div>
                        </td>

                        <!-- Actions (Info / Comment) -->
                        <td
                            class="bg-card px-1 py-1 text-center whitespace-nowrap"
                        >
                            <div
                                class="flex items-center justify-center gap-1 text-muted-foreground"
                            >
                                <Info
                                    class="h-3 w-3 cursor-pointer transition-colors hover:text-primary"
                                    title="Informações"
                                />
                                <MessageSquare
                                    class="h-3 w-3 cursor-pointer transition-colors hover:text-primary"
                                    title="Observações"
                                />
                            </div>
                        </td>
                    </tr>

                    <!-- Empty rows message if parameters exist but no readings in period -->
                    <tr v-if="rows.length === 0">
                        <td
                            :colspan="visibleParameters.length + 4"
                            class="p-6 text-center text-xs text-muted-foreground"
                        >
                            Sem registros de leitura no período selecionado.
                        </td>
                    </tr>
                </tbody>

                <!-- Table Footer: Média (Sticky Bottom) -->
                <tfoot class="sticky bottom-0 z-10 bg-card shadow-md">
                    <tr
                        class="border-t-2 bg-muted/90 font-bold backdrop-blur-sm"
                    >
                        <td class="border-r px-1 py-1 text-center">
                            <ArrowUpDown
                                class="mx-auto h-3 w-3 text-muted-foreground"
                            />
                        </td>
                        <td
                            class="border-r px-2 py-1 text-foreground"
                            colspan="2"
                        >
                            Média
                        </td>
                        <td
                            v-for="param in visibleParameters"
                            :key="'avg-' + param.id"
                            class="border-r px-2 py-1 text-center font-mono text-[11px] text-foreground transition-colors"
                            :class="[
                                getSystemStyle(param.monitored_system_id).colBg,
                            ]"
                        >
                            {{ averages[param.id]?.formatted || 'SR' }}
                        </td>
                        <td class="px-1 py-1"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
