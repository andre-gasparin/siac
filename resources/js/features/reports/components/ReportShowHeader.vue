<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, Clock, ShieldCheck, X } from '@lucide/vue';
import { computed } from 'vue';
import {
    formatDateBr,
    formatDateTimeBr,
} from '@/features/reports/reportMetrics';
import type { Team } from '@/shared/types';

const props = withDefaults(
    defineProps<{
        report: {
            id: number;
            title: string;
            status: string;
            date_reference: string;
            finished_at?: string | null;
        };
        currentTeam: Team;
        intendedRecipientsCount?: number;
        activeRecipientsCount?: number;
        issuerName?: string | null;
        pendingDate: string;
        datePrompt: {
            open: boolean;
            date: string;
            exists: boolean;
            targetReportId?: number;
        } | null;
    }>(),
    {
        intendedRecipientsCount: 0,
        activeRecipientsCount: undefined,
        issuerName: null,
    },
);

const emit = defineEmits<{
    dateChange: [event: Event];
    cancelDatePrompt: [];
    confirmDatePrompt: [];
    openRecipients: [];
}>();

const page = usePage();
const resolvedIssuerName = computed(
    () =>
        props.issuerName ||
        (page.props.auth as { user?: { name?: string } } | undefined)?.user
            ?.name ||
        'Administrador Consucal',
);
</script>

