import { computed, ref } from 'vue';
import type {
    ActiveEditingCell,
    ActiveEditingRowDate,
    AverageInfo,
    DataRow,
    ParameterItem,
    SystemColorStyle,
} from '@/features/data-table/types';

export const SYSTEM_COLORS: SystemColorStyle[] = [
    {
        headerBg: 'bg-blue-100/70 dark:bg-blue-950/40',
        headerText: 'text-blue-900 dark:text-blue-200',
        colBg: 'bg-blue-50/30 dark:bg-blue-950/10',
        borderCol: 'border-blue-200/60 dark:border-blue-900/40',
    },
    {
        headerBg: 'bg-emerald-100/70 dark:bg-emerald-950/40',
        headerText: 'text-emerald-900 dark:text-emerald-200',
        colBg: 'bg-emerald-50/30 dark:bg-emerald-950/10',
        borderCol: 'border-emerald-200/60 dark:border-emerald-900/40',
    },
    {
        headerBg: 'bg-amber-100/70 dark:bg-amber-950/40',
        headerText: 'text-amber-900 dark:text-amber-200',
        colBg: 'bg-amber-50/30 dark:bg-amber-950/10',
        borderCol: 'border-amber-200/60 dark:border-amber-900/40',
    },
    {
        headerBg: 'bg-purple-100/70 dark:bg-purple-950/40',
        headerText: 'text-purple-900 dark:text-purple-200',
        colBg: 'bg-purple-50/30 dark:bg-purple-950/10',
        borderCol: 'border-purple-200/60 dark:border-purple-900/40',
    },
    {
        headerBg: 'bg-rose-100/70 dark:bg-rose-950/40',
        headerText: 'text-rose-900 dark:text-rose-200',
        colBg: 'bg-rose-50/30 dark:bg-rose-950/10',
        borderCol: 'border-rose-200/60 dark:border-rose-900/40',
    },
    {
        headerBg: 'bg-cyan-100/70 dark:bg-cyan-950/40',
        headerText: 'text-cyan-900 dark:text-cyan-200',
        colBg: 'bg-cyan-50/30 dark:bg-cyan-950/10',
        borderCol: 'border-cyan-200/60 dark:border-cyan-900/40',
    },
];

