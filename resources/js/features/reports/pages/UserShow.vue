<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import ClientReportContent from '@/features/reports/components/ClientReportContent.vue';
import { store as storeUserComment } from '@/routes/reports/user/comments';
import { chart as userChart } from '@/routes/reports/user/systems';
import type { Team } from '@/shared/types';

interface ReportComment {
    id: number;
    author: string;
    body: string;
    is_consucal: boolean;
    created_at: string;
}

interface ReportItem {
    id: number;
    show_data_results: boolean;
    hide_data: boolean;
    is_stopped: boolean;
    comment: string | null;
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
    name: string;
    email: string;
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
        finished_at: string | null;
    };
    systems: SystemSection[];
    dataBySystem: Record<number, ReportData>;
    recipients?: RecipientInfo[];
    recipient?: RecipientInfo | null;
    currentTeam: Team;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Relatórios',
            href: `/${props.currentTeam.slug}/meus-relatorios`,
        },
        { title: props.report.title, href: '#' },
    ],
});

function getCommentSubmitUrl(): string {
    return storeUserComment.url({
        current_team: props.currentTeam.slug,
        report: props.report.id,
    });
}

function getChartUrl(
    systemId: number,
    parameterId: number,
    type: 'line' | 'bar',
): string {
    return userChart.url(
        {
            current_team: props.currentTeam.slug,
            report: props.report.id,
            system: systemId,
        },
        { query: { parameter_id: parameterId, type } },
    );
}
</script>

<template>
    <Head :title="report.title" />

    <ClientReportContent
        :report="report"
        :systems="systems"
        :data-by-system="dataBySystem"
        :recipients="recipients"
        :recipient="recipient"
        :current-team="currentTeam"
        :is-preview="false"
        :comment-submit-url-generator="getCommentSubmitUrl"
        :chart-url-generator="getChartUrl"
    />
</template>
