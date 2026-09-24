<script setup lang="ts">
import { Copy, Edit3, Plus, Search, Table, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import type {
    CellMapping,
    SheetConfig,
} from '@/features/spreadsheet-imports/types';

const props = defineProps<{
    sheetConfig: SheetConfig;
    sampleData?: Record<string, any>;
    maxRows?: number;
    maxCols?: number;
}>();

const emit = defineEmits<{
    (e: 'edit-cell', coordinate: string): void;
    (
        e: 'paste-mapping',
        payload: { source: CellMapping; targetCoordinates: string[] },
    ): void;
    (e: 'delete-mapping', coordinate: string): void;
    (e: 'delete-mappings', coordinates: string[]): void;
}>();

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

function parseCoord(coord: string): {
    col: string;
    colNum: number;
    row: number;
} {
    const match = coord.toUpperCase().match(/^([A-Z]+)(\d+)$/);

    if (!match) {
        return { col: 'A', colNum: 1, row: 1 };
    }

    return {
        col: match[1],
        colNum: columnToNumber(match[1]),
        row: parseInt(match[2], 10),
    };
}

function getCoordinate(colLetter: string, rowNumber: number): string {
    return `${colLetter}${rowNumber}`;
}

const extraCols = ref(0);
const extraRows = ref(0);
const jumpCellInput = ref('');
const gridContainerRef = ref<HTMLElement | null>(null);

const maxMappedCol = computed(() => {
    let max = 0;

    if (props.sheetConfig?.mappings) {
        for (const m of props.sheetConfig.mappings) {
            if (m.cell) {
                const parsed = parseCoord(m.cell);

                if (parsed.colNum > max) {
                    max = parsed.colNum;
                }
            }

            if (m.date_cell) {
                const parsed = parseCoord(m.date_cell);

                if (parsed.colNum > max) {
                    max = parsed.colNum;
                }
            }

            if (
                m.row_or_col &&
                typeof m.row_or_col === 'string' &&
                isNaN(Number(m.row_or_col))
            ) {
                const num = columnToNumber(m.row_or_col.toUpperCase());

                if (num > max) {
                    max = num;
                }
            }
        }
    }

    if (props.sheetConfig?.date_column) {
        const num = columnToNumber(props.sheetConfig.date_column.toUpperCase());

        if (num > max) {
            max = num;
        }
    }

    if (props.sheetConfig?.time_column) {
        const num = columnToNumber(props.sheetConfig.time_column.toUpperCase());

        if (num > max) {
            max = num;
        }
    }

    return max;
});

const maxMappedRow = computed(() => {
    let max = 0;

    if (props.sheetConfig?.mappings) {
        for (const m of props.sheetConfig.mappings) {
            if (m.cell) {
                const parsed = parseCoord(m.cell);

                if (parsed.row > max) {
                    max = parsed.row;
                }
            }

            if (m.date_cell) {
                const parsed = parseCoord(m.date_cell);

                if (parsed.row > max) {
                    max = parsed.row;
                }
            }

            if (m.row_or_col && !isNaN(Number(m.row_or_col))) {
                const r = Number(m.row_or_col);

                if (r > max) {
                    max = r;
                }
            }
        }
    }

    if (
        props.sheetConfig?.date_row &&
        Number(props.sheetConfig.date_row) > max
    ) {
        max = Number(props.sheetConfig.date_row);
    }

    if (
        props.sheetConfig?.time_row &&
        Number(props.sheetConfig.time_row) > max
    ) {
        max = Number(props.sheetConfig.time_row);
    }

    return max;
});

const numRows = computed(() => {
    const base = Math.max(props.maxRows ?? 50, maxMappedRow.value, 50);

    return base + extraRows.value;
});

const numCols = computed(() => {
    const base = Math.max(props.maxCols ?? 26, maxMappedCol.value, 26);

    return base + extraCols.value;
});

function onGridScroll(e: Event) {
    const target = e.target as HTMLElement;

    if (!target) {
        return;
    }

    if (target.scrollLeft + target.clientWidth >= target.scrollWidth - 150) {
        extraCols.value += 26;
    }

    if (target.scrollTop + target.clientHeight >= target.scrollHeight - 150) {
        extraRows.value += 50;
    }
}

function handleAddColumns(count = 26) {
    extraCols.value += count;
}

function handleAddRows(count = 50) {
    extraRows.value += count;
}

function handleJumpToCell() {
    const clean = jumpCellInput.value.trim().toUpperCase();

    if (!clean) {
        return;
    }

    const match = clean.match(/^([A-Z]+)(\d+)$/);

    if (!match) {
        return;
    }

    const colStr = match[1];
    const targetColNum = columnToNumber(colStr);
    const targetRowNum = parseInt(match[2], 10);

    if (targetColNum > numCols.value) {
        extraCols.value += targetColNum - numCols.value + 10;
    }

    if (targetRowNum > numRows.value) {
        extraRows.value += targetRowNum - numRows.value + 20;
    }

    activeCell.value = clean;
    selectionAnchor.value = clean;
    selectionLead.value = clean;
    jumpCellInput.value = '';
}

// Generate Column letters: A, B, C... Z, AA, AB...
const columns = computed(() => {
    const cols: string[] = [];

    for (let i = 1; i <= numCols.value; i++) {
        cols.push(numberToColumn(i));
    }

    return cols;
});

// Selection state
const activeCell = ref<string>('A1');
const selectionAnchor = ref<string>('A1');
const selectionLead = ref<string>('A1');
const isMouseDown = ref<boolean>(false);
const copiedMapping = ref<CellMapping | null>(null);

const mappedLookup = computed(() => {
    const map = new Map<string, CellMapping>();

    if (props.sheetConfig?.mappings) {
        for (const m of props.sheetConfig.mappings) {
            map.set(m.cell.toUpperCase(), m);
        }
    }

    return map;
});

// Detect row-level mappings in horizontal series
const mappedRows = computed(() => {
    const map = new Map<number, CellMapping>();

    if (
        props.sheetConfig?.date_mode === 'horizontal_series' &&
        props.sheetConfig.mappings
    ) {
        for (const m of props.sheetConfig.mappings) {
            let row: number | null = null;

            if (m.row_or_col && !isNaN(Number(m.row_or_col))) {
                row = Number(m.row_or_col);
            } else if (m.cell) {
                const parsed = parseCoord(m.cell);
                row = parsed.row;
            }

            if (row) {
                map.set(row, m);
            }
        }
    }

    return map;
});

// Detect col-level mappings in vertical series
const mappedCols = computed(() => {
    const map = new Map<string, CellMapping>();

    if (
        props.sheetConfig?.date_mode !== 'horizontal_series' &&
        props.sheetConfig?.date_mode !== 'cell_reference' &&
        props.sheetConfig.mappings
    ) {
        for (const m of props.sheetConfig.mappings) {
            let col: string | null = null;

            if (
                m.row_or_col &&
                typeof m.row_or_col === 'string' &&
                isNaN(Number(m.row_or_col))
            ) {
                col = m.row_or_col.toUpperCase();
            } else if (m.cell) {
                const parsed = parseCoord(m.cell);
                col = parsed.col;
            }

            if (col) {
                map.set(col, m);
            }
        }
    }

    return map;
});

// Selection Bounding Box calculation
const minColNum = computed(() => {
    const a = parseCoord(selectionAnchor.value);
    const l = parseCoord(selectionLead.value);

    return Math.min(a.colNum, l.colNum);
});

const maxColNum = computed(() => {
    const a = parseCoord(selectionAnchor.value);
    const l = parseCoord(selectionLead.value);

    return Math.max(a.colNum, l.colNum);
});

const minRow = computed(() => {
    const a = parseCoord(selectionAnchor.value);
    const l = parseCoord(selectionLead.value);

    return Math.min(a.row, l.row);
});

const maxRow = computed(() => {
    const a = parseCoord(selectionAnchor.value);
    const l = parseCoord(selectionLead.value);

    return Math.max(a.row, l.row);
});

const selectedCount = computed(() => {
    return (
        (maxColNum.value - minColNum.value + 1) *
        (maxRow.value - minRow.value + 1)
    );
});

const selectionLabel = computed(() => {
    if (selectedCount.value === 1) {
        return activeCell.value;
    }

    const start = getCoordinate(numberToColumn(minColNum.value), minRow.value);
    const end = getCoordinate(numberToColumn(maxColNum.value), maxRow.value);

    return `${start}:${end}`;
});

const selectedCoordinates = computed(() => {
    const coords: string[] = [];

    for (let c = minColNum.value; c <= maxColNum.value; c++) {
        const colLetter = numberToColumn(c);

        for (let r = minRow.value; r <= maxRow.value; r++) {
            coords.push(getCoordinate(colLetter, r));
        }
    }

    return coords;
});

const hasMappingsInSelection = computed(() => {
    return selectedCoordinates.value.some((c) => mappedLookup.value.has(c));
});

function isCellSelected(colNum: number, row: number): boolean {
    return (
        colNum >= minColNum.value &&
        colNum <= maxColNum.value &&
        row >= minRow.value &&
        row <= maxRow.value
    );
}

function formatSampleCellValue(val: any): string {
    if (val === null || val === undefined || val === '') {
        return '';
    }

    if (typeof val === 'number' && val >= 35000 && val <= 65000) {
        const date = new Date(Math.round((val - 25569) * 86400) * 1000);

        if (date.getUTCSeconds() >= 55) {
            date.setUTCMinutes(date.getUTCMinutes() + 1);
            date.setUTCSeconds(0);
        }

        const d = String(date.getUTCDate()).padStart(2, '0');
        const m = String(date.getUTCMonth() + 1).padStart(2, '0');
        const y = date.getUTCFullYear();
        const hrs = date.getUTCHours();
        const mins = date.getUTCMinutes();

        if (hrs !== 0 || mins !== 0) {
            return `${d}/${m}/${y} ${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
        }

        return `${d}/${m}/${y}`;
    }

    if (typeof val === 'string' && /^\d{5}(\.\d+)?$/.test(val.trim())) {
        const num = parseFloat(val.trim());

        if (num >= 35000 && num <= 65000) {
            const date = new Date(Math.round((num - 25569) * 86400) * 1000);

            if (date.getUTCSeconds() >= 55) {
                date.setUTCMinutes(date.getUTCMinutes() + 1);
                date.setUTCSeconds(0);
            }

            const d = String(date.getUTCDate()).padStart(2, '0');
            const m = String(date.getUTCMonth() + 1).padStart(2, '0');
            const y = date.getUTCFullYear();
            const hrs = date.getUTCHours();
            const mins = date.getUTCMinutes();

            if (hrs !== 0 || mins !== 0) {
                return `${d}/${m}/${y} ${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
            }

            return `${d}/${m}/${y}`;
        }
    }

    return String(val);
}

// Mouse Drag Events
function onCellMouseDown(coord: string, e: MouseEvent) {
    if (e.button !== 0) {
        return;
    } // Only primary button

    isMouseDown.value = true;
    activeCell.value = coord;

    if (e.shiftKey) {
        selectionLead.value = coord;
    } else {
        selectionAnchor.value = coord;
        selectionLead.value = coord;
    }
}

function onCellMouseEnter(coord: string) {
    if (isMouseDown.value) {
        selectionLead.value = coord;
    }
}

function onGlobalMouseUp() {
    isMouseDown.value = false;
}

// Header Selection
function onColumnHeaderClick(col: string, e: MouseEvent) {
    if (e.shiftKey) {
        selectionLead.value = getCoordinate(col, numRows.value);
    } else {
        selectionAnchor.value = getCoordinate(col, 1);
        selectionLead.value = getCoordinate(col, numRows.value);
        activeCell.value = getCoordinate(col, 1);
    }
}

function onColumnHeaderMouseEnter(col: string) {
    if (isMouseDown.value) {
        selectionLead.value = getCoordinate(col, numRows.value);
    }
}

function onRowHeaderClick(row: number, e: MouseEvent) {
    const lastCol = columns.value[columns.value.length - 1];

    if (e.shiftKey) {
        selectionLead.value = getCoordinate(lastCol, row);
    } else {
        selectionAnchor.value = getCoordinate(columns.value[0], row);
        selectionLead.value = getCoordinate(lastCol, row);
        activeCell.value = getCoordinate(columns.value[0], row);
    }
}

function onRowHeaderMouseEnter(row: number) {
    if (isMouseDown.value) {
        const lastCol = columns.value[columns.value.length - 1];
        selectionLead.value = getCoordinate(lastCol, row);
    }
}

function onCellDoubleClick(coord: string) {
    activeCell.value = coord;
    selectionAnchor.value = coord;
    selectionLead.value = coord;
    emit('edit-cell', coord);
}

// Actions
function handleCopy() {
    let mapping = mappedLookup.value.get(activeCell.value);

    if (!mapping) {
        for (const c of selectedCoordinates.value) {
            const found = mappedLookup.value.get(c);

            if (found) {
                mapping = found;
                break;
            }
        }
    }

    if (mapping) {
        copiedMapping.value = { ...mapping };
    }
}

function handlePaste() {
    if (!copiedMapping.value) {
        return;
    }

    emit('paste-mapping', {
        source: copiedMapping.value,
        targetCoordinates: selectedCoordinates.value,
    });
}

function handleDeleteSelection() {
    const mappedInSel = selectedCoordinates.value.filter((c) =>
        mappedLookup.value.has(c),
    );

    if (mappedInSel.length === 0) {
        return;
    }

    if (mappedInSel.length === 1) {
        emit('delete-mapping', mappedInSel[0]);
    } else {
        emit('delete-mappings', mappedInSel);
    }
}

function handleKeyDown(e: KeyboardEvent) {
    if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement ||
        e.target instanceof HTMLSelectElement
    ) {
        return;
    }

    const currentCoord = selectionLead.value;
    const { colNum, row } = parseCoord(currentCoord);

    if (e.key === 'ArrowUp') {
        e.preventDefault();

        if (row > 1) {
            const nextCoord = getCoordinate(numberToColumn(colNum), row - 1);
            selectionLead.value = nextCoord;

            if (!e.shiftKey) {
                selectionAnchor.value = nextCoord;
                activeCell.value = nextCoord;
            }
        }
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();

        if (row >= numRows.value) {
            extraRows.value += 50;
        }

        const nextCoord = getCoordinate(numberToColumn(colNum), row + 1);
        selectionLead.value = nextCoord;

        if (!e.shiftKey) {
            selectionAnchor.value = nextCoord;
            activeCell.value = nextCoord;
        }
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();

        if (colNum > 1) {
            const nextCoord = getCoordinate(numberToColumn(colNum - 1), row);
            selectionLead.value = nextCoord;

            if (!e.shiftKey) {
                selectionAnchor.value = nextCoord;
                activeCell.value = nextCoord;
            }
        }
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();

        if (colNum >= columns.value.length) {
            extraCols.value += 26;
        }

        const nextCoord = getCoordinate(numberToColumn(colNum + 1), row);
        selectionLead.value = nextCoord;

        if (!e.shiftKey) {
            selectionAnchor.value = nextCoord;
            activeCell.value = nextCoord;
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        emit('edit-cell', activeCell.value);
    } else if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        handleDeleteSelection();
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') {
        e.preventDefault();
        handleCopy();
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v') {
        e.preventDefault();
        handlePaste();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('mouseup', onGlobalMouseUp);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('mouseup', onGlobalMouseUp);
});
</script>