export function useDataTable(teamSlugGetter: () => string) {
    const selectedSystemIds = ref<number[]>([]);
    const startDate = ref<string>('');
    const endDate = ref<string>('');
    const collapsedSystemIds = ref<number[]>([]);

    const isLoading = ref(false);
    const parameters = ref<ParameterItem[]>([]);
    const rows = ref<DataRow[]>([]);
    const averages = ref<Record<number, AverageInfo>>({});
    const isMultiSystem = ref(false);
    const cacheStatus = ref('aguardando consulta');
    const lastLoadedTime = ref<string | null>(null);

    const confirmingDeleteTimestamp = ref<string | null>(null);
    const isDeletingRow = ref<string | null>(null);

    const editingCell = ref<ActiveEditingCell | null>(null);
    const isSavingCell = ref(false);
    const cellEditError = ref<string | null>(null);

    const editingRowDate = ref<ActiveEditingRowDate | null>(null);
    const isSavingRowDate = ref(false);
    const rowDateEditError = ref<string | null>(null);

    const systemColorMap = computed(() => {
        const map: Record<number, SystemColorStyle> = {};
        const uniqueSystemIds = Array.from(
            new Set(parameters.value.map((p) => p.monitored_system_id)),
        );
        uniqueSystemIds.forEach((sysId, index) => {
            map[sysId] = SYSTEM_COLORS[index % SYSTEM_COLORS.length];
        });

        return map;
    });

    const visibleParameters = computed(() => {
        return parameters.value.filter(
            (p) => !collapsedSystemIds.value.includes(p.monitored_system_id),
        );
    });

    function toggleCollapseSystem(sysId: number) {
        if (collapsedSystemIds.value.includes(sysId)) {
            collapsedSystemIds.value = collapsedSystemIds.value.filter(
                (id) => id !== sysId,
            );
        } else {
            collapsedSystemIds.value.push(sysId);
        }
    }

    function isSystemCollapsed(sysId: number): boolean {
        return collapsedSystemIds.value.includes(sysId);
    }

    function getSystemStyle(sysId: number): SystemColorStyle {
        if (!isMultiSystem.value) {
            return {
                headerBg: '',
                headerText: '',
                colBg: '',
                borderCol: '',
            };
        }

        return systemColorMap.value[sysId] || SYSTEM_COLORS[0];
    }

    function roundNumber(num: number, decimals: number): number {
        const factor = Math.pow(10, decimals);

        return Math.round(num * factor) / factor;
    }

    function recalculateAverages() {
        const sums: Record<number, number> = {};
        const counts: Record<number, number> = {};

        parameters.value.forEach((p) => {
            sums[p.id] = 0;
            counts[p.id] = 0;
        });

        rows.value.forEach((r) => {
            parameters.value.forEach((p) => {
                const val = r.values[p.id];

                if (val !== undefined && val !== null && !isNaN(val)) {
                    sums[p.id] += Number(val);
                    counts[p.id]++;
                }
            });
        });

        const newAverages: Record<number, AverageInfo> = {};
        parameters.value.forEach((p) => {
            const count = counts[p.id];

            if (count > 0) {
                const avg = sums[p.id] / count;
                const decimals = p.decimals ?? 2;
                newAverages[p.id] = {
                    numeric: roundNumber(avg, decimals),
                    formatted: avg.toLocaleString('pt-BR', {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals,
                    }),
                };
            } else {
                newAverages[p.id] = {
                    numeric: null,
                    formatted: 'SR',
                };
            }
        });

        averages.value = newAverages;
    }

    async function loadData() {
        if (selectedSystemIds.value.length === 0) {
            parameters.value = [];
            rows.value = [];
            averages.value = {};
            isMultiSystem.value = false;
            cacheStatus.value = 'nenhum sistema selecionado';

            return;
        }

        isLoading.value = true;

        try {
            const queryParams = new URLSearchParams();
            selectedSystemIds.value.forEach((id) =>
                queryParams.append('system_ids[]', id.toString()),
            );

            if (startDate.value) {
                queryParams.append('start_date', startDate.value);
            }

            if (endDate.value) {
                queryParams.append('end_date', endDate.value);
            }

            const teamSlug = teamSlugGetter();
            const url = `/${teamSlug}/tabela-dados/data?${queryParams.toString()}`;
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Erro ao carregar dados da tabela');
            }

            const data = await response.json();
            parameters.value = data.parameters || [];
            rows.value = data.rows || [];
            averages.value = data.averages || {};
            isMultiSystem.value = data.is_multi_system || false;

            collapsedSystemIds.value = [];
            confirmingDeleteTimestamp.value = null;

            const now = new Date();
            lastLoadedTime.value = now.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit',
            });
            cacheStatus.value = `carregado às ${lastLoadedTime.value}`;
        } catch (error) {
            console.error('Falha na requisição:', error);
            cacheStatus.value = 'erro no carregamento';
        } finally {
            isLoading.value = false;
        }
    }

    function startDeleteRow(timestamp: string) {
        confirmingDeleteTimestamp.value = timestamp;
    }

    function cancelDeleteRow() {
        confirmingDeleteTimestamp.value = null;
    }

    async function confirmDeleteRow(row: DataRow) {
        isDeletingRow.value = row.timestamp;

        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content || '';
            const parameterIds = parameters.value.map((p) => p.id);
            const teamSlug = teamSlugGetter();

            const response = await fetch(`/${teamSlug}/tabela-dados/rows`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    timestamp: row.timestamp,
                    parameter_ids: parameterIds,
                }),
            });

            if (!response.ok) {
                throw new Error('Falha ao excluir a linha');
            }

            rows.value = rows.value.filter(
                (r) => r.timestamp !== row.timestamp,
            );
            recalculateAverages();
            confirmingDeleteTimestamp.value = null;
        } catch (error) {
            console.error('Erro ao excluir linha:', error);
        } finally {
            isDeletingRow.value = null;
        }
    }

    function openCellEdit(
        row: DataRow,
        param: ParameterItem,
        event: MouseEvent,
    ) {
        const target = event.currentTarget as HTMLElement | null;
        const rect = target?.getBoundingClientRect();
        const val = row.values[param.id];

        editingCell.value = {
            rowTimestamp: row.timestamp,
            date: row.date,
            time: row.time,
            parameterId: param.id,
            parameterName: param.name,
            unit: param.unit,
            decimals: param.decimals,
            originalValue: val !== undefined && val !== null ? val : null,
            currentValue: val !== undefined && val !== null ? String(val) : '',
            x: rect ? rect.left + rect.width / 2 : event.clientX,
            y: rect ? rect.bottom + 6 : event.clientY + 10,
            yUpper: rect ? rect.top : event.clientY,
        };
        cellEditError.value = null;
    }

    function closeCellEdit() {
        editingCell.value = null;
        cellEditError.value = null;
    }

    function isEditingThisCell(
        timestamp: string,
        parameterId: number,
    ): boolean {
        return (
            editingCell.value?.rowTimestamp === timestamp &&
            editingCell.value?.parameterId === parameterId
        );
    }

    async function saveCellEdit(valueOverride?: string) {
        if (!editingCell.value) {
            return;
        }

        isSavingCell.value = true;
        cellEditError.value = null;

        const cell = editingCell.value;
        const trimmedVal = (
            valueOverride !== undefined ? valueOverride : cell.currentValue
        ).trim();
        const numericVal =
            trimmedVal !== '' ? parseFloat(trimmedVal.replace(',', '.')) : null;

        if (trimmedVal !== '' && (numericVal === null || isNaN(numericVal))) {
            cellEditError.value = 'Por favor, insira um número válido.';
            isSavingCell.value = false;

            return;
        }

        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content || '';
            const teamSlug = teamSlugGetter();

            const response = await fetch(`/${teamSlug}/tabela-dados/cell`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    parameter_id: cell.parameterId,
                    timestamp: cell.rowTimestamp,
                    value: numericVal,
                }),
            });

            if (!response.ok) {
                const errData = await response.json().catch(() => ({}));

                throw new Error(
                    errData.message || 'Falha ao atualizar o valor.',
                );
            }

            const targetRow = rows.value.find(
                (r) => r.timestamp === cell.rowTimestamp,
            );

            if (targetRow) {
                if (numericVal === null) {
                    delete targetRow.values[cell.parameterId];
                } else {
                    targetRow.values[cell.parameterId] = numericVal;
                }
            }

            recalculateAverages();
            closeCellEdit();
        } catch (err: any) {
            console.error('Erro ao salvar célula:', err);
            cellEditError.value = err.message || 'Erro ao salvar alteração.';
        } finally {
            isSavingCell.value = false;
        }
    }

    function parseDateToISO(dateStr: string): string {
        if (!dateStr) {
            return '';
        }

        if (dateStr.includes('/')) {
            const [d, m, y] = dateStr.split('/');

            return `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
        }

        return dateStr;
    }

    function openRowDateEdit(row: DataRow, event: MouseEvent) {
        const target = event.currentTarget as HTMLElement | null;
        const rect = target?.getBoundingClientRect();

        editingRowDate.value = {
            oldTimestamp: row.timestamp,
            originalDate: row.date,
            originalTime: row.time,
            newDate: parseDateToISO(row.date),
            newTime: row.time.slice(0, 5),
            x: rect ? rect.left + rect.width / 2 : event.clientX,
            y: rect ? rect.bottom + 6 : event.clientY + 10,
            yUpper: rect ? rect.top : event.clientY,
        };
        rowDateEditError.value = null;
    }

    function closeRowDateEdit() {
        editingRowDate.value = null;
        rowDateEditError.value = null;
    }

    function isEditingRowDate(timestamp: string): boolean {
        return editingRowDate.value?.oldTimestamp === timestamp;
    }

    async function saveRowDateEdit(payload?: {
        newDate: string;
        newTime: string;
    }) {
        if (!editingRowDate.value) {
            return;
        }

        isSavingRowDate.value = true;
        rowDateEditError.value = null;

        const rowEdit = editingRowDate.value;
        const newDate = payload?.newDate ?? rowEdit.newDate;
        const newTime = payload?.newTime ?? rowEdit.newTime;

        if (!newDate || !newTime) {
            rowDateEditError.value = 'Por favor, informe a data e a hora.';
            isSavingRowDate.value = false;

            return;
        }

        const newTimestamp = `${newDate} ${newTime}:00`;
        const parameterIds = parameters.value.map((p) => p.id);
        const teamSlug = teamSlugGetter();

        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content || '';

            const response = await fetch(
                `/${teamSlug}/tabela-dados/rows/timestamp`,
                {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        old_timestamp: rowEdit.oldTimestamp,
                        new_timestamp: newTimestamp,
                        parameter_ids: parameterIds,
                    }),
                },
            );

            if (!response.ok) {
                const errData = await response.json().catch(() => ({}));

                throw new Error(
                    errData.message || 'Falha ao atualizar a data e hora.',
                );
            }

            const data = await response.json();

            const targetRow = rows.value.find(
                (r) => r.timestamp === rowEdit.oldTimestamp,
            );

            if (targetRow) {
                targetRow.timestamp = data.new_timestamp;
                targetRow.date = data.date;
                targetRow.time = data.time;
            }

            rows.value.sort((a, b) => a.timestamp.localeCompare(b.timestamp));
            closeRowDateEdit();
        } catch (err: any) {
            console.error('Erro ao salvar data da linha:', err);
            rowDateEditError.value = err.message || 'Erro ao salvar alteração.';
        } finally {
            isSavingRowDate.value = false;
        }
    }

    return {
        selectedSystemIds,
        startDate,
        endDate,
        collapsedSystemIds,
        isLoading,
        parameters,
        rows,
        averages,
        isMultiSystem,
        cacheStatus,
        lastLoadedTime,
        confirmingDeleteTimestamp,
        isDeletingRow,
        editingCell,
        isSavingCell,
        cellEditError,
        editingRowDate,
        isSavingRowDate,
        rowDateEditError,
        systemColorMap,
        visibleParameters,
        toggleCollapseSystem,
        isSystemCollapsed,
        getSystemStyle,
        recalculateAverages,
        loadData,
        startDeleteRow,
        cancelDeleteRow,
        confirmDeleteRow,
        openCellEdit,
        closeCellEdit,
        isEditingThisCell,
        saveCellEdit,
        openRowDateEdit,
        closeRowDateEdit,
        isEditingRowDate,
        saveRowDateEdit,
    };
}
