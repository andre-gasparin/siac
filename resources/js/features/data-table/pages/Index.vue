<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';
import CellEditPopover from '@/features/data-table/components/CellEditPopover.vue';
import DataTableFilterBar from '@/features/data-table/components/DataTableFilterBar.vue';
import DataTableGrid from '@/features/data-table/components/DataTableGrid.vue';
import RowDateEditPopover from '@/features/data-table/components/RowDateEditPopover.vue';
import { useDataTable } from '@/features/data-table/composables/useDataTable';
import type {
    DataRow,
    ParameterItem,
    SystemItem,
} from '@/features/data-table/types';
import { index } from '@/routes/data-table';
import type { Team } from '@/shared/types';

const DataTableChartDialog = defineAsyncComponent(
    () => import('@/features/data-table/components/DataTableChartDialog.vue'),
);
const DataTableRowDetailsDialog = defineAsyncComponent(
    () =>
        import('@/features/data-table/components/DataTableRowDetailsDialog.vue'),
);
const props = defineProps<{
    systems: SystemItem[];
    currentTeam: Team;
    defaultStartDate: string;
    defaultEndDate: string;
}>();
setLayoutProps({
    breadcrumbs: [
        {
            title: 'Tabela de dados',
            href: index.url({ current_team: props.currentTeam.slug }),
        },
    ],
});
const {
    selectedSystemIds,
    startDate,
    endDate,
    ewmaOutsideLimits,
    ewmaAll,
    fitToScreen,
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
selectedSystemIds.value = props.systems.length ? [props.systems[0].id] : [];
startDate.value = props.defaultStartDate;
endDate.value = props.defaultEndDate;
const rowDetails = ref<{ row: DataRow; tab: 'history' | 'comments' } | null>(
    null,
);
const chart = ref<{ parameter: ParameterItem; type: 'line' | 'bar' } | null>(
    null,
);
const loadedSystemIds = computed(() => [
    ...new Set(
        parameters.value.map((parameter) => parameter.monitored_system_id),
    ),
]);
</script>

<template>
    <div class="flex w-full min-w-0 flex-col gap-3 p-3 md:p-4">
        <Head title="Tabela de Dados" />
        <DataTableFilterBar
            v-model:selected-system-ids="selectedSystemIds"
            v-model:start-date="startDate"
            v-model:end-date="endDate"
            v-model:ewma-outside-limits="ewmaOutsideLimits"
            v-model:ewma-all="ewmaAll"
            v-model:fit-to-screen="fitToScreen"
            :systems="systems"
            :is-loading="isLoading"
            :cache-status="cacheStatus"
            @load="loadData"
        />
        <DataTableGrid
            :parameters="parameters"
            :visible-parameters="visibleParameters"
            :rows="rows"
            :averages="averages"
            :is-multi-system="isMultiSystem"
            :is-loading="isLoading"
            :fit-to-screen="fitToScreen"
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
            @open-details="(row, tab) => (rowDetails = { row, tab })"
            @open-chart="(parameter, type) => (chart = { parameter, type })"
        />
        <CellEditPopover
            :cell="editingCell"
            :is-saving="isSavingCell"
            :error="cellEditError"
            @close="closeCellEdit"
            @save="saveCellEdit"
        />
        <RowDateEditPopover
            :row-date="editingRowDate"
            :is-saving="isSavingRowDate"
            :error="rowDateEditError"
            @close="closeRowDateEdit"
            @save="saveRowDateEdit"
        />
        <DataTableRowDetailsDialog
            v-if="rowDetails"
            :key="rowDetails.row.timestamp"
            :team-slug="currentTeam.slug"
            :row="rowDetails.row"
            :system-ids="loadedSystemIds"
            :tab="rowDetails.tab"
            @close="rowDetails = null"
        />
        <DataTableChartDialog
            v-if="chart"
            :parameter="chart.parameter"
            :rows="rows"
            :type="chart.type"
            @close="chart = null"
        />
    </div>
</template>
