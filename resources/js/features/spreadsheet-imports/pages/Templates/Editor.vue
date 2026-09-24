<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    Code,
    FileSpreadsheet,
    Maximize2,
    Minimize2,
    Plus,
    RefreshCw,
    Save,
    Upload,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CellMappingModal from '@/features/spreadsheet-imports/components/CellMappingModal.vue';
import type { SystemOption } from '@/features/spreadsheet-imports/components/CellMappingModal.vue';
import JsonConfigDrawer from '@/features/spreadsheet-imports/components/JsonConfigDrawer.vue';
import PasteIncrementDialog from '@/features/spreadsheet-imports/components/PasteIncrementDialog.vue';
import SpreadsheetGrid from '@/features/spreadsheet-imports/components/SpreadsheetGrid.vue';
import type {
    CellMapping,
    SheetConfig,
    SpreadsheetTemplateItem,
    TemplateConfig,
} from '@/features/spreadsheet-imports/types';
import { index as spreadsheetImportsIndex } from '@/routes/spreadsheet-imports';
import {
    index as templatesIndex,
    samplePreview as templatesSamplePreview,
    store as templatesStore,
    update as templatesUpdate,
} from '@/routes/spreadsheet-imports/templates';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import type { Team } from '@/shared/types';

const props = defineProps<{
    template: SpreadsheetTemplateItem | null;
    systems: SystemOption[];
    currentTeam: Team;
}>();

const isEditing = computed(() => !!props.template);

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Importação de Planilhas',
            href: props.currentTeam
                ? spreadsheetImportsIndex.url({
                      current_team: props.currentTeam.slug,
                  })
                : '/',
        },
        {
            title: 'Modelos de Mapeamento',
            href: props.currentTeam
                ? templatesIndex.url({ current_team: props.currentTeam.slug })
                : '/',
        },
        {
            title: isEditing.value ? 'Editar Modelo' : 'Novo Modelo',
            href: '#',
        },
    ],
});

// Form state
const name = ref<string>(props.template?.name ?? '');
const description = ref<string>(props.template?.description ?? '');
const isActive = ref<boolean>(props.template?.is_active ?? true);
const isSubmitting = ref<boolean>(false);

// Template configuration schema state
const defaultConfig: TemplateConfig = {
    version: 1,
    empty_values_mode: 'ignore',
    ignore_empty_cells: true,
    sheets: [
        {
            sheet_identifier_type: 'index',
            sheet_identifier_value: 1,
            sheet_name: 'Aba 1',
            date_mode: 'horizontal_series',
            date_cell: 'A3',
            time_cell: '',
            date_column: 'A',
            date_row: 2,
            time_column: null,
            time_row: null,
            allowed_times: [],
            empty_values_mode: 'ignore',
            ignore_empty_cells: true,
            mappings: [],
        },
    ],
};

const config = ref<TemplateConfig>(
    props.template?.config?.sheets
        ? JSON.parse(JSON.stringify(props.template.config))
        : defaultConfig,
);

// Active Sheet Tab
const activeSheetIndex = ref<number>(0);
const activeSheet = computed<SheetConfig>(() => {
    if (!config.value.sheets[activeSheetIndex.value]) {
        return config.value.sheets[0];
    }

    return config.value.sheets[activeSheetIndex.value];
});

function handleEmptyValuesModeChange(val: string) {
    const isIgnore = val !== 'save_empty';

    if (activeSheet.value) {
        activeSheet.value.empty_values_mode = isIgnore
            ? 'ignore'
            : 'save_empty';
        activeSheet.value.ignore_empty_cells = isIgnore;
    }

    config.value.empty_values_mode = isIgnore ? 'ignore' : 'save_empty';
    config.value.ignore_empty_cells = isIgnore;
}

// Time filter input and methods
const newTimeInput = ref('');

function formatTimeInput(value: string): string | null {
    const clean = value.trim();

    if (!clean) {
        return null;
    }

    const match = clean.match(/^(\d{1,2})(?::(\d{1,2}))?$/);

    if (match) {
        const h = parseInt(match[1], 10);
        const m = match[2] ? parseInt(match[2], 10) : 0;

        if (h >= 0 && h <= 23 && m >= 0 && m <= 59) {
            return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        }
    }

    return null;
}

function handleAddAllowedTime(e?: Event) {
    if (e) {
        e.preventDefault();
    }

    const val = newTimeInput.value;

    if (!val) {
        return;
    }

    if (!activeSheet.value.allowed_times) {
        activeSheet.value.allowed_times = [];
    }

    const parts = val.split(/[,;\s]+/);

    for (const part of parts) {
        const formatted = formatTimeInput(part);

        if (formatted && !activeSheet.value.allowed_times.includes(formatted)) {
            activeSheet.value.allowed_times.push(formatted);
        }
    }

    newTimeInput.value = '';
}