<template>
    <div
        class="flex h-full flex-col overflow-hidden rounded-lg border bg-background shadow-xs select-none"
    >
        <!-- Top Toolbar info & Quick Actions -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/40 px-3 py-1.5 text-xs"
        >
            <div class="flex items-center gap-3">
                <!-- Selection Info -->
                <div class="flex items-center gap-1.5 font-mono">
                    <span class="text-muted-foreground">Seleção:</span>
                    <span
                        class="rounded bg-primary/10 px-2 py-0.5 font-bold text-primary"
                    >
                        {{ selectionLabel }}
                    </span>
                    <span
                        v-if="selectedCount > 1"
                        class="text-[11px] text-muted-foreground"
                    >
                        ({{ selectedCount }} células)
                    </span>
                </div>

                <!-- Active Cell Mapping Info -->
                <div
                    v-if="mappedLookup.has(activeCell)"
                    class="flex items-center gap-2"
                >
                    <span
                        class="rounded-full bg-emerald-500/15 px-2 py-0.5 font-medium text-emerald-700 dark:text-emerald-400"
                    >
                        ✓ Mapeada
                    </span>
                    <span class="text-muted-foreground">
                        {{ mappedLookup.get(activeCell)?.description }}
                        <span
                            v-if="
                                (mappedLookup.get(activeCell)?.multiplier ??
                                    1) !== 1
                            "
                            class="font-mono font-bold text-amber-600 dark:text-amber-400"
                        >
                            (x{{ mappedLookup.get(activeCell)?.multiplier }})
                        </span>
                        <span
                            v-if="mappedLookup.get(activeCell)?.date_cell"
                            class="font-mono text-muted-foreground"
                        >
                            [Data:
                            {{ mappedLookup.get(activeCell)?.date_cell }}]
                        </span>
                    </span>
                </div>

                <!-- Horizontal Series Row Info -->
                <div
                    v-else-if="
                        sheetConfig.date_mode === 'horizontal_series' &&
                        mappedRows.has(parseCoord(activeCell).row)
                    "
                    class="flex items-center gap-2"
                >
                    <span
                        class="rounded-full bg-emerald-500/15 px-2 py-0.5 font-medium text-emerald-700 dark:text-emerald-400"
                    >
                        ✓ Linha {{ parseCoord(activeCell).row }} Mapeada
                    </span>
                    <span class="text-muted-foreground">
                        {{
                            mappedRows.get(parseCoord(activeCell).row)
                                ?.description
                        }}
                    </span>
                </div>

                <!-- Horizontal Series Time Row Info -->
                <div
                    v-else-if="
                        sheetConfig.date_mode === 'horizontal_series' &&
                        sheetConfig.time_row &&
                        Number(sheetConfig.time_row) ===
                            parseCoord(activeCell).row
                    "
                    class="flex items-center gap-2"
                >
                    <span
                        class="rounded-full bg-amber-500/15 px-2 py-0.5 font-medium text-amber-700 dark:text-amber-400"
                    >
                        🕒 Linha {{ parseCoord(activeCell).row }} de Horas
                    </span>
                </div>

                <!-- Vertical Series Col Info -->
                <div
                    v-else-if="
                        sheetConfig.date_mode !== 'horizontal_series' &&
                        sheetConfig.date_mode !== 'cell_reference' &&
                        mappedCols.has(parseCoord(activeCell).col)
                    "
                    class="flex items-center gap-2"
                >
                    <span
                        class="rounded-full bg-emerald-500/15 px-2 py-0.5 font-medium text-emerald-700 dark:text-emerald-400"
                    >
                        ✓ Coluna {{ parseCoord(activeCell).col }} Mapeada
                    </span>
                    <span class="text-muted-foreground">
                        {{
                            mappedCols.get(parseCoord(activeCell).col)
                                ?.description
                        }}
                    </span>
                </div>

                <!-- Vertical Series Time Col Info -->
                <div
                    v-else-if="
                        sheetConfig.date_mode !== 'horizontal_series' &&
                        sheetConfig.date_mode !== 'cell_reference' &&
                        sheetConfig.time_column &&
                        sheetConfig.time_column.toUpperCase() ===
                            parseCoord(activeCell).col
                    "
                    class="flex items-center gap-2"
                >
                    <span
                        class="rounded-full bg-amber-500/15 px-2 py-0.5 font-medium text-amber-700 dark:text-amber-400"
                    >
                        🕒 Coluna {{ parseCoord(activeCell).col }} de Horas
                    </span>
                </div>
            </div>

            <!-- Toolbar Buttons -->
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded border border-border bg-background px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                    title="Copiar mapeamento (Ctrl+C)"
                    @click="handleCopy"
                >
                    <Copy class="h-3 w-3" />
                    <span>Copiar</span>
                </button>

                <button
                    type="button"
                    :disabled="!copiedMapping"
                    :class="[
                        'inline-flex items-center gap-1 rounded border border-border px-2 py-1 text-xs font-medium transition-colors',
                        copiedMapping
                            ? 'cursor-pointer bg-background text-foreground hover:bg-muted'
                            : 'cursor-not-allowed bg-muted text-muted-foreground opacity-40',
                    ]"
                    title="Colar mapeamento (Ctrl+V)"
                    @click="handlePaste"
                >
                    <Table class="h-3 w-3" />
                    <span>Colar</span>
                </button>

                <button
                    type="button"
                    :disabled="
                        !hasMappingsInSelection && !mappedLookup.has(activeCell)
                    "
                    :class="[
                        'inline-flex items-center gap-1 rounded border border-border px-2 py-1 text-xs font-medium transition-colors',
                        hasMappingsInSelection || mappedLookup.has(activeCell)
                            ? 'cursor-pointer bg-background text-destructive hover:bg-destructive/10'
                            : 'cursor-not-allowed bg-muted text-muted-foreground opacity-40',
                    ]"
                    title="Limpar mapeamento da seleção (Del)"
                    @click="handleDeleteSelection"
                >
                    <Trash2 class="h-3 w-3" />
                    <span>Limpar</span>
                </button>

                <div class="h-4 w-px bg-border" />

                <!-- Quick jump to cell -->
                <div class="flex items-center gap-1">
                    <input
                        v-model="jumpCellInput"
                        type="text"
                        placeholder="Ir: AA5"
                        class="h-6 w-20 rounded border border-input bg-background px-1.5 font-mono text-[11px] uppercase shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        title="Digite uma célula (ex: AA1, BD50) e aperte Enter para ir direto a ela"
                        @keydown.enter.prevent="handleJumpToCell"
                    />
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded border border-border bg-background p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        title="Ir para célula"
                        @click="handleJumpToCell"
                    >
                        <Search class="h-3 w-3" />
                    </button>
                </div>

                <div class="h-4 w-px bg-border" />

                <!-- Expansion buttons -->
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded border border-border bg-background px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                    title="Adicionar mais 26 colunas à grade (AA, AB...)"
                    @click="handleAddColumns(26)"
                >
                    <Plus class="h-3 w-3" />
                    <span>+26 Colunas</span>
                </button>

                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded border border-border bg-background px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                    title="Adicionar mais 50 linhas à grade"
                    @click="handleAddRows(50)"
                >
                    <Plus class="h-3 w-3" />
                    <span>+50 Linhas</span>
                </button>

                <div class="h-4 w-px bg-border" />

                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded bg-primary px-2.5 py-1 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    @click="emit('edit-cell', activeCell)"
                >
                    <Edit3 class="h-3 w-3" />
                    <span>
                        {{
                            mappedLookup.has(activeCell) ||
                            (sheetConfig.date_mode === 'horizontal_series' &&
                                mappedRows.has(parseCoord(activeCell).row)) ||
                            (sheetConfig.date_mode !== 'horizontal_series' &&
                                sheetConfig.date_mode !== 'cell_reference' &&
                                mappedCols.has(parseCoord(activeCell).col))
                                ? 'Editar Mapeamento'
                                : 'Mapear Parâmetro'
                        }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Scrollable Excel Grid with infinite expansion on scroll -->
        <div
            ref="gridContainerRef"
            class="flex-1 overflow-auto"
            @scroll="onGridScroll"
        >
            <table class="w-full border-collapse font-mono text-[11px]">
                <thead>
                    <tr class="sticky top-0 z-20 bg-muted/80">
                        <th
                            class="w-10 min-w-10 border-r border-b bg-muted/90 p-1 text-center text-[10px] font-semibold text-muted-foreground"
                        >
                            #
                        </th>
                        <th
                            v-for="col in columns"
                            :key="col"
                            :class="[
                                'max-w-36 min-w-24 cursor-pointer border-r border-b px-1 py-1 text-center text-[11px] font-bold transition-colors select-none',
                                columnToNumber(col) >= minColNum &&
                                columnToNumber(col) <= maxColNum
                                    ? 'bg-primary/20 font-black text-primary'
                                    : 'text-muted-foreground hover:bg-muted',
                                sheetConfig.date_mode !== 'horizontal_series' &&
                                sheetConfig.date_mode !== 'cell_reference' &&
                                sheetConfig.date_column?.toUpperCase() === col
                                    ? 'bg-sky-500/20 text-sky-700 dark:text-sky-300'
                                    : '',
                                sheetConfig.date_mode !== 'horizontal_series' &&
                                sheetConfig.date_mode !== 'cell_reference' &&
                                sheetConfig.time_column?.toUpperCase() === col
                                    ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300'
                                    : '',
                            ]"
                            title="Clique para selecionar a coluna inteira"
                            @mousedown.prevent="
                                onColumnHeaderClick(col, $event)
                            "
                            @mouseenter="onColumnHeaderMouseEnter(col)"
                            @dblclick="emit('edit-cell', `${col}1`)"
                        >
                            <div class="flex flex-col items-center gap-0.5">
                                <span>{{ col }}</span>
                                <!-- Badge for Date Column -->
                                <span
                                    v-if="
                                        sheetConfig.date_mode !==
                                            'horizontal_series' &&
                                        sheetConfig.date_mode !==
                                            'cell_reference' &&
                                        sheetConfig.date_column?.toUpperCase() ===
                                            col
                                    "
                                    class="py-0.2 rounded bg-sky-500/30 px-1 text-[8px] font-semibold text-sky-800 dark:text-sky-200"
                                >
                                    📅 Datas
                                </span>
                                <!-- Badge for Time Column -->
                                <span
                                    v-else-if="
                                        sheetConfig.date_mode !==
                                            'horizontal_series' &&
                                        sheetConfig.date_mode !==
                                            'cell_reference' &&
                                        sheetConfig.time_column?.toUpperCase() ===
                                            col
                                    "
                                    class="py-0.2 rounded bg-amber-500/30 px-1 text-[8px] font-semibold text-amber-800 dark:text-amber-200"
                                >
                                    🕒 Horas
                                </span>
                                <!-- Badge for Column Parameter -->
                                <span
                                    v-else-if="mappedCols.has(col)"
                                    class="py-0.2 max-w-[90px] truncate rounded bg-emerald-500/20 px-1 text-[8px] font-bold text-emerald-800 dark:text-emerald-300"
                                    :title="mappedCols.get(col)?.description"
                                >
                                    {{
                                        mappedCols
                                            .get(col)
                                            ?.description?.split('-')[1]
                                            ?.trim() ||
                                        mappedCols.get(col)?.description ||
                                        'Mapeado'
                                    }}
                                </span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in numRows"
                        :key="row"
                        class="hover:bg-muted/10"
                    >
                        <!-- Row Header -->
                        <td
                            :class="[
                                'sticky left-0 z-10 cursor-pointer border-r border-b bg-muted/80 p-1 text-center text-[10px] font-semibold transition-colors select-none',
                                row >= minRow && row <= maxRow
                                    ? 'bg-primary/20 font-black text-primary'
                                    : 'text-muted-foreground hover:bg-muted',
                                sheetConfig.date_mode === 'horizontal_series' &&
                                Number(sheetConfig.date_row) === row
                                    ? 'bg-sky-500/20 text-sky-700 dark:text-sky-300'
                                    : '',
                                sheetConfig.date_mode === 'horizontal_series' &&
                                sheetConfig.time_row &&
                                Number(sheetConfig.time_row) === row
                                    ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300'
                                    : '',
                            ]"
                            title="Clique para selecionar a linha inteira"
                            @mousedown.prevent="onRowHeaderClick(row, $event)"
                            @mouseenter="onRowHeaderMouseEnter(row)"
                            @dblclick="emit('edit-cell', `${columns[0]}${row}`)"
                        >
                            <div class="flex flex-col items-center gap-0.5">
                                <span>{{ row }}</span>
                                <!-- Badge for Date Row -->
                                <span
                                    v-if="
                                        sheetConfig.date_mode ===
                                            'horizontal_series' &&
                                        Number(sheetConfig.date_row) === row
                                    "
                                    class="rounded bg-sky-500/30 px-1 text-[7.5px] font-semibold text-sky-800 dark:text-sky-200"
                                >
                                    📅 Datas
                                </span>
                                <!-- Badge for Time Row -->
                                <span
                                    v-else-if="
                                        sheetConfig.date_mode ===
                                            'horizontal_series' &&
                                        sheetConfig.time_row &&
                                        Number(sheetConfig.time_row) === row
                                    "
                                    class="rounded bg-amber-500/30 px-1 text-[7.5px] font-semibold text-amber-800 dark:text-amber-200"
                                >
                                    🕒 Horas
                                </span>
                                <!-- Badge for Row Parameter -->
                                <span
                                    v-else-if="mappedRows.has(row)"
                                    class="max-w-[36px] truncate rounded bg-emerald-500/20 px-0.5 text-[7.5px] font-bold text-emerald-800 dark:text-emerald-300"
                                    :title="mappedRows.get(row)?.description"
                                >
                                    {{
                                        mappedRows
                                            .get(row)
                                            ?.description?.split('-')[1]
                                            ?.trim() ||
                                        mappedRows.get(row)?.description ||
                                        'Mapeado'
                                    }}
                                </span>
                            </div>
                        </td>

                        <!-- Cells -->
                        <td
                            v-for="col in columns"
                            :key="col + row"
                            :class="[
                                'relative h-8 min-h-[32px] cursor-pointer border-r border-b px-1 py-0.5 align-top transition-all select-none',
                                activeCell === getCoordinate(col, row)
                                    ? 'z-10 ring-2 ring-primary ring-inset'
                                    : '',
                                isCellSelected(columnToNumber(col), row)
                                    ? 'bg-primary/10'
                                    : '',
                                mappedLookup.has(getCoordinate(col, row))
                                    ? 'bg-emerald-500/10 dark:bg-emerald-950/30'
                                    : '',
                                sheetConfig.date_mode === 'horizontal_series' &&
                                mappedRows.has(row) &&
                                !mappedLookup.has(getCoordinate(col, row))
                                    ? 'bg-emerald-500/5 dark:bg-emerald-950/15'
                                    : '',
                                sheetConfig.date_mode !== 'horizontal_series' &&
                                sheetConfig.date_mode !== 'cell_reference' &&
                                mappedCols.has(col) &&
                                !mappedLookup.has(getCoordinate(col, row))
                                    ? 'bg-emerald-500/5 dark:bg-emerald-950/15'
                                    : '',
                            ]"
                            @mousedown="
                                onCellMouseDown(getCoordinate(col, row), $event)
                            "
                            @mouseenter="
                                onCellMouseEnter(getCoordinate(col, row))
                            "
                            @dblclick="
                                onCellDoubleClick(getCoordinate(col, row))
                            "
                        >
                            <!-- Specific Cell Mapped Badge -->
                            <div
                                v-if="mappedLookup.has(getCoordinate(col, row))"
                                class="flex flex-col gap-0.5 leading-tight"
                            >
                                <div
                                    class="flex items-center justify-between gap-1"
                                >
                                    <span
                                        class="truncate text-[9.5px] font-bold text-emerald-800 dark:text-emerald-300"
                                    >
                                        {{
                                            mappedLookup.get(
                                                getCoordinate(col, row),
                                            )?.description || 'Mapeado'
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center gap-1 text-[8.5px] text-muted-foreground"
                                >
                                    <span
                                        v-if="
                                            (mappedLookup.get(
                                                getCoordinate(col, row),
                                            )?.multiplier ?? 1) !== 1
                                        "
                                        class="rounded bg-amber-500/20 px-1 font-bold text-amber-700 dark:text-amber-300"
                                    >
                                        x{{
                                            mappedLookup.get(
                                                getCoordinate(col, row),
                                            )?.multiplier
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            mappedLookup.get(
                                                getCoordinate(col, row),
                                            )?.date_cell
                                        "
                                        class="rounded bg-sky-500/20 px-1 text-sky-700 dark:text-sky-300"
                                    >
                                        📅{{
                                            mappedLookup.get(
                                                getCoordinate(col, row),
                                            )?.date_cell
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Sample preview value background (if available) -->
                            <div
                                v-else-if="
                                    sampleData &&
                                    sampleData[getCoordinate(col, row)] !==
                                        undefined
                                "
                                class="truncate font-mono text-[10px] leading-tight text-muted-foreground/80"
                            >
                                {{
                                    formatSampleCellValue(
                                        sampleData[getCoordinate(col, row)],
                                    )
                                }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