<template>
    <div class="space-y-2.5">
        <!-- Faixa Administrativa Discreta -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-[var(--report-admin-border)] bg-[var(--report-admin-bg)] px-3.5 py-1.5 text-[11px] text-[var(--report-admin-ink)] shadow-2xs"
        >
            <div class="flex flex-wrap items-center gap-2.5">
                <span
                    class="inline-flex items-center gap-1.5 text-[10.5px] font-bold tracking-wider text-[var(--report-admin-ink)] uppercase"
                >
                    <ShieldCheck class="size-3.5" />
                    ÁREA ADMINISTRATIVA
                </span>
                <span
                    class="border-l border-[var(--report-admin-border)] pl-2.5 text-[10.5px] text-[var(--report-admin-muted)]"
                >
                    Visível somente para administradores
                </span>
            </div>

            <span
                class="text-[10.5px] font-medium text-[var(--report-admin-muted)]"
            >
                {{ report.title }}
            </span>
        </div>

        <!-- Card Unificado de Identificação + Metadados Administrativos -->
        <header
            class="overflow-visible rounded-xl border border-[var(--report-admin-card-border)] bg-[var(--report-admin-card)] text-card-foreground shadow-sm"
        >
            <!-- Faixa Institucional Azul -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-t-[11px] bg-gradient-to-r from-[#164a6c] to-[#246d91] px-4 py-3 text-white lg:px-5 dark:from-[#173d57] dark:to-[#205779]"
            >
                <div class="min-w-0 flex-1">
                    <h1
                        class="text-[15px] font-semibold tracking-tight text-white lg:text-[17px]"
                    >
                        Elaboração do Relatório de Controle Químico
                    </h1>
                </div>

                <!-- Seletor de Data de Referência -->
                <div class="relative">
                    <label
                        class="flex items-center gap-2 text-[11.5px] font-semibold whitespace-nowrap text-[#e4f2f9]"
                    >
                        <span>Alterar data</span>
                        <input
                            :value="pendingDate"
                            type="date"
                            aria-label="Alterar data do relatório"
                            class="h-8 rounded-lg border border-[#9fc3d8] bg-white px-2.5 text-xs font-semibold text-[#244157] shadow-xs focus:ring-2 focus:ring-white/60 focus:outline-hidden dark:border-[var(--border)] dark:bg-[var(--card)] dark:text-[var(--foreground)]"
                            @change="emit('dateChange', $event)"
                        />
                    </label>

                    <!-- Date Switch Confirmation Popover -->
                    <div
                        v-if="datePrompt?.open"
                        class="absolute top-full right-0 z-50 mt-2 w-72 rounded-xl border border-border bg-popover p-3.5 text-popover-foreground shadow-xl ring-1 ring-border/50 sm:w-80"
                    >
                        <p
                            class="text-xs leading-relaxed font-medium text-foreground"
                        >
                            <template v-if="datePrompt.exists">
                                Deseja visualizar o relatório de
                                <strong class="text-primary">{{
                                    formatDateBr(datePrompt.date)
                                }}</strong
                                >?
                            </template>
                            <template v-else>
                                Relatório não encontrado para
                                <strong
                                    class="text-amber-600 dark:text-amber-400"
                                    >{{ formatDateBr(datePrompt.date) }}</strong
                                >. Deseja criar um?
                            </template>
                        </p>

                        <div class="mt-3 flex items-center justify-end gap-1.5">
                            <button
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-muted/60 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                title="Cancelar"
                                @click="emit('cancelDatePrompt')"
                            >
                                <X class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs transition hover:bg-emerald-700"
                                :title="
                                    datePrompt.exists
                                        ? 'Visualizar relatório'
                                        : 'Criar relatório'
                                "
                                @click="emit('confirmDatePrompt')"
                            >
                                <Check class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid Administrativo de 4 Colunas -->
            <div
                class="grid grid-cols-1 gap-3 px-4 py-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4 lg:px-5"
            >
                <div
                    class="flex items-center gap-3 sm:pr-3 lg:border-r lg:border-[var(--report-admin-card-border)]"
                >
                    <div
                        v-if="currentTeam.logoUrl"
                        class="flex h-11 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/60 bg-white p-1.5 shadow-2xs"
                    >
                        <img
                            :src="currentTeam.logoUrl"
                            :alt="`Logo de ${currentTeam.name}`"
                            class="max-h-full max-w-full object-contain"
                        />
                    </div>
                    <div class="flex min-w-0 flex-col justify-center gap-0.5">
                        <small
                            class="text-[9.5px] font-semibold tracking-wider text-[#806e58] uppercase dark:text-[#d5c5ae]"
                        >
                            Cliente / Unidade
                        </small>
                        <strong
                            class="truncate text-[12.5px] font-semibold text-foreground"
                        >
                            {{ currentTeam.name }}
                        </strong>
                        <span class="text-[10.5px] text-muted-foreground">
                            Referência:
                            {{ formatDateBr(report.date_reference) }}
                        </span>
                    </div>
                </div>

                <div
                    class="flex flex-col justify-center gap-0.5 sm:pr-3 lg:border-r lg:border-[var(--report-admin-card-border)]"
                >
                    <small
                        class="text-[9.5px] font-semibold tracking-wider text-[#806e58] uppercase dark:text-[#d5c5ae]"
                    >
                        Responsável pela emissão
                    </small>
                    <strong class="text-[12.5px] font-semibold text-foreground">
                        {{ resolvedIssuerName }}
                    </strong>
                    <span class="text-[10.5px] text-muted-foreground">
                        Usuário autenticado
                    </span>
                </div>

                <div
                    class="flex flex-col justify-center gap-0.5 sm:pr-3 lg:border-r lg:border-[var(--report-admin-card-border)]"
                >
                    <small
                        class="text-[9.5px] font-semibold tracking-wider text-[#806e58] uppercase dark:text-[#d5c5ae]"
                    >
                        Emissão
                    </small>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-flex size-4.5 items-center justify-center rounded-full"
                            :class="
                                report.status === 'completed'
                                    ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-amber-500/20 text-amber-700 dark:text-amber-300'
                            "
                        >
                            <Check
                                v-if="report.status === 'completed'"
                                class="size-2.5"
                            />
                            <Clock v-else class="size-2.5" />
                        </span>
                        <strong
                            class="text-[12.5px] font-semibold"
                            :class="
                                report.status === 'completed'
                                    ? 'text-emerald-700 dark:text-emerald-300'
                                    : 'text-[#996323] dark:text-[#f2c17c]'
                            "
                        >
                            {{
                                report.status === 'completed'
                                    ? 'Emitido'
                                    : 'Em elaboração'
                            }}
                        </strong>
                    </div>
                    <span class="text-[10.5px] text-muted-foreground">
                        {{
                            report.status === 'completed' && report.finished_at
                                ? `Emissão em ${formatDateTimeBr(report.finished_at)}`
                                : 'Emissão pendente'
                        }}
                    </span>
                </div>

                <div class="flex flex-col justify-center gap-0.5">
                    <small
                        class="text-[9.5px] font-semibold tracking-wider text-[#806e58] uppercase dark:text-[#d5c5ae]"
                    >
                        Destinatários e envio
                    </small>
                    <strong class="text-[12.5px] font-semibold text-foreground">
                        {{
                            activeRecipientsCount ??
                            intendedRecipientsCount ??
                            0
                        }}
                        destinatário(s) ativo(s)
                    </strong>
                    <button
                        type="button"
                        class="inline-flex w-fit items-center gap-1 text-left text-[10.5px] font-semibold text-primary hover:underline"
                        @click="emit('openRecipients')"
                    >
                        Consultar / gerenciar
                    </button>
                </div>
            </div>
        </header>
    </div>
</template>
