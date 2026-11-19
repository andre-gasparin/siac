<script setup lang="ts">
import {
    Calendar,
    Check,
    ExternalLink,
    History,
    Layers,
    Mail,
    RefreshCw,
    ShieldCheck,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { resolveReportSystemStatus } from '@/features/reports/systemStatus';
import type { ReportSystemStatus } from '@/features/reports/systemStatus';
import type { Team } from '@/shared/types';

interface SystemSectionItem {
    id: number;
    name: string;
    item: {
        show_data_results: boolean;
        comment?: string | null;
        parent_monitored_system_id?: number | null;
    } | null;
}

const props = defineProps<{
    report: {
        id: number;
        title: string;
        status: string;
        date_reference: string;
        email_count: number;
    };
    currentTeam: Team;
    sections: SystemSectionItem[];
    editingSystems?: Record<number, boolean>;
    intendedRecipientsCount?: number;
    activeRecipientsCount?: number;
    pendingDate: string;
    tableStartDate?: string;
    tableEndDate?: string;
    datePrompt: {
        open: boolean;
        date: string;
        exists: boolean;
        targetReportId?: number;
    } | null;
}>();

const emit = defineEmits<{
    dateChange: [event: Event];
    'update:tableStartDate': [value: string];
    'update:tableEndDate': [value: string];
    tablePeriodChange: [dates: { start_date: string; end_date: string }];
    cancelDatePrompt: [];
    confirmDatePrompt: [];
    openHistory: [];
    openRecipients: [];
    sendAgain: [];
    finish: [];
}>();

const systemsPopoverOpen = ref(false);
const systemsPopoverRef = ref<HTMLElement | null>(null);

const STATUS_META: Record<
    ReportSystemStatus,
    { label: string; badgeClass: string; dotClass: string }
> = {
    enabled: {
        label: 'Habilitado',
        badgeClass:
            'bg-emerald-500/15 font-semibold text-emerald-600 dark:text-emerald-400',
        dotClass: 'bg-emerald-500',
    },
    editing: {
        label: 'Em edição',
        badgeClass:
            'bg-amber-500/15 font-semibold text-amber-600 dark:text-amber-400',
        dotClass: 'bg-amber-500',
    },
    disabled: {
        label: 'Desabilitado',
        badgeClass:
            'bg-red-500/15 font-semibold text-red-600 dark:text-red-400',
        dotClass: 'bg-red-500',
    },
};

function getSystemStatus(system: SystemSectionItem): ReportSystemStatus {
    return resolveReportSystemStatus(
        system.item,
        Boolean(props.editingSystems?.[system.id]),
    );
}

const sectionsWithStatus = computed(() =>
    props.sections.map((system) => {
        const status = getSystemStatus(system);

        return {
            ...system,
            status,
            statusMeta: STATUS_META[status],
        };
    }),
);

const totalSystems = computed(() => sectionsWithStatus.value.length);
const enabledSystems = computed(
    () => sectionsWithStatus.value.filter((s) => s.status === 'enabled').length,
);
const editingSystemsCount = computed(
    () => sectionsWithStatus.value.filter((s) => s.status === 'editing').length,
);
const disabledSystems = computed(
    () =>
        sectionsWithStatus.value.filter((s) => s.status === 'disabled').length,
);
const percentage = computed(() =>
    totalSystems.value > 0
        ? Math.round((enabledSystems.value / totalSystems.value) * 100)
        : 0,
);
const editingPercentage = computed(() =>
    totalSystems.value > 0
        ? Math.min(
              100 - percentage.value,
              Math.round(
                  (editingSystemsCount.value / totalSystems.value) * 100,
              ),
          )
        : 0,
);

function scrollToSystem(systemId: number) {
    systemsPopoverOpen.value = false;
    const el = document.querySelector(`[data-report-system-id="${systemId}"]`);

    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function handleClickOutside(event: MouseEvent) {
    if (
        systemsPopoverOpen.value &&
        systemsPopoverRef.value &&
        !systemsPopoverRef.value.contains(event.target as Node)
    ) {
        systemsPopoverOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

function formatDateBr(value: string | null): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');

    if (year && month && day) {
        return `${day}/${month}/${year}`;
    }

    return value;
}
</script>

<template>
    <header
        class="overflow-visible rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm lg:p-6"
    >
        <!-- Topo do Card: Identificação à esquerda e Histórico no canto superior direito -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-lg font-semibold tracking-tight text-foreground lg:text-xl"
                    >
                        {{ report.title }}
                    </h1>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            report.status === 'completed'
                                ? 'bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/30 dark:text-emerald-400'
                                : 'bg-amber-500/15 text-amber-600 ring-1 ring-amber-500/30 dark:text-amber-400'
                        "
                    >
                        {{
                            report.status === 'completed'
                                ? 'Finalizado'
                                : 'Rascunho'
                        }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ currentTeam.name }} ·
                    {{
                        new Date(
                            `${report.date_reference}T12:00:00`,
                        ).toLocaleDateString('pt-BR')
                    }}
                </p>
            </div>

            <!-- Botão de Histórico no canto superior direito -->
            <button
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-lg border border-border bg-muted/50 px-3 text-xs font-medium text-foreground transition hover:bg-muted"
                title="Histórico de atividades"
                aria-label="Histórico de atividades"
                @click="emit('openHistory')"
            >
                <History class="size-4 text-muted-foreground" />
                <span>Histórico</span>
            </button>
        </div>

        <!-- Barra inferior: ferramentas e ações do relatório -->
        <div
            class="mt-4 flex flex-wrap items-center gap-2 border-t border-border/50 pt-4"
        >
            <!-- Seletor de Data com Tooltip de Confirmação -->
            <div class="relative">
                <div
                    class="flex items-center gap-1.5 rounded-lg border border-border bg-muted/40 px-2.5 py-1.5 text-xs shadow-2xs"
                >
                    <Calendar class="size-3.5 text-muted-foreground" />
                    <span class="font-medium text-muted-foreground">Data:</span>
                    <input
                        :value="pendingDate"
                        type="date"
                        class="h-6 rounded-md border border-input bg-background px-1.5 text-xs font-semibold text-foreground"
                        @change="emit('dateChange', $event)"
                    />
                </div>

                <!-- Date Switch Tooltip / Confirmation -->
                <div
                    v-if="datePrompt?.open"
                    class="absolute top-full left-0 z-50 mt-2 w-72 rounded-xl border border-border bg-popover p-3.5 text-popover-foreground shadow-xl ring-1 ring-border/50 sm:w-80"
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

            <!-- Seletor do Período das Tabelas (Global) -->
            <div
                class="flex items-center gap-1.5 rounded-lg border border-border bg-muted/40 px-2.5 py-1.5 text-xs shadow-2xs"
            >
                <Calendar class="size-3.5 text-muted-foreground" />
                <span class="font-medium text-muted-foreground">Tabelas:</span>
                <input
                    :value="tableStartDate"
                    type="date"
                    class="h-6 rounded-md border border-input bg-background px-1.5 text-xs font-semibold text-foreground"
                    title="Data inicial das tabelas"
                    @change="
                        emit(
                            'update:tableStartDate',
                            ($event.target as HTMLInputElement).value,
                        );
                        emit('tablePeriodChange', {
                            start_date: ($event.target as HTMLInputElement)
                                .value,
                            end_date: tableEndDate ?? '',
                        });
                    "
                />
                <span class="text-muted-foreground">até</span>
                <input
                    :value="tableEndDate"
                    type="date"
                    class="h-6 rounded-md border border-input bg-background px-1.5 text-xs font-semibold text-foreground"
                    title="Data final das tabelas"
                    @change="
                        emit(
                            'update:tableEndDate',
                            ($event.target as HTMLInputElement).value,
                        );
                        emit('tablePeriodChange', {
                            start_date: tableStartDate ?? '',
                            end_date: ($event.target as HTMLInputElement).value,
                        });
                    "
                />
            </div>

            <!-- Botão de Sistemas Incluídos com Barra de Progresso e Dropdown -->
            <div ref="systemsPopoverRef" class="relative">
                <button
                    type="button"
                    data-test="systems-popover-trigger"
                    class="group relative inline-flex h-9 items-center gap-2 overflow-hidden rounded-lg border bg-card px-3 text-xs font-medium text-foreground shadow-2xs transition hover:bg-muted/60"
                    :class="systemsPopoverOpen ? 'ring-2 ring-primary/40' : ''"
                    title="Ver sistemas monitorados"
                    @click="systemsPopoverOpen = !systemsPopoverOpen"
                >
                    <Layers
                        class="size-3.5 text-muted-foreground transition group-hover:text-foreground"
                    />
                    <span class="font-medium">Sistemas:</span>
                    <span
                        class="font-semibold text-emerald-600 dark:text-emerald-400"
                    >
                        {{ enabledSystems }}/{{ totalSystems }}
                    </span>
                    <span class="text-[11px] text-muted-foreground"
                        >({{ percentage }}%)</span
                    >
                    <span
                        v-if="editingSystemsCount > 0"
                        data-test="systems-popover-editing-count"
                        class="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-600 dark:text-amber-400"
                    >
                        <span class="size-1.5 rounded-full bg-amber-500" />
                        {{ editingSystemsCount }}
                    </span>

                    <!-- Mini barra de progresso na base do botão -->
                    <span
                        class="absolute bottom-0 left-0 flex h-1 w-full bg-muted/80"
                    >
                        <span
                            class="block h-full bg-emerald-500 transition-all duration-300"
                            :style="{ width: `${percentage}%` }"
                        />
                        <span
                            v-if="editingPercentage > 0"
                            class="block h-full bg-amber-500 transition-all duration-300"
                            :style="{ width: `${editingPercentage}%` }"
                        />
                    </span>
                </button>

                <!-- Popover Dropdown de Sistemas -->
                <div
                    v-if="systemsPopoverOpen"
                    data-test="systems-popover"
                    class="absolute top-full left-0 z-50 mt-2 w-72 rounded-xl border border-border bg-popover p-3 text-popover-foreground shadow-xl ring-1 ring-border/50 sm:w-80"
                >
                    <div
                        class="flex items-start justify-between gap-2 border-b border-border/50 pb-2"
                    >
                        <div class="min-w-0">
                            <h3 class="text-xs font-semibold text-foreground">
                                Sistemas Monitorados
                            </h3>
                            <p class="text-[11px] text-muted-foreground">
                                {{ enabledSystems }} de
                                {{ totalSystems }} habilitados ({{
                                    percentage
                                }}%)
                            </p>
                            <div
                                class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[10px]"
                            >
                                <span
                                    class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-emerald-500"
                                    />
                                    {{ enabledSystems }} habilitado{{
                                        enabledSystems === 1 ? '' : 's'
                                    }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-amber-500"
                                    />
                                    {{ editingSystemsCount }} em edição
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 font-medium text-red-600 dark:text-red-400"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-red-500"
                                    />
                                    {{ disabledSystems }} desabilitado{{
                                        disabledSystems === 1 ? '' : 's'
                                    }}
                                </span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            title="Fechar"
                            @click="systemsPopoverOpen = false"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <div class="mt-2 max-h-60 space-y-1 overflow-y-auto pr-1">
                        <button
                            v-for="system in sectionsWithStatus"
                            :key="system.id"
                            type="button"
                            :data-test="`systems-popover-item-${system.id}`"
                            class="group flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs transition hover:bg-muted/70"
                            @click="scrollToSystem(system.id)"
                        >
                            <span
                                class="truncate font-medium text-foreground group-hover:text-primary"
                            >
                                {{ system.name }}
                            </span>
                            <span
                                :data-test="`systems-popover-status-${system.id}`"
                                :data-status="system.status"
                                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px]"
                                :class="system.statusMeta.badgeClass"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="system.statusMeta.dotClass"
                                />
                                {{ system.statusMeta.label }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Visualizar como cliente -->
            <a
                :href="`/${currentTeam.slug}/relatorios/${report.id}/preview`"
                target="_blank"
                class="inline-flex h-9 items-center gap-2 rounded-lg border border-primary/25 bg-primary/5 px-3 text-xs font-medium text-foreground transition hover:bg-primary/10"
                title="Visualizar relatório como o cliente visualiza em nova aba"
            >
                <ExternalLink class="size-4 text-primary" /> Visualizar como
                cliente
            </a>

            <!-- Destinatários -->
            <button
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-lg border bg-muted/50 px-3 text-xs font-medium text-foreground transition hover:bg-muted"
                @click="emit('openRecipients')"
            >
                <Mail class="size-4 text-muted-foreground" />
                <span>Destinatários</span>
                <span
                    class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                >
                    {{ activeRecipientsCount ?? intendedRecipientsCount ?? 0 }}
                    ativo{{
                        (activeRecipientsCount ??
                            intendedRecipientsCount ??
                            0) === 1
                            ? ''
                            : 's'
                    }}
                </span>
            </button>

            <!-- Finalizar relatório / Reenviar -->
            <button
                v-if="report.status === 'completed'"
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-lg border bg-primary px-4 text-xs font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                @click="emit('sendAgain')"
            >
                <RefreshCw class="size-4" /> Reenviar
            </button>
            <button
                v-else
                type="button"
                data-test="finalize-report"
                class="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-600 px-4 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-500"
                @click="emit('finish')"
            >
                <ShieldCheck class="size-4" /> Finalizar relatório
            </button>
        </div>
    </header>
</template>
