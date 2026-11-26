<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClientReportContent from '@/features/reports/components/ClientReportContent.vue';
import ReportRecipientsModal from '@/features/reports/components/ReportRecipientsModal.vue';
import type {
    DeliveryHistoryEntry,
    ReportRecipientItem,
} from '@/features/reports/components/ReportRecipientsModal.vue';
import { useReportDeliverySync } from '@/features/reports/composables/useReportDeliverySync';
import { notify as notifyPreviewInteractions } from '@/routes/reports/preview/interactions';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';
import type { Team } from '@/shared/types';

interface ReportComment {
    id: number;
    author: string;
    body: string;
    is_consucal: boolean;
    is_own?: boolean;
    created_at: string;
    notified_at?: string | null;
}

interface ReportItem {
    id: number;
    show_data_results: boolean;
    hide_data: boolean;
    is_stopped: boolean;
    comment: string | null;
    document?: Record<string, unknown> | null;
    charts?: Record<string, ChartSeries[]>;
    comments: ReportComment[];
}

interface SystemSection {
    id: number;
    name: string;
    sort_order: number;
    item: ReportItem;
}

interface ReportData {
    parameters: Array<{
        id: number;
        name: string;
        code?: string | null;
        tag?: string | null;
        unit?: string | null;
        decimals: number;
        alert_1_min: number | null;
        alert_1_max: number | null;
        monitored_system_id?: number | null;
        system_name?: string | null;
    }>;
    rows: Array<{
        timestamp: string;
        time: string;
        date?: string;
        values: Record<number, number>;
    }>;
    averages?: Record<number, { numeric: number | null; formatted: string }>;
}

interface RecipientInfo {
    id?: number;
    user_id?: number | null;
    name: string;
    email: string;
    access_token?: string;
    public_url?: string;
    revoked_at?: string | null;
    last_accessed_at?: string | null;
    last_sent_at?: string | null;
    send_count?: number;
    is_admin?: boolean;
}

const props = defineProps<{
    report: {
        id: number;
        title: string;
        status: string;
        date_reference: string;
        team_name: string;
        team_logo_url?: string | null;
        finished_at?: string | null;
    };
    systems: SystemSection[];
    dataBySystem: Record<number, ReportData>;
    recipients?: ReportRecipientItem[];
    deliveryHistory?: DeliveryHistoryEntry[];
    recipient?: RecipientInfo | null;
    activeRecipientsCount?: number;
    currentTeam: Team;
}>();

const recipientsOpen = ref(false);

useReportDeliverySync({
    reportId: () => props.report.id,
    recipients: () => props.recipients ?? [],
    reloadProps: [
        'report',
        'recipients',
        'activeRecipientsCount',
        'deliveryHistory',
    ],
});

function getCommentSubmitUrl(): string {
    return `/${props.currentTeam.slug}/relatorios/${props.report.id}/preview/comentarios`;
}

function getChartUrl(
    systemId: number,
    parameterId: number,
    type: 'line' | 'bar',
): string {
    return `/${props.currentTeam.slug}/relatorios/${props.report.id}/sistemas/${systemId}/grafico?parameter_id=${parameterId}&type=${type}`;
}
</script>

<template>
    <Head :title="`Pré-visualização: ${report.title}`" />

    <div
        class="report-view-theme min-h-full w-full min-w-0 flex-1 bg-background text-foreground"
    >
        <ClientReportContent
            :report="report"
            :systems="systems"
            :data-by-system="dataBySystem"
            :recipients="recipients"
            :recipient="recipient"
            :active-recipients-count="activeRecipientsCount"
            :current-team="currentTeam"
            :is-preview="true"
            :comment-submit-url-generator="getCommentSubmitUrl"
            :notify-interactions-url="
                notifyPreviewInteractions.url({
                    current_team: currentTeam.slug,
                    report: report.id,
                })
            "
            :chart-url-generator="getChartUrl"
            @open-recipients="recipientsOpen = true"
        />

        <ReportRecipientsModal
            :show="recipientsOpen"
            :recipients="recipients ?? []"
            :delivery-history="deliveryHistory"
            :team-slug="currentTeam.slug"
            :report-id="report.id"
            :report-status="report.status"
            :report-date-reference="report.date_reference"
            @close="recipientsOpen = false"
        />
    </div>
</template>