function handleRemoveAllowedTime(time: string) {
    if (!activeSheet.value.allowed_times) {
        return;
    }

    activeSheet.value.allowed_times = activeSheet.value.allowed_times.filter(
        (t) => t !== time,
    );
}

function handleKeydownTimeInput(e: KeyboardEvent) {
    if (e.key === ',' || e.key === 'Enter') {
        e.preventDefault();
        handleAddAllowedTime();
    }
}

// Expanded / Focus mode state
const isExpandedMode = ref(false);

// Modals / Dialogs state
const isMappingModalOpen = ref(false);
const selectedCellCoord = ref('A1');
const isJsonDrawerOpen = ref(false);
const isPasteDialogOpen = ref(false);

const pendingPastePayload = ref<{
    source: CellMapping;
    targetCoordinates: string[];
} | null>(null);

// Sample preview background state
const sampleFileInput = ref<HTMLInputElement | null>(null);
const sampleSheetsData = ref<
    Array<{
        index: number;
        name: string;
        highest_row: number;
        highest_column: string;
        cells: Record<string, any>;
    }>
>([]);
const isUploadingSample = ref(false);

const activeSampleCells = computed(() => {
    return sampleSheetsData.value[activeSheetIndex.value]?.cells ?? {};
});

const activeSampleHighestRow = computed(() => {
    return sampleSheetsData.value[activeSheetIndex.value]?.highest_row ?? 50;
});

const activeSampleHighestCol = computed(() => {
    const colStr =
        sampleSheetsData.value[activeSheetIndex.value]?.highest_column ?? 'Z';

    return Math.max(columnToNumber(colStr), 26);
});

// Cell Mapping Actions
const activeCellMapping = computed<CellMapping | null>(() => {
    return (
        activeSheet.value.mappings.find(
            (m) =>
                m.cell.toUpperCase() === selectedCellCoord.value.toUpperCase(),
        ) ?? null
    );
});

function openCellEditor(coord: string) {
    selectedCellCoord.value = coord.toUpperCase();
    isMappingModalOpen.value = true;
}

function handleSaveCellMapping(mapping: CellMapping) {
    const existingIdx = activeSheet.value.mappings.findIndex(
        (m) => m.cell.toUpperCase() === mapping.cell.toUpperCase(),
    );

    if (existingIdx >= 0) {
        activeSheet.value.mappings[existingIdx] = mapping;
    } else {
        activeSheet.value.mappings.push(mapping);
    }
}

function handleRemoveCellMapping(coord: string) {
    const idx = activeSheet.value.mappings.findIndex(
        (m) => m.cell.toUpperCase() === coord.toUpperCase(),
    );

    if (idx >= 0) {
        activeSheet.value.mappings.splice(idx, 1);
        toast.info(`Mapeamento da célula ${coord.toUpperCase()} removido.`);
    }
}

function handleRemoveMultipleMappings(coords: string[]) {
    const coordsSet = new Set(coords.map((c) => c.toUpperCase()));
    activeSheet.value.mappings = activeSheet.value.mappings.filter(
        (m) => !coordsSet.has(m.cell.toUpperCase()),
    );
    toast.info(`${coords.length} mapeamento(s) removido(s).`);
}

// Paste Handling
function onPasteTriggered(payload: {
    source: CellMapping;
    targetCoordinates: string[];
}) {
    pendingPastePayload.value = payload;
    const isSeries =
        activeSheet.value.date_mode === 'horizontal_series' ||
        activeSheet.value.date_mode === 'vertical_series' ||
        activeSheet.value.date_mode === 'upload_date_match' ||
        activeSheet.value.date_mode === 'all_dates_scan';

    if (isSeries) {
        handleConfirmPasteIncrement('fixed_date');
    } else {
        isPasteDialogOpen.value = true;
    }
}

