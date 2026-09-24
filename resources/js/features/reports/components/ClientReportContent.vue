<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    Clock,
    Layers,
    Mail,
    MessageSquare,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { reactive, ref } from 'vue';
import ParameterChartModal from '@/features/reports/components/ParameterChartModal.vue';
import ReportHorizontalDataTable from '@/features/reports/components/ReportHorizontalDataTable.vue';
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

const props = withDefaults(
    defineProps<{
        report: {
            id: number;
            title: string;
            status: string;
            date_reference: string;
            team_name: string;
            finished_at?: string | null;
        };
        systems: SystemSection[];
        dataBySystem: Record<number, ReportData>;
        recipients?: RecipientInfo[];
        recipient?: RecipientInfo | null;
        isPreview?: boolean;
        currentTeam?: Team;
        commentSubmitUrlGenerator: (systemItemId?: number) => string;
        chartUrlGenerator: (
            systemId: number,
            parameterId: number,
            type: 'line' | 'bar',
        ) => string;
    }>(),
    {
        recipients: () => [],
        recipient: null,
        isPreview: false,
    },
);

const commentBodies = reactive<Record<number, string>>({});
const isSubmittingComment = reactive<Record<number, boolean>>({});
const showRecipientsList = ref(false);

const chart = ref<{ open: boolean; url: string; title: string }>({
    open: false,
    url: '',
    title: '',
});

function publishComment(system: SystemSection) {
    const body = commentBodies[system.item.id]?.trim();

    if (!body || isSubmittingComment[system.item.id]) {
        return;
    }

    isSubmittingComment[system.item.id] = true;
    const url = props.commentSubmitUrlGenerator(system.item.id);

    router.post(
        url,
        {
            report_item_id: system.item.id,
            body,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                commentBodies[system.item.id] = '';
            },
            onFinish: () => {
                isSubmittingComment[system.item.id] = false;
            },
        },
    );
}

function openChart(
    system: SystemSection,
    parameterId: number,
    type: 'line' | 'bar',
) {
    const parameter = props.dataBySystem[system.id]?.parameters.find(
        (item) => item.id === parameterId,
    );
    chart.value = {
        open: true,
        title: `${system.name} · ${parameter?.name ?? 'Parâmetro'} (${type === 'bar' ? 'barras' : 'linha'})`,
        url: props.chartUrlGenerator(system.id, parameterId, type),
    };
}

