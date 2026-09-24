<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { Calendar, MessageSquare, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import CellEditPopover from '@/features/data-table/components/CellEditPopover.vue';
import DataTableFilterBar from '@/features/data-table/components/DataTableFilterBar.vue';
import DataTableGrid from '@/features/data-table/components/DataTableGrid.vue';
import RowDateEditPopover from '@/features/data-table/components/RowDateEditPopover.vue';
import { useDataTable } from '@/features/data-table/composables/useDataTable';
import type { SystemItem } from '@/features/data-table/types';
import { ReportEditorLauncher } from '@/features/reports';
import { store as storeReport } from '@/routes/reports';
import type { Team } from '@/shared/types';

const props = defineProps<{
    systems: SystemItem[];
    currentTeam: Team;
    defaultStartDate: string;
    defaultEndDate: string;
    canManageReports: boolean;
    existingReportDates?: string[];
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Tabela de dados',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/tabela-dados`
                : '/',
        },
    ],
});

const {
    selectedSystemIds,
    startDate,
    endDate,
    isLoading,
    parameters,
    rows,
    averages,
    isMultiSystem,
    cacheStatus,
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
} = useDataTable(() => props.currentTeam.slug);

// Initial filter values
selectedSystemIds.value = props.systems.length > 0 ? [props.systems[0].id] : [];
startDate.value = props.defaultStartDate;
endDate.value = props.defaultEndDate;

const editorLauncherDate = ref<string | null>(null);

const effectiveReportDate = computed(() => {
    if (editorLauncherDate.value && editorLauncherDate.value.trim() !== '') {
        return editorLauncherDate.value;
    }

    if (endDate.value && endDate.value.trim() !== '') {
        return endDate.value;
    }

    if (rows.value.length > 0) {
        const lastRowDate =
            rows.value[0]?.date || rows.value[rows.value.length - 1]?.date;

        if (lastRowDate) {
            if (lastRowDate.includes('/')) {
                const parts = lastRowDate.split('/');

                if (parts.length === 3) {
                    return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
                }
            }

            return lastRowDate;
        }
    }

    return props.defaultEndDate;
});

const hasExistingReportForDate = computed(() => {
    if (!effectiveReportDate.value || !props.existingReportDates) {
        return false;
    }

    return props.existingReportDates.includes(effectiveReportDate.value);
});

const formattedReportDate = computed(() => {
    const dateStr = effectiveReportDate.value;

    if (!dateStr) {
        return '';
    }

    if (dateStr.includes('/')) {
        return dateStr;
    }

    const parts = dateStr.split('-');

    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    return dateStr;
});

function createConsolidatedReport() {
    router.post(storeReport.url({ current_team: props.currentTeam.slug }), {
        date_reference: effectiveReportDate.value,
    });
}

async function syncSystemFromReport(systemId: number) {
    selectedSystemIds.value = [systemId];
    await loadData();
}
</script>

<template>
    <Head title="Tabela de Dados" />

    <div class="flex w-full min-w-0 flex-col gap-3 p-3 md:p-4">
        <!-- Controls Filter Header Bar -->
        <DataTableFilterBar
            v-model:selected-system-ids="selectedSystemIds"
            v-model:start-date="startDate"
            v-model:end-date="endDate"
            :systems="systems"
            :is-loading="isLoading"
            :cache-status="cacheStatus"
            @load="loadData"
        />

        <!-- Data Table Container -->
        <DataTableGrid
            :parameters="parameters"
            :visible-parameters="visibleParameters"
            :rows="rows"
            :averages="averages"
            :is-multi-system="isMultiSystem"
            :is-loading="isLoading"
            :system-color-map="systemColorMap"
            :is-system-collapsed="isSystemCollapsed"
            :get-system-style="getSystemStyle"
            :confirming-delete-timestamp="confirmingDeleteTimestamp"
            :is-deleting-row="isDeletingRow"
            :is-editing-this-cell="isEditingThisCell"
            :is-editing-row-date="isEditingRowDate"
            @toggle-collapse-system="toggleCollapseSystem"
            @start-delete-row="startDeleteRow"
            @cancel-delete-row="cancelDeleteRow"
            @confirm-delete-row="confirmDeleteRow"
            @open-cell-edit="openCellEdit"
            @open-row-date-edit="openRowDateEdit"
        />

        <!-- Actions / Report Launcher Bar -->
        <div
            v-if="canManageReports"
            class="mt-3 flex flex-wrap items-center gap-2"
        >
            <ReportEditorLauncher
                :current-team="currentTeam"
                :systems="systems"
                :selected-system-ids="selectedSystemIds"
                :default-date="effectiveReportDate"
                button-label="Editar considerações Consucal"
                @system-change="syncSystemFromReport"
                @date-change="(d) => (editorLauncherDate = d)"
            />

            <button
                type="button"
                data-test="create-report"
                class="inline-flex h-9 items-center gap-2 rounded-md bg-blue-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-blue-700"
                @click="createConsolidatedReport"
            >
                <Pencil v-if="hasExistingReportForDate" class="size-4" />
                <MessageSquare v-else class="size-4" />
                {{
                    hasExistingReportForDate
                        ? 'Editar relatório'
                        : 'Criar relatório'
                }}
            </button>

            <div
                v-if="formattedReportDate"
                class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-card px-3 text-xs font-medium text-muted-foreground shadow-sm"
            >
                <Calendar
                    class="size-4 text-emerald-600 dark:text-emerald-400"
                />
                <span>
                    Data do relatório:
                    <strong class="font-semibold text-foreground">{{
                        formattedReportDate
                    }}</strong>
                </span>
            </div>
        </div>

        <!-- Fixed Floating Tooltip/Popover Editor -->
        <CellEditPopover
            :cell="editingCell"
            :is-saving="isSavingCell"
            :error="cellEditError"
            @close="closeCellEdit"
            @save="saveCellEdit"
        />

        <!-- Fixed Floating Tooltip/Popover for Editing Row Date & Time -->
        <RowDateEditPopover
            :row-date="editingRowDate"
            :is-saving="isSavingRowDate"
            :error="rowDateEditError"
            @close="closeRowDateEdit"
            @save="saveRowDateEdit"
        />
    </div>
</template>