function handleConfirmPasteIncrement(
    option: 'row_increment' | 'col_increment' | 'fixed_date',
) {
    if (!pendingPastePayload.value) {
        return;
    }

    const { source, targetCoordinates } = pendingPastePayload.value;
    const isHorizontal = activeSheet.value.date_mode === 'horizontal_series';
    const isVertical =
        activeSheet.value.date_mode !== 'horizontal_series' &&
        activeSheet.value.date_mode !== 'cell_reference';

    const sourceDateCell = source.date_cell ?? activeSheet.value.date_cell;
    const dateMatch = sourceDateCell
        ? sourceDateCell.match(/^([A-Z]+)(\d+)$/)
        : null;

    for (let i = 0; i < targetCoordinates.length; i++) {
        const targetCoord = targetCoordinates[i];
        let newDateCell = sourceDateCell;

        if (dateMatch && option === 'row_increment') {
            const baseCol = dateMatch[1];
            const baseRow = parseInt(dateMatch[2], 10);
            newDateCell = `${baseCol}${baseRow + i + 1}`;
        } else if (dateMatch && option === 'col_increment') {
            const baseCol = dateMatch[1];
            const baseRow = parseInt(dateMatch[2], 10);
            const nextColNum = columnToNumber(baseCol) + i + 1;
            newDateCell = `${numberToColumn(nextColNum)}${baseRow}`;
        }

        let rowOrCol: number | string | undefined = undefined;

        if (isHorizontal) {
            const m = targetCoord.match(/\d+$/);

            if (m) {
                rowOrCol = parseInt(m[0], 10);
            }
        } else if (isVertical) {
            const m = targetCoord.match(/^[A-Z]+/i);

            if (m) {
                rowOrCol = m[0].toUpperCase();
            }
        }

        const newMapping: CellMapping = {
            cell: targetCoord.toUpperCase(),
            monitored_system_id: source.monitored_system_id,
            parameter_id: source.parameter_id,
            multiplier: source.multiplier ?? 1,
            description: source.description,
            date_cell:
                !isHorizontal && !isVertical && newDateCell
                    ? newDateCell.toUpperCase()
                    : undefined,
            row_or_col: rowOrCol,
        };

        handleSaveCellMapping(newMapping);
    }

    toast.success(
        targetCoordinates.length === 1
            ? `Mapeamento colado em ${targetCoordinates[0]}!`
            : `Mapeamento colado com sucesso em ${targetCoordinates.length} células!`,
    );
}

function columnToNumber(col: string): number {
    let num = 0;

    for (let i = 0; i < col.length; i++) {
        num = num * 26 + (col.charCodeAt(i) - 64);
    }

    return num;
}

function numberToColumn(num: number): string {
    let s = '';

    while (num > 0) {
        const mod = (num - 1) % 26;
        s = String.fromCharCode(65 + mod) + s;
        num = Math.floor((num - mod) / 26);
    }

    return s;
}

// Sheet Tab Operations
function addSheet() {
    const nextIdx = config.value.sheets.length + 1;
    config.value.sheets.push({
        sheet_identifier_type: 'index',
        sheet_identifier_value: nextIdx,
        sheet_name: `Aba ${nextIdx}`,
        date_mode: 'cell_reference',
        date_cell: 'A3',
        time_cell: '',
        date_column: 'A',
        date_row: null,
        time_column: null,
        time_row: null,
        allowed_times: [],
        empty_values_mode: config.value.empty_values_mode ?? 'ignore',
        ignore_empty_cells: config.value.ignore_empty_cells ?? true,
        mappings: [],
    });
    activeSheetIndex.value = config.value.sheets.length - 1;
}

function removeSheet(idx: number) {
    if (config.value.sheets.length <= 1) {
        toast.error('O modelo precisa ter pelo menos uma aba.');

        return;
    }

    config.value.sheets.splice(idx, 1);

    if (activeSheetIndex.value >= config.value.sheets.length) {
        activeSheetIndex.value = config.value.sheets.length - 1;
    }
}

// Sample file upload
function triggerSampleUpload() {
    sampleFileInput.value?.click();
}