function formatDate(value: string | null | undefined) {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('pt-BR', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function formatDateOnly(dateStr: string) {
    if (!dateStr) {
        return '—';
    }

    try {
        const [year, month, day] = dateStr.split('-');

        if (year && month && day) {
            return `${day}/${month}/${year}`;
        }

        return new Date(`${dateStr}T12:00:00`).toLocaleDateString('pt-BR');
    } catch {
        return dateStr;
    }
}
</script>

<template>
    <div class="mx-auto grid w-full max-w-[1600px] gap-6 p-4 lg:p-7">
        <!-- Banner de Pré-visualização do Administrador -->
        <aside
            v-if="isPreview"
            aria-label="Aviso de modo de visualização"
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm text-amber-900 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex size-2.5 animate-pulse rounded-full bg-amber-500"
                />
                <strong>Modo de Pré-visualização do Administrador:</strong>
                <span
                    >Você está visualizando o relatório no mesmo formato
                    acessado pelo cliente. Seus comentários serão identificados
                    como Consucal.</span
                >
            </div>

            <a
                v-if="currentTeam"
                :href="`/${currentTeam.slug}/relatorios/${report.id}`"
                class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-950 shadow-2xs transition hover:bg-amber-100 dark:border-amber-800 dark:bg-neutral-900 dark:text-amber-200 dark:hover:bg-neutral-800"
            >
                <ArrowLeft class="size-3.5" /> Voltar ao Painel do Administrador
            </a>
        </aside>

        <!-- Cabeçalho Executivo -->
        <header
            class="overflow-hidden rounded-3xl border border-border bg-card p-5 text-card-foreground shadow-sm lg:p-7"
        >
            <div class="flex flex-wrap items-start justify-between gap-5">
                <!-- Identificação do Relatório e Logo -->
                <div class="flex items-center gap-4">
                    <img
                        src="/logo.png"
                        alt="Consucal"
                        class="h-12 w-auto object-contain"
                    />
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl"
                            >
                                {{ report.title }}
                            </h1>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="
                                    report.status === 'completed'
                                        ? 'bg-emerald-500/15 text-emerald-700 ring-1 ring-emerald-500/30 dark:text-emerald-300'
                                        : 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/30 dark:text-amber-300'
                                "
                            >
                                {{
                                    report.status === 'completed'
                                        ? 'Finalizado'
                                        : 'Em Elaboração'
                                }}
                            </span>
                        </div>

                        <p
                            class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                        >
                            <span class="font-medium text-foreground">{{
                                report.team_name
                            }}</span>
                            <span>·</span>
                            <span class="inline-flex items-center gap-1">
                                <CalendarDays
                                    class="size-4 text-muted-foreground"
                                />
                                {{ formatDateOnly(report.date_reference) }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Badge de Usuário Ativo / Acesso Individual -->
                <div
                    v-if="recipient"
                    class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-950 dark:text-emerald-200"
                >
                    <p class="flex items-center gap-2 font-semibold">
                        <ShieldCheck
                            class="size-4 text-emerald-600 dark:text-emerald-400"
                        />
                        {{
                            recipient.is_admin
                                ? 'Administrador Consucal'
                                : 'Acesso Individual do Destinatário'
                        }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ recipient.name }} · {{ recipient.email }}
                    </p>
                </div>
            </div>

            <!-- Cards Resumo de Estatísticas -->
            <div
                class="mt-6 grid grid-cols-1 gap-3 border-t border-border/60 pt-5 sm:grid-cols-3"
            >
                <div class="rounded-2xl border bg-muted/25 px-4 py-3">
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <Layers class="size-3.5" />
                        Sistemas Habilitados
                    </div>
                    <strong
                        class="mt-1 block text-xl font-bold text-foreground"
                    >
                        {{ systems.length }} sistema(s)
                    </strong>
                </div>

                <div class="rounded-2xl border bg-muted/25 px-4 py-3">
                    <div
                        class="flex items-center gap-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <CalendarDays class="size-3.5" />
                        Data de Referência
                    </div>
                    <strong
                        class="mt-1 block text-xl font-bold text-foreground"
                    >
                        {{ formatDateOnly(report.date_reference) }}
                    </strong>
                </div>

                <div class="rounded-2xl border bg-muted/25 px-4 py-3">
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <Users class="size-3.5" />
                            Destinatários Enviados
                        </div>
                        <button
                            v-if="recipients.length > 0"
                            type="button"
                            class="text-[11px] font-medium text-primary hover:underline"
                            @click="showRecipientsList = !showRecipientsList"
                        >
                            {{ showRecipientsList ? 'Ocultar' : 'Ver todos' }}
                        </button>
                    </div>
                    <strong
                        class="mt-1 block text-xl font-bold text-foreground"
                    >
                        {{ recipients.length }} destinatário(s)
                    </strong>
                </div>
            </div>

            <!-- Seção Expansível ou Exibição de Destinatários do Envio -->
            <div
                v-if="recipients.length > 0"
                class="mt-4 rounded-2xl border border-border/80 bg-muted/15 p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Mail class="size-4 text-primary" />
                        <h2
                            class="text-xs font-semibold tracking-wide text-foreground uppercase"
                        >
                            Destinatários que receberam este relatório
                        </h2>
                        <span
                            class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                        >
                            {{ recipients.length }}
                        </span>
                    </div>

                    <button
                        type="button"
                        class="text-xs font-medium text-muted-foreground transition hover:text-foreground"
                        @click="showRecipientsList = !showRecipientsList"
                    >
                        {{
                            showRecipientsList
                                ? 'Recolher lista'
                                : 'Expandir lista'
                        }}
                    </button>
                </div>

                <div
                    v-show="showRecipientsList"
                    class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="dest in recipients"
                        :key="dest.email"
                        class="flex items-center justify-between gap-2.5 rounded-xl border border-border/60 bg-card p-2.5 text-xs shadow-2xs"
                    >
                        <div class="min-w-0 flex-1">
                            <strong
                                class="block truncate font-medium text-foreground"
                            >
                                {{ dest.name }}
                            </strong>
                            <span
                                class="block truncate text-[11px] text-muted-foreground"
                            >
                                {{ dest.email }}
                            </span>
                        </div>

                        <div class="shrink-0 text-right">
                            <span
                                v-if="dest.last_sent_at"
                                class="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300"
                                :title="`Enviado em ${formatDate(dest.last_sent_at)}`"
                            >
                                <CheckCircle2 class="size-3 text-emerald-600" />
                                {{ formatDate(dest.last_sent_at) }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground"
                            >
                                <Clock class="size-3" /> Pendente
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Estado Vazio se nenhum sistema habilitado -->
        <div
            v-if="systems.length === 0"
            class="flex flex-col items-center justify-center gap-2 rounded-3xl border border-dashed border-border bg-card/60 py-16 text-center"
        >
            <p class="text-sm font-medium text-foreground">
                Nenhum sistema habilitado ou com dados cadastrados neste
                relatório.
            </p>
        </div>

        <!-- Lista de Sistemas Habilitados -->
        <section
            v-for="system in systems"
            :key="system.id"
            class="overflow-hidden rounded-3xl border border-emerald-500/40 bg-card shadow-sm ring-1 ring-emerald-500/20"
        >
            <!-- Cabeçalho da Seção do Sistema -->
            <header
                class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-500/20 bg-emerald-500/10 px-4 py-3"
            >
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-semibold text-foreground">
                        {{ system.name }}
                    </h2>
                    <span
                        class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-500/30 dark:text-emerald-300"
                    >
                        Habilitado
                    </span>
                </div>

                <span
                    v-if="system.item.is_stopped"
                    class="rounded-full bg-amber-400/20 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-500/30 dark:text-amber-300"
                >
                    Sistema parado
                </span>
            </header>

            <!-- Tabela Horizontal Transposta de Dados -->
            <div
                v-if="system.item.show_data_results && !system.item.hide_data"
                class="p-4"
            >
                <ReportHorizontalDataTable
                    :parameters="dataBySystem[system.id]?.parameters ?? []"
                    :rows="dataBySystem[system.id]?.rows ?? []"
                    :averages="dataBySystem[system.id]?.averages"
                    @chart="(paramId, type) => openChart(system, paramId, type)"
                />
            </div>

            <!-- Considerações Consucal (Somente Leitura, sem botões de edição ou IA) -->
            <div
                v-if="system.item.comment"
                class="border-t border-blue-100 bg-blue-50/30 p-5 dark:border-blue-950/80 dark:bg-blue-950/15"
            >
                <div class="mb-2.5 flex items-center gap-2">
                    <h3
                        class="text-xs font-semibold tracking-wider text-blue-900 uppercase dark:text-blue-200"
                    >
                        Considerações Consucal
                    </h3>
                </div>
                <div
                    class="prose prose-sm dark:prose-invert max-w-none rounded-2xl border border-blue-200/60 bg-card p-4 leading-relaxed text-foreground shadow-2xs dark:border-blue-900/40"
                    v-html="system.item.comment"
                />
            </div>

            <!-- Bloco de Comentários do Sistema -->
            <div class="grid gap-3.5 border-t border-border/60 bg-card p-5">
                <div class="flex items-center justify-between">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold text-foreground"
                    >
                        <MessageSquare class="size-4 text-primary" />
                        <span>Comentários</span>
                        <span
                            v-if="system.item.comments.length > 0"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            {{ system.item.comments.length }}
                        </span>
                    </h3>
                </div>

                <!-- Lista de comentários existentes -->
                <div
                    v-if="system.item.comments.length === 0"
                    class="rounded-2xl border border-dashed border-border/80 p-4 text-center text-xs text-muted-foreground"
                >
                    Nenhum comentário registrado para este sistema ainda.
                </div>

                <div
                    v-for="comment in system.item.comments"
                    :key="comment.id"
                    class="rounded-2xl border p-3.5 text-sm transition"
                    :class="
                        comment.is_consucal
                            ? 'border-blue-200 bg-blue-50/50 dark:border-blue-900/60 dark:bg-blue-950/20'
                            : 'border-border bg-muted/20'
                    "
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex items-center gap-2">
                            <strong class="font-semibold text-foreground">
                                {{ comment.author }}
                            </strong>
                            <span
                                v-if="comment.is_consucal"
                                class="rounded-md bg-blue-600/10 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700 dark:text-blue-300"
                            >
                                Consucal
                            </span>
                            <span
                                v-else
                                class="rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                            >
                                Cliente
                            </span>
                        </div>

                        <time class="text-xs text-muted-foreground">
                            {{ formatDate(comment.created_at) }}
                        </time>
                    </div>

                    <p class="mt-2 whitespace-pre-wrap text-foreground/90">
                        {{ comment.body }}
                    </p>
                </div>

                <!-- Formulário de Comentário -->
                <form
                    class="mt-1 flex flex-col gap-2.5 sm:flex-row"
                    @submit.prevent="publishComment(system)"
                >
                    <input
                        v-model="commentBodies[system.item.id]"
                        maxlength="5000"
                        placeholder="Escreva um comentário para este sistema…"
                        class="h-10 flex-1 rounded-xl border border-input bg-background px-3.5 text-sm text-foreground shadow-2xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <button
                        type="submit"
                        :disabled="
                            isSubmittingComment[system.item.id] ||
                            !commentBodies[system.item.id]?.trim()
                        "
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-5 text-xs font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 disabled:opacity-50"
                    >
                        <span v-if="isSubmittingComment[system.item.id]"
                            >Enviando…</span
                        >
                        <span v-else>Comentar</span>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <!-- Modal de Gráfico de Parâmetro -->
    <ParameterChartModal
        :open="chart.open"
        :url="chart.url"
        :title="chart.title"
        @close="chart.open = false"
    />
</template>
