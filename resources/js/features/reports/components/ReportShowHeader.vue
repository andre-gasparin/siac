<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Calendar,
    Check,
    ExternalLink,
    History,
    Layers,
    Mail,
    PanelTop,
    RefreshCw,
    ShieldCheck,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { index as reportsIndex } from '@/routes/reports';
import type { Team } from '@/shared/types';

interface SystemSectionItem {
    id: number;
    name: string;
    item: {
        show_data_results: boolean;
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
    intendedRecipientsCount?: number;
    pendingDate: string;
    datePrompt: {
        open: boolean;
        date: string;
        exists: boolean;
        targetReportId?: number;
    } | null;
}>();

const emit = defineEmits<{
    dateChange: [event: Event];
    cancelDatePrompt: [];
    confirmDatePrompt: [];
    openHistory: [];
    openRecipients: [];
    sendAgain: [];
    finish: [];
}>();

const systemsPopoverOpen = ref(false);
const systemsPopoverRef = ref<HTMLElement | null>(null);

const totalSystems = computed(() => props.sections.length);
const enabledSystems = computed(
    () => props.sections.filter((s) => s.item?.show_data_results).length,
);
const percentage = computed(() =>
    totalSystems.value > 0
        ? Math.round((enabledSystems.value / totalSystems.value) * 100)
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
        class="overflow-visible rounded-3xl border border-border bg-card p-4 text-card-foreground shadow-sm lg:p-6"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl"
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
            <div class="flex flex-wrap items-center gap-2">
                <!-- Seletor de Data com Tooltip de Confirmação -->
                <div class="relative">
                    <div
                        class="flex items-center gap-1.5 rounded-xl border border-border bg-muted/40 px-2.5 py-1.5 text-xs shadow-2xs"
                    >
                        <Calendar class="size-3.5 text-muted-foreground" />
                        <span class="font-medium text-muted-foreground"
                            >Data:</span
                        >
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
                        class="absolute top-full left-0 z-50 mt-2 w-72 rounded-2xl border border-border bg-popover p-3.5 text-popover-foreground shadow-xl ring-1 ring-border/50 sm:w-80"
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

                <!-- Botão de Sistemas Incluídos com Barra de Progresso e Dropdown -->
                <div ref="systemsPopoverRef" class="relative">
                    <button
                        type="button"
                        class="group relative inline-flex h-9 items-center gap-2 overflow-hidden rounded-xl border bg-card px-3 text-xs font-medium text-foreground shadow-2xs transition hover:bg-muted/60"
                        :class="
                            systemsPopoverOpen ? 'ring-2 ring-primary/40' : ''
                        "
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

                        <!-- Mini barra de progresso na base do botão -->
                        <span
                            class="absolute bottom-0 left-0 h-1 w-full bg-muted/80"
                        >
                            <span
                                class="block h-full bg-emerald-500 transition-all duration-300"
                                :style="{ width: `${percentage}%` }"
                            />
                        </span>
                    </button>

                    <!-- Popover Dropdown de Sistemas -->
                    <div
                        v-if="systemsPopoverOpen"
                        class="absolute top-full left-0 z-50 mt-2 w-72 rounded-2xl border border-border bg-popover p-3 text-popover-foreground shadow-xl ring-1 ring-border/50 sm:w-80"
                    >
                        <div
                            class="flex items-center justify-between border-b border-border/50 pb-2"
                        >
                            <div>
                                <h3
                                    class="text-xs font-semibold text-foreground"
                                >
                                    Sistemas Monitorados
                                </h3>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ enabledSystems }} de
                                    {{ totalSystems }} habilitados ({{
                                        percentage
                                    }}%)
                                </p>
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
                            class="mt-2 max-h-60 space-y-1 overflow-y-auto pr-1"
                        >
                            <button
                                v-for="system in sections"
                                :key="system.id"
                                type="button"
                                class="group flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs transition hover:bg-muted/70"
                                @click="scrollToSystem(system.id)"
                            >
                                <span
                                    class="truncate font-medium text-foreground group-hover:text-primary"
                                >
                                    {{ system.name }}
                                </span>
                                <span
                                    v-if="system.item?.show_data_results"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-emerald-500"
                                    />
                                    Habilitado
                                </span>
                                <span
                                    v-else
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-muted-foreground/50"
                                    />
                                    Desabilitado
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <Link
                    :href="reportsIndex.url({ current_team: currentTeam.slug })"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border bg-muted/50 px-3 text-xs font-medium text-foreground transition hover:bg-muted"
                >
                    <PanelTop class="size-4" /> Todos os relatórios
                </Link>
                <a
                    :href="`/${currentTeam.slug}/relatorios/${report.id}/preview`"
                    target="_blank"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-primary/25 bg-primary/5 px-3 text-xs font-medium text-foreground transition hover:bg-primary/10"
                    title="Visualizar relatório como o cliente visualiza em nova aba"
                >
                    <ExternalLink class="size-4 text-primary" /> Visualizar como
                    cliente
                </a>
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-xl border bg-muted/50 text-foreground transition hover:bg-muted"
                    title="Histórico de atividades"
                    aria-label="Histórico de atividades"
                    @click="emit('openHistory')"
                >
                    <History class="size-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border bg-muted/50 px-3 text-xs font-medium text-foreground transition hover:bg-muted"
                    @click="emit('openRecipients')"
                >
                    <Mail class="size-4" /> Destinatários
                </button>
                <button
                    v-if="report.status === 'completed'"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border bg-primary px-4 text-xs font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                    @click="emit('sendAgain')"
                >
                    <RefreshCw class="size-4" /> Reenviar
                </button>
                <button
                    v-else
                    type="button"
                    data-test="finalize-report"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-500"
                    @click="emit('finish')"
                >
                    <ShieldCheck class="size-4" /> Finalizar relatório
                </button>
            </div>
        </div>
    </header>
</template>