async function handleSampleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) {
        return;
    }

    isUploadingSample.value = true;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(
            templatesSamplePreview.url({
                current_team: props.currentTeam.slug,
            }),
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: formData,
            },
        );

        const res = await response.json();

        if (res.success && res.sheets && res.sheets.length > 0) {
            sampleSheetsData.value = res.sheets;

            // Sincronizar automaticamente as abas se o template não tiver mapeamentos definidos ou se tiver apenas abas padrão
            const hasCustomMappings = config.value.sheets.some(
                (s) => s.mappings && s.mappings.length > 0,
            );

            if (!hasCustomMappings) {
                config.value.sheets = res.sheets.map((s: any) => ({
                    sheet_identifier_type: 'name',
                    sheet_identifier_value: s.name,
                    sheet_name: s.name,
                    date_mode: s.suggested_orientation || 'horizontal_series',
                    date_cell: 'A3',
                    time_cell: '',
                    date_column: s.suggested_date_column || 'A',
                    date_row: s.suggested_date_row ?? 2,
                    mappings: [],
                }));
            } else {
                res.sheets.forEach((s: any, idx: number) => {
                    if (!config.value.sheets[idx]) {
                        config.value.sheets.push({
                            sheet_identifier_type: 'name',
                            sheet_identifier_value: s.name,
                            sheet_name: s.name,
                            date_mode:
                                s.suggested_orientation || 'horizontal_series',
                            date_cell: 'A3',
                            time_cell: '',
                            date_column: s.suggested_date_column || 'A',
                            date_row: s.suggested_date_row ?? 2,
                            mappings: [],
                        });
                    } else if (
                        !config.value.sheets[idx].sheet_name ||
                        config.value.sheets[idx].sheet_name.startsWith('Aba ')
                    ) {
                        config.value.sheets[idx].sheet_name = s.name;
                        config.value.sheets[idx].sheet_identifier_value =
                            s.name;
                        config.value.sheets[idx].sheet_identifier_type = 'name';
                    }

                    if (
                        s.suggested_orientation &&
                        (!config.value.sheets[idx].mappings ||
                            config.value.sheets[idx].mappings.length === 0)
                    ) {
                        config.value.sheets[idx].date_mode =
                            s.suggested_orientation;

                        if (s.suggested_date_row) {
                            config.value.sheets[idx].date_row =
                                s.suggested_date_row;
                        }

                        if (s.suggested_date_column) {
                            config.value.sheets[idx].date_column =
                                s.suggested_date_column;
                        }
                    }
                });
            }

            activeSheetIndex.value = 0;

            const firstSheet = res.sheets[0];
            let orientationDesc = '';

            if (firstSheet?.suggested_orientation === 'horizontal_series') {
                orientationDesc = ` Orientação detectada: Horizontal (Linha de datas ${firstSheet.suggested_date_row}).`;
            } else if (
                firstSheet?.suggested_orientation === 'vertical_series'
            ) {
                orientationDesc = ` Orientação detectada: Vertical (Coluna de datas ${firstSheet.suggested_date_column}).`;
            }

            toast.success(
                `Planilha "${res.file_name}" carregada com ${res.sheets.length} aba(s)!${orientationDesc}`,
            );
        } else {
            toast.error(
                res.message || 'Erro ao processar planilha de exemplo.',
            );
        }
    } catch {
        toast.error('Falha na comunicação com o servidor.');
    } finally {
        isUploadingSample.value = false;
        target.value = '';
    }
}

// JSON Schema Apply
function handleApplyJsonConfig(newConfig: TemplateConfig) {
    config.value = newConfig;
    toast.success('Configuração JSON aplicada na grade!');
}

// Save template
function handleSaveTemplate() {
    if (!name.value.trim()) {
        toast.error('Informe o nome do modelo de planilha.');

        return;
    }

    isSubmitting.value = true;
    const payload = {
        name: name.value.trim(),
        description: description.value.trim() || null,
        config: config.value,
        is_active: isActive.value,
    };

    if (isEditing.value && props.template) {
        router.put(
            templatesUpdate.url({
                current_team: props.currentTeam.slug,
                template: props.template.id,
            }),
            payload,
            {
                onFinish: () => {
                    isSubmitting.value = false;
                },
            },
        );
    } else {
        router.post(
            templatesStore.url({ current_team: props.currentTeam.slug }),
            payload,
            {
                onFinish: () => {
                    isSubmitting.value = false;
                },
            },
        );
    }
}
</script>

