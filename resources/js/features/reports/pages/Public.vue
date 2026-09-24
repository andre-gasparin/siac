<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClientReportContent from '@/features/reports/components/ClientReportContent.vue';
import { show as publicChart } from '@/routes/reports/public/charts';
import { store as storePublicComment } from '@/routes/reports/public/comments';

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
}

defineProps<{
    report: {
        id: number;
        title: string;
        status: string;
        date_reference: string;
        team_name: string;
        finished_at?: string | null;
    };
    recipient?: RecipientInfo | null;
    recipients?: RecipientInfo[];
    systems: SystemSection[];
    dataBySystem: Record<number, ReportData>;
}>();

const token = window.location.pathname.split('/').filter(Boolean).at(-1) ?? '';

function getCommentSubmitUrl(): string {
    return storePublicComment.url(token);
}

function getChartUrl(
    systemId: number,
    parameterId: number,
    type: 'line' | 'bar',
): string {
    return publicChart.url(
        { token, system: systemId },
        { query: { parameter_id: parameterId, type } },
    );
}
</script>

<template>
    <Head :title="report.title">
        <meta name="robots" content="noindex,nofollow" />
        <meta name="referrer" content="no-referrer" />
    </Head>

    <main
        class="min-h-screen bg-slate-100 text-slate-950 dark:bg-neutral-950 dark:text-white"
    >
        <ClientReportContent
            :report="report"
            :systems="systems"
            :data-by-system="dataBySystem"
            :recipients="recipients"
            :recipient="recipient"
            :is-preview="false"
            :comment-submit-url-generator="getCommentSubmitUrl"
            :chart-url-generator="getChartUrl"
        />
    </main>
</template>
