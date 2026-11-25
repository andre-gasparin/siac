<script setup lang="ts">
import {
    Calendar,
    ChevronDown,
    ExternalLink,
    Eye,
    History,
    Layers,
    Loader2,
    Mail,
    RefreshCw,
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

const props = withDefaults(
    defineProps<{
        report: {
            id: number;
            status: string;
            date_reference: string;
        };
        currentTeam: Team;
        sections: SystemSectionItem[];
        editingSystems?: Record<number, boolean>;
        activitiesCount?: number;
        tableStartDate?: string;
        tableEndDate?: string;
        layoutMode: 'sequence' | 'lateral';
        isFinalizing?: boolean;
        isResending?: boolean;
    }>(),
    {
        editingSystems: () => ({}),
        activitiesCount: 0,
        tableStartDate: '',
        tableEndDate: '',
        isFinalizing: false,
        isResending: false,
    },
);

const emit = defineEmits<{
    'update:tableStartDate': [value: string];
    'update:tableEndDate': [value: string];
    tablePeriodChange: [dates: { start_date: string; end_date: string }];
    openHistory: [];
    sendAgain: [];
    finish: [];
    displayAll: [mode: 'editor' | 'both' | 'close'];
    focusSystem: [systemId: number];
}>();

const systemsPopoverOpen = ref(false);
const systemsPopoverRef = ref<HTMLElement | null>(null);
const displayPopoverOpen = ref(false);
const displayPopoverRef = ref<HTMLElement | null>(null);
const customPeriodOpen = ref(false);

const STATUS_META: Record<
    ReportSystemStatus,
    { label: string; badgeClass: string; dotClass: string }
> = {
    enabled: {
        label: 'Concluído',
        badgeClass:
            'bg-emerald-500/15 font-semibold text-emerald-600 dark:text-emerald-400',
        dotClass: 'bg-emerald-500',
    },
    editing: {
        label: 'Em avaliação',
        badgeClass:
            'bg-amber-500/15 font-semibold text-amber-600 dark:text-amber-400',
        dotClass: 'bg-amber-500',
    },
    disabled: {
        label: 'Pendente',
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

const hasAnyEditing = computed(() =>
    Object.values(props.editingSystems ?? {}).some(Boolean),
);

const selectedPresetDays = computed(() => {
    if (!props.tableStartDate || !props.tableEndDate) {
        return 'custom';
    }

    if (props.tableEndDate !== props.report.date_reference) {
        return 'custom';
    }

    const start = new Date(`${props.tableStartDate}T12:00:00`);
    const end = new Date(`${props.tableEndDate}T12:00:00`);
    const diffDays =
        Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) +
        1;

    if ([1, 7, 10, 15, 30, 60].includes(diffDays)) {
        return String(diffDays);
    }

    return 'custom';
});

function handlePresetPeriodChange(event: Event) {
    const val = (event.target as HTMLSelectElement).value;

    if (val === 'custom') {
        customPeriodOpen.value = true;

        return;
    }

    customPeriodOpen.value = false;
    const days = Number(val);
    const endStr = props.report.date_reference;
    const end = new Date(`${endStr}T12:00:00`);
    end.setDate(end.getDate() - (days - 1));
    const startStr = end.toISOString().split('T')[0];

    emit('update:tableStartDate', startStr);
    emit('update:tableEndDate', endStr);
    emit('tablePeriodChange', { start_date: startStr, end_date: endStr });
}

function handleDisplayAction(mode: 'editor' | 'both' | 'close') {
    displayPopoverOpen.value = false;
    emit('displayAll', mode);
}

function handleSelectSystem(systemId: number) {
    systemsPopoverOpen.value = false;
    emit('focusSystem', systemId);
}

function handlePreviewClick(event: MouseEvent) {
    if (
        hasAnyEditing.value &&
        !window.confirm(
            'Há considerações em edição ainda não salvas. A prévia exibirá apenas a última versão salva. Deseja continuar?',
        )
    ) {
        event.preventDefault();
    }
}

function handleClickOutside(event: MouseEvent) {
    const target = event.target as Node;

    if (
        systemsPopoverOpen.value &&
        systemsPopoverRef.value &&
        !systemsPopoverRef.value.contains(target)
    ) {
        systemsPopoverOpen.value = false;
    }

    if (
        displayPopoverOpen.value &&
        displayPopoverRef.value &&
        !displayPopoverRef.value.contains(target)
    ) {
        displayPopoverOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <section
        aria-label="Ferramentas de elaboração"
        class="sticky top-0 z-30 rounded-xl border border-border bg-card/95 text-card-foreground shadow-sm backdrop-blur-sm"
    >
        <div
            class="flex items-center gap-2 border-b border-border/60 px-3.5 py-1.5 text-[10.5px] font-semibold text-muted-foreground"
        >
            <Layers class="size-3.5" />
            <span>Ferramentas de elaboração</span>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
        >
            <!-- Esquerda: Exibição, Período, Andamento, Histórico -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Exibição dos sistemas -->
                <div
                    v-if="layoutMode === 'sequence'"
                    ref="displayPopoverRef"
                    class="relative"
                >
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-[11.5px] font-medium text-foreground transition hover:bg-muted"
                        :aria-expanded="displayPopoverOpen"
                        @click="displayPopoverOpen = !displayPopoverOpen"
                    >
                        <span>Exibição dos sistemas</span>
                        <ChevronDown class="size-3.5 text-muted-foreground" />
                    </button>

                    <div
                        v-if="displayPopoverOpen"
                        class="absolute top-full left-0 z-50 mt-2 w-72 rounded-xl border border-border bg-popover p-1.5 text-popover-foreground shadow-xl ring-1 ring-border/50"
                    >
                        <button
                            type="button"
                            class="flex w-full flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-muted"
                            @click="handleDisplayAction('editor')"
                        >
                            <strong class="text-xs font-semibold">
                                Expandir todos — Somente Editor
                            </strong>
                            <small class="text-[11px] text-muted-foreground">
                                Abre as considerações de todos os sistemas.
                            </small>
                        </button>
                        <button
                            type="button"
                            class="flex w-full flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-muted"
                            @click="handleDisplayAction('both')"
                        >
                            <strong class="text-xs font-semibold">
                                Expandir todos — Editor + Tabela
                            </strong>
                            <small class="text-[11px] text-muted-foreground">
                                Abre os editores e as tabelas históricas.
                            </small>
                        </button>
                        <button
                            type="button"
                            class="flex w-full flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-muted"
                            @click="handleDisplayAction('close')"
                        >
                            <strong class="text-xs font-semibold">
                                Recolher todos os sistemas
                            </strong>
                            <small class="text-[11px] text-muted-foreground">
                                Permanece visível somente o cabeçalho de cada
                                sistema.
                            </small>
                        </button>
                    </div>
                </div>

                <!-- Período das tabelas -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <label
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-[11.5px]"
                    >
                        <Calendar class="size-3.5 text-muted-foreground" />
                        <span
                            class="font-medium whitespace-nowrap text-muted-foreground"
                        >
                            Período das tabelas:
                        </span>
                        <select
                            :value="selectedPresetDays"
                            aria-label="Período histórico comum às tabelas"
                            class="h-6 rounded bg-transparent pr-1 text-[11.5px] font-semibold text-foreground focus:outline-hidden"
                            @change="handlePresetPeriodChange"
                        >
                            <option value="1">Dia do relatório</option>
                            <option value="7">7 dias</option>
                            <option value="10">10 dias</option>
                            <option value="15">15 dias</option>
                            <option value="30">30 dias</option>
                            <option value="60">60 dias</option>
                            <option value="custom">Personalizado</option>
                        </select>
                    </label>

                    <div
                        v-if="
                            customPeriodOpen || selectedPresetDays === 'custom'
                        "
                        class="flex items-center gap-1.5 rounded-lg border border-border bg-muted/30 px-2 py-1 text-xs"
                    >
                        <input
                            :value="tableStartDate"
                            type="date"
                            title="Data inicial das tabelas"
                            class="h-6 rounded border border-input bg-background px-1.5 text-xs font-semibold text-foreground"
                            @change="
                                emit(
                                    'update:tableStartDate',
                                    ($event.target as HTMLInputElement).value,
                                );
                                emit('tablePeriodChange', {
                                    start_date: (
                                        $event.target as HTMLInputElement
                                    ).value,
                                    end_date: tableEndDate ?? '',
                                });
                            "
                        />
                        <span class="text-muted-foreground">até</span>
                        <input
                            :value="tableEndDate"
                            type="date"
                            title="Data final das tabelas"
                            class="h-6 rounded border border-input bg-background px-1.5 text-xs font-semibold text-foreground"
                            @change="
                                emit(
                                    'update:tableEndDate',
                                    ($event.target as HTMLInputElement).value,
                                );
                                emit('tablePeriodChange', {
                                    start_date: tableStartDate ?? '',
                                    end_date: (
                                        $event.target as HTMLInputElement
                                    ).value,
                                });
                            "
                        />
                    </div>
                </div>

                <!-- Popover Andamento da avaliação -->
                <div ref="systemsPopoverRef" class="relative">
                    <button
                        type="button"
                        data-test="systems-popover-trigger"
                        class="group relative inline-flex h-8 items-center gap-1.5 overflow-hidden rounded-lg border border-border bg-background px-2.5 text-[11.5px] font-medium text-foreground transition hover:bg-muted/60"
                        :class="
                            systemsPopoverOpen ? 'ring-2 ring-primary/40' : ''
                        "
                        title="Ver andamento da avaliação dos sistemas"
                        @click="systemsPopoverOpen = !systemsPopoverOpen"
                    >
                        <Layers
                            class="size-3.5 text-muted-foreground transition group-hover:text-foreground"
                        />
                        <span class="font-medium">Andamento:</span>
                        <span
                            class="font-semibold text-emerald-600 dark:text-emerald-400"
                        >
                            {{ enabledSystems }}/{{ totalSystems }}
                        </span>
                        <span class="text-[11px] text-muted-foreground">
                            ({{ percentage }}%)
                        </span>
                        <span
                            v-if="editingSystemsCount > 0"
                            data-test="systems-popover-editing-count"
                            class="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-600 dark:text-amber-400"
                        >
                            <span class="size-1.5 rounded-full bg-amber-500" />
                            {{ editingSystemsCount }}
                        </span>

                        <!-- Barra de progresso na base do botão -->
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

                    <div
                        v-if="systemsPopoverOpen"
                        data-test="systems-popover"
                        class="absolute top-full left-0 z-50 mt-2 w-80 rounded-xl border border-border bg-popover p-3 text-popover-foreground shadow-xl ring-1 ring-border/50 sm:w-96"
                    >
                        <div
                            class="flex items-start justify-between gap-2 border-b border-border/50 pb-2"
                        >
                            <div class="min-w-0">
                                <h3
                                    class="text-xs font-semibold text-foreground"
                                >
                                    Andamento da avaliação técnica
                                </h3>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ enabledSystems }} de
                                    {{ totalSystems }} concluídos ({{
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
                                        {{ enabledSystems }} concluído{{
                                            enabledSystems === 1 ? '' : 's'
                                        }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400"
                                    >
                                        <span
                                            class="size-1.5 rounded-full bg-amber-500"
                                        />
                                        {{ editingSystemsCount }} em avaliação
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 font-medium text-red-600 dark:text-red-400"
                                    >
                                        <span
                                            class="size-1.5 rounded-full bg-red-500"
                                        />
                                        {{ disabledSystems }} pendente{{
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

                        <div
                            class="mt-2 max-h-64 space-y-1 overflow-y-auto pr-1"
                        >
                            <button
                                v-for="system in sectionsWithStatus"
                                :key="system.id"
                                type="button"
                                :data-test="`systems-popover-item-${system.id}`"
                                class="group flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs transition hover:bg-muted/70"
                                @click="handleSelectSystem(system.id)"
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

                <!-- Histórico de ações -->
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-[11.5px] font-medium text-foreground transition hover:bg-muted"
                    title="Histórico de ações da elaboração"
                    @click="emit('openHistory')"
                >
                    <History class="size-3.5 text-muted-foreground" />
                    <span>Histórico de ações</span>
                    <span
                        v-if="activitiesCount > 0"
                        class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-foreground tabular-nums"
                    >
                        {{ activitiesCount }}
                    </span>
                </button>
            </div>

            <!-- Direita: Prévia e Emissão -->
            <div class="flex flex-wrap items-center gap-2">
                <a
                    :href="`/${currentTeam.slug}/relatorios/${report.id}/preview`"
                    target="_blank"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-[11.5px] font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90"
                    title="Visualizar relatório consolidado em nova aba"
                    @click="handlePreviewClick"
                >
                    <Eye class="size-3.5" />
                    <span>Visualizar relatório consolidado</span>
                    <ExternalLink class="size-3 opacity-80" />
                </a>

                <button
                    v-if="report.status === 'completed'"
                    type="button"
                    data-test="resend-report"
                    :disabled="isResending"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#89653e] bg-[#89653e] px-3 text-[11.5px] font-semibold text-white shadow-xs transition hover:border-[#75532f] hover:bg-[#75532f] disabled:cursor-wait disabled:opacity-70 dark:border-[#a57a49] dark:bg-[#a57a49] dark:text-[#141b20] dark:hover:bg-[#b88c58]"
                    @click="emit('sendAgain')"
                >
                    <Loader2
                        v-if="isResending"
                        data-test="resend-loading"
                        class="size-3.5 animate-spin"
                    />
                    <RefreshCw v-else class="size-3.5" />
                    <span>{{
                        isResending ? 'Reenviando...' : 'Reenviar'
                    }}</span>
                </button>
                <button
                    v-else
                    type="button"
                    data-test="finalize-report"
                    :disabled="isFinalizing"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#89653e] bg-[#89653e] px-3 text-[11.5px] font-semibold text-white shadow-xs transition hover:border-[#75532f] hover:bg-[#75532f] disabled:cursor-wait disabled:opacity-70 dark:border-[#a57a49] dark:bg-[#a57a49] dark:text-[#141b20] dark:hover:bg-[#b88c58]"
                    @click="emit('finish')"
                >
                    <Loader2
                        v-if="isFinalizing"
                        data-test="finalize-loading"
                        class="size-3.5 animate-spin"
                    />
                    <Mail v-else class="size-3.5" />
                    <span>{{
                        isFinalizing ? 'Finalizando...' : 'Finalizar relatório'
                    }}</span>
                </button>
            </div>
        </div>
    </section>
</template>