<template>
    <Head
        :title="
            isEditing ? `Editar Modelo: ${name}` : 'Novo Modelo de Planilha'
        "
    />

    <div
        :class="[
            isExpandedMode
                ? 'fixed inset-0 z-50 flex h-screen w-screen flex-col gap-2 bg-background p-3'
                : 'flex h-[calc(100vh-3rem)] w-full flex-col gap-2 overflow-hidden p-2.5 md:p-3',
        ]"
    >
        <!-- Top Navigation & Actions Bar (Normal Mode) -->
        <div
            v-if="!isExpandedMode"
            class="flex flex-wrap items-center justify-between gap-2 border-b pb-2"
        >
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        :href="
                            templatesIndex.url({
                                current_team: currentTeam.slug,
                            })
                        "
                    >
                        <ArrowLeft class="mr-1 h-4 w-4" />
                        Voltar aos Modelos
                    </Link>
                </Button>
                <div class="h-4 w-px bg-border" />
                <h1
                    class="flex items-center gap-2 text-lg font-bold text-foreground"
                >
                    <FileSpreadsheet class="h-5 w-5 text-primary" />
                    {{
                        isEditing
                            ? 'Editar Modelo de Mapeamento'
                            : 'Novo Modelo de Mapeamento'
                    }}
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <!-- Sample upload hidden input -->
                <input
                    ref="sampleFileInput"
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="hidden"
                    @change="handleSampleFileChange"
                />

                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    :disabled="isUploadingSample"
                    @click="triggerSampleUpload"
                >
                    <RefreshCw
                        v-if="isUploadingSample"
                        class="mr-1.5 h-3.5 w-3.5 animate-spin"
                    />
                    <Upload v-else class="mr-1.5 h-3.5 w-3.5" />
                    {{
                        isUploadingSample
                            ? 'Carregando Exemplo...'
                            : 'Carregar Planilha Exemplo'
                    }}
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    @click="isJsonDrawerOpen = true"
                >
                    <Code class="mr-1.5 h-3.5 w-3.5" />
                    Editor JSON
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    title="Expandir grade para tela inteira"
                    @click="isExpandedMode = true"
                >
                    <Maximize2 class="mr-1.5 h-3.5 w-3.5" />
                    Tela Ampla
                </Button>

                <Button
                    size="sm"
                    type="button"
                    :disabled="isSubmitting"
                    @click="handleSaveTemplate"
                >
                    <RefreshCw
                        v-if="isSubmitting"
                        class="mr-1.5 h-3.5 w-3.5 animate-spin"
                    />
                    <Save v-else class="mr-1.5 h-3.5 w-3.5" />
                    {{ isSubmitting ? 'Salvando...' : 'Salvar Modelo' }}
                </Button>
            </div>
        </div>

        <!-- Top Compact Toolbar (Expanded Mode) -->
        <div
            v-else
            class="flex items-center justify-between gap-3 rounded-lg border bg-muted/40 px-3 py-1.5 text-xs shadow-xs"
        >
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 font-semibold text-primary"
                >
                    Modo Tela Ampla
                </span>
                <Input
                    v-model="name"
                    placeholder="Nome do Modelo *"
                    class="h-7 w-52 text-xs font-semibold"
                />
                <select
                    v-model="activeSheet.date_mode"
                    class="h-7 rounded-md border border-input bg-background px-2 text-xs font-medium shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="cell_reference">Células fixas</option>
                    <option value="horizontal_series">Horizontal</option>
                    <option value="vertical_series">Vertical</option>
                </select>
                <select
                    :value="
                        activeSheet.empty_values_mode ??
                        config.empty_values_mode ??
                        'ignore'
                    "
                    class="h-7 rounded-md border border-input bg-background px-2 text-xs font-medium shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    title="Campos Vazios"
                    @change="
                        handleEmptyValuesModeChange(
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="save_empty">Gravar vazio</option>
                    <option value="ignore">Ignorar</option>
                </select>
                <div
                    v-if="activeSheet.date_mode === 'cell_reference'"
                    class="flex items-center gap-1.5"
                >
                    <span class="font-medium text-muted-foreground">Data:</span>
                    <Input
                        v-model="activeSheet.date_cell"
                        class="h-7 w-16 font-mono text-xs uppercase"
                        placeholder="ex: B6"
                    />
                    <span class="font-medium text-muted-foreground">Hora:</span>
                    <Input
                        v-model="activeSheet.time_cell"
                        class="h-7 w-16 font-mono text-xs uppercase"
                        placeholder="ex: C7"
                    />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Sample upload hidden input -->
                <input
                    ref="sampleFileInput"
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="hidden"
                    @change="handleSampleFileChange"
                />

                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    class="h-7 text-xs"
                    :disabled="isUploadingSample"
                    @click="triggerSampleUpload"
                >
                    <RefreshCw
                        v-if="isUploadingSample"
                        class="mr-1 h-3 w-3 animate-spin"
                    />
                    <Upload v-else class="mr-1 h-3 w-3" />
                    {{
                        isUploadingSample ? 'Carregando...' : 'Carregar Exemplo'
                    }}
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    class="h-7 text-xs"
                    @click="isJsonDrawerOpen = true"
                >
                    <Code class="mr-1 h-3 w-3" />
                    Editor JSON
                </Button>

                <Button
                    size="sm"
                    type="button"
                    class="h-7 text-xs"
                    :disabled="isSubmitting"
                    @click="handleSaveTemplate"
                >
                    <RefreshCw
                        v-if="isSubmitting"
                        class="mr-1 h-3 w-3 animate-spin"
                    />
                    <Save v-else class="mr-1 h-3 w-3" />
                    {{ isSubmitting ? 'Salvando...' : 'Salvar Modelo' }}
                </Button>

                <div class="h-4 w-px bg-border" />

                <Button
                    variant="secondary"
                    size="sm"
                    type="button"
                    class="h-7 text-xs"
                    title="Sair do modo tela ampla"
                    @click="isExpandedMode = false"
                >
                    <Minimize2 class="mr-1 h-3 w-3" />
                    Restaurar Visualização
                </Button>
            </div>
        </div>

        <!-- Unified Configuration Toolbar (Normal Mode) -->
        <div
            v-if="!isExpandedMode"
            class="rounded-lg border bg-muted/20 px-3 py-1.5 text-xs shadow-xs"
        >
            <div
                class="flex items-end justify-between gap-3 overflow-x-auto py-0.5"
            >
                <div class="flex items-end gap-2.5">
                    <!-- Nome do Modelo -->
                    <div class="flex flex-col gap-0.5">
                        <Label
                            for="template-name"
                            class="text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                            >Nome:*</Label
                        >
                        <Input
                            id="template-name"
                            v-model="name"
                            placeholder="ex: Boletim Diário"
                            class="h-7 w-36 text-xs font-medium sm:w-40"
                        />
                    </div>

                    <div class="hidden h-5 w-px bg-border sm:block" />

                    <!-- Estrutura -->
                    <div class="flex flex-col gap-0.5">
                        <span
                            class="text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                            >Estrutura:</span
                        >
                        <select
                            v-model="activeSheet.date_mode"
                            class="h-7 rounded-md border border-input bg-background px-2 text-xs font-medium shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="cell_reference">
                                Células fixas
                            </option>
                            <option value="horizontal_series">
                                Horizontal
                            </option>
                            <option value="vertical_series">Vertical</option>
                        </select>
                    </div>

                    <div class="hidden h-5 w-px bg-border sm:block" />

                    <!-- Campos Vazios -->
                    <div class="flex flex-col gap-0.5">
                        <span
                            class="text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                            >Campos Vazios:</span
                        >
                        <select
                            :value="
                                activeSheet.empty_values_mode ??
                                config.empty_values_mode ??
                                'ignore'
                            "
                            class="h-7 rounded-md border border-input bg-background px-2 text-xs font-medium shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            @change="
                                handleEmptyValuesModeChange(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="save_empty">Gravar vazio</option>
                            <option value="ignore">Ignorar</option>
                        </select>
                    </div>

                    <!-- Modo Células fixas -->
                    <template v-if="activeSheet.date_mode === 'cell_reference'">
                        <div class="hidden h-5 w-px bg-border sm:block" />

                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-[11px] font-medium text-muted-foreground"
                                >Data:</span
                            >
                            <Input
                                v-model="activeSheet.date_cell"
                                class="h-7 w-16 font-mono text-xs uppercase"
                                placeholder="ex: B6"
                            />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-[11px] font-medium text-muted-foreground"
                                >Hora:</span
                            >
                            <Input
                                v-model="activeSheet.time_cell"
                                class="h-7 w-16 font-mono text-xs uppercase"
                                placeholder="ex: C7"
                            />
                        </div>
                    </template>

                    <!-- Modo Tabela Horizontal -->
                    <template
                        v-else-if="
                            activeSheet.date_mode === 'horizontal_series'
                        "
                    >
                        <div class="hidden h-5 w-px bg-border sm:block" />

                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                                >Linha das Datas:</span
                            >
                            <input
                                v-model.number="activeSheet.date_row"
                                type="number"
                                min="1"
                                class="flex h-7 w-14 rounded-md border border-input bg-background px-2 text-center font-mono text-xs font-bold text-primary shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                placeholder="2"
                            />
                        </div>

                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                                >Linha das Horas:</span
                            >
                            <input
                                v-model.number="activeSheet.time_row"
                                type="number"
                                min="1"
                                class="flex h-7 w-16 rounded-md border border-input bg-background px-2 text-center font-mono text-xs font-bold text-primary shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                placeholder="Opcional"
                                title="Deixe vazio se o horário estiver junto com a data na mesma célula"
                            />
                        </div>

                        <div class="hidden h-5 w-px bg-border sm:block" />

                        <!-- Filtro de Horas Permitidas -->
                        <div class="flex flex-col gap-0.5">
                            <span
                                class="flex items-center gap-1 text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                                title="Se vazio, captura todas as horas. Se preenchido, apenas medições destas horas exatas serão importadas."
                            >
                                <Clock class="h-3 w-3 text-primary" />
                                <span>Filtro de Horas:</span>
                                <span
                                    v-if="
                                        !activeSheet.allowed_times ||
                                        activeSheet.allowed_times.length === 0
                                    "
                                    class="text-[10px] font-normal text-muted-foreground italic"
                                >
                                    (Todas as horas)
                                </span>
                            </span>

                            <div class="flex items-center gap-1">
                                <span
                                    v-for="timeTag in activeSheet.allowed_times ??
                                    []"
                                    :key="timeTag"
                                    class="inline-flex items-center gap-1 rounded-md border border-primary/30 bg-primary/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-primary"
                                >
                                    {{ timeTag }}
                                    <button
                                        type="button"
                                        class="text-primary/70 hover:text-destructive"
                                        title="Remover horário"
                                        @click="
                                            handleRemoveAllowedTime(timeTag)
                                        "
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </span>

                                <input
                                    v-model="newTimeInput"
                                    type="text"
                                    placeholder="+ ex: 07:00"
                                    class="h-7 w-20 rounded-md border border-input bg-background px-1.5 font-mono text-xs shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    @keydown="handleKeydownTimeInput"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 px-1.5 text-xs"
                                    title="Adicionar horário ao filtro"
                                    @click="handleAddAllowedTime"
                                >
                                    <Plus class="h-3 w-3" />
                                </Button>
                            </div>
                        </div>
                    </template>

                    <!-- Modo Tabela Vertical -->
                    <template
                        v-else-if="
                            activeSheet.date_mode === 'vertical_series' ||
                            activeSheet.date_mode === 'upload_date_match' ||
                            activeSheet.date_mode === 'all_dates_scan'
                        "
                    >
                        <div class="hidden h-5 w-px bg-border sm:block" />

                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                                >Coluna das Datas:</span
                            >
                            <Input
                                v-model="activeSheet.date_column"
                                class="h-7 w-14 text-center font-mono text-xs font-bold text-primary uppercase"
                                placeholder="A"
                            />
                        </div>

                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                                >Coluna das Horas:</span
                            >
                            <input
                                v-model="activeSheet.time_column"
                                class="flex h-7 w-16 rounded-md border border-input bg-background px-2 text-center font-mono text-xs font-bold text-primary uppercase shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                placeholder="Opcional"
                                title="Deixe vazio se o horário estiver junto com a data na mesma célula"
                            />
                        </div>

                        <div class="hidden h-5 w-px bg-border sm:block" />

                        <!-- Filtro de Horas Permitidas -->
                        <div class="flex flex-col gap-0.5">
                            <span
                                class="flex items-center gap-1 text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                                title="Se vazio, captura todas as horas. Se preenchido, apenas medições destas horas exatas serão importadas."
                            >
                                <Clock class="h-3 w-3 text-primary" />
                                <span>Filtro de Horas:</span>
                                <span
                                    v-if="
                                        !activeSheet.allowed_times ||
                                        activeSheet.allowed_times.length === 0
                                    "
                                    class="text-[10px] font-normal text-muted-foreground italic"
                                >
                                    (Todas as horas)
                                </span>
                            </span>

                            <div class="flex items-center gap-1">
                                <span
                                    v-for="timeTag in activeSheet.allowed_times ??
                                    []"
                                    :key="timeTag"
                                    class="inline-flex items-center gap-1 rounded-md border border-primary/30 bg-primary/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-primary"
                                >
                                    {{ timeTag }}
                                    <button
                                        type="button"
                                        class="text-primary/70 hover:text-destructive"
                                        title="Remover horário"
                                        @click="
                                            handleRemoveAllowedTime(timeTag)
                                        "
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </span>

                                <input
                                    v-model="newTimeInput"
                                    type="text"
                                    placeholder="+ ex: 07:00"
                                    class="h-7 w-20 rounded-md border border-input bg-background px-1.5 font-mono text-xs shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    @keydown="handleKeydownTimeInput"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 px-1.5 text-xs"
                                    title="Adicionar horário ao filtro"
                                    @click="handleAddAllowedTime"
                                >
                                    <Plus class="h-3 w-3" />
                                </Button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Slider Toggle: Ativo (Fixo à direita na Linha) -->
                <div class="flex shrink-0 flex-col items-center gap-1 pb-0.5">
                    <Label
                        for="template-active-toggle"
                        class="cursor-pointer text-[11px] font-medium text-muted-foreground select-none"
                        >Ativo</Label
                    >
                    <button
                        id="template-active-toggle"
                        type="button"
                        role="switch"
                        :aria-checked="isActive"
                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                        :class="
                            isActive ? 'bg-primary' : 'bg-muted-foreground/30'
                        "
                        @click="isActive = !isActive"
                    >
                        <span
                            aria-hidden="true"
                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow-md ring-0 transition duration-200 ease-in-out"
                            :class="
                                isActive ? 'translate-x-4' : 'translate-x-0'
                            "
                        />
                    </button>
                </div>
            </div>
        </div>

        <!-- Sheet Settings & Tabs Section -->
        <div class="space-y-2">
            <div
                class="flex items-center justify-between rounded-lg border bg-muted/40 px-3 py-1.5 text-xs shadow-xs"
            >
                <div class="flex min-w-0 items-center gap-2 overflow-x-auto">
                    <span class="shrink-0 font-semibold text-muted-foreground"
                        >Abas da Planilha:</span
                    >
                    <div class="flex items-center gap-1.5 py-0.5">
                        <button
                            v-for="(sheet, sIdx) in config.sheets"
                            :key="sIdx"
                            type="button"
                            :class="[
                                'flex shrink-0 items-center gap-1.5 rounded-md border px-3 py-1 text-xs font-medium whitespace-nowrap shadow-xs transition-colors',
                                activeSheetIndex === sIdx
                                    ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                    : 'border-border bg-background text-muted-foreground hover:bg-muted/70 hover:text-foreground',
                            ]"
                            @click="activeSheetIndex = sIdx"
                        >
                            <span>{{
                                sheet.sheet_name || `Aba ${sIdx + 1}`
                            }}</span>
                            <span
                                :class="[
                                    'inline-flex h-4 min-w-4.5 items-center justify-center rounded-full px-1.5 font-mono text-[10px] leading-none font-semibold transition-colors',
                                    (sheet.mappings?.length ?? 0) > 0
                                        ? activeSheetIndex === sIdx
                                            ? 'bg-emerald-500 text-white shadow-xs'
                                            : 'border border-emerald-300 bg-emerald-100 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        : activeSheetIndex === sIdx
                                          ? 'bg-primary-foreground/20 text-primary-foreground'
                                          : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                {{ sheet.mappings?.length ?? 0 }}
                            </span>
                            <span
                                v-if="config.sheets.length > 1"
                                class="ml-1 rounded-full p-0.5 transition-colors hover:bg-destructive/20 hover:text-destructive"
                                title="Remover aba"
                                @click.stop="removeSheet(sIdx)"
                            >
                                ×
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex shrink-0 items-center gap-1 rounded-md border border-dashed border-border bg-background px-2.5 py-1 text-xs text-muted-foreground transition-colors hover:border-primary/50 hover:bg-muted hover:text-foreground"
                            title="Adicionar outra aba"
                            @click="addSheet"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            <span>Nova Aba</span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="isExpandedMode"
                    class="flex shrink-0 items-center gap-2 pl-2"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                        title="Sair da tela cheia"
                        @click="isExpandedMode = false"
                    >
                        <Minimize2 class="mr-1 h-3.5 w-3.5" />
                        Minimizar
                    </Button>
                </div>
            </div>
        </div>

        <!-- Main Interactive Excel Grid with loading state -->
        <div class="relative min-h-0 flex-1">
            <div
                v-if="isUploadingSample"
                class="absolute inset-0 z-30 flex flex-col items-center justify-center gap-2 rounded-lg bg-background/80 backdrop-blur-xs"
            >
                <RefreshCw class="h-8 w-8 animate-spin text-primary" />
                <span class="text-sm font-semibold text-foreground">
                    Lendo e processando abas da planilha...
                </span>
                <span class="text-xs text-muted-foreground">
                    Carregando valores calculados e referências de células
                </span>
            </div>

            <SpreadsheetGrid
                :sheet-config="activeSheet"
                :sample-data="activeSampleCells"
                :max-rows="activeSampleHighestRow"
                :max-cols="activeSampleHighestCol"
                @edit-cell="openCellEditor"
                @paste-mapping="onPasteTriggered"
                @delete-mapping="handleRemoveCellMapping"
                @delete-mappings="handleRemoveMultipleMappings"
            />
        </div>

        <!-- Cell Mapping Modal -->
        <CellMappingModal
            v-model:open="isMappingModalOpen"
            :cell-coordinate="selectedCellCoord"
            :existing-mapping="activeCellMapping"
            :default-date-cell="activeSheet.date_cell"
            :default-time-cell="activeSheet.time_cell"
            :systems="systems"
            :date-mode="activeSheet.date_mode"
            :date-row="activeSheet.date_row"
            :date-column="activeSheet.date_column"
            @save="handleSaveCellMapping"
            @remove="handleRemoveCellMapping"
        />

        <!-- Paste Increment Dialog -->
        <PasteIncrementDialog
            v-if="pendingPastePayload"
            v-model:open="isPasteDialogOpen"
            :source-cell="pendingPastePayload.source.cell"
            :source-date-cell="pendingPastePayload.source.date_cell"
            :target-cells="pendingPastePayload.targetCoordinates"
            @confirm="handleConfirmPasteIncrement"
        />

        <!-- JSON Config Drawer -->
        <JsonConfigDrawer
            v-model:open="isJsonDrawerOpen"
            :config="config"
            @apply="handleApplyJsonConfig"
        />
    </div>
</template>
