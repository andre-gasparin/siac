<script setup lang="ts">
import { PanelLeftClose, Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    hasSavedReportComment,
    resolveReportSystemStatus,
} from '@/features/reports/systemStatus';
import type { ReportSystemStatus } from '@/features/reports/systemStatus';

interface NavSystemItem {
    id: number;
    name: string;
    item: {
        show_data_results: boolean;
        comment?: string | null;
        parent_monitored_system_id?: number | null;
    } | null;
}

const props = defineProps<{
    open: boolean;
    mode: 'sequence' | 'lateral';
    sections: NavSystemItem[];
    allSystems: NavSystemItem[];
    editingSystems: Record<number, boolean>;
    activeSystemId: number | null;
    dataCountsBySystem?: Record<number, number>;
}>();

const emit = defineEmits<{
    select: [systemId: number];
    close: [];
}>();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'pending' | 'editing' | 'enabled'>('all');

const STATUS_LABELS: Record<ReportSystemStatus, string> = {
    disabled: 'Pendente',
    editing: 'Em avaliação',
    enabled: 'Concluído',
};

function getSystemStatus(system: NavSystemItem): ReportSystemStatus {
    return resolveReportSystemStatus(
        system.item,
        Boolean(props.editingSystems[system.id]),
    );
}

function getChildrenFor(systemId: number): NavSystemItem[] {
    return props.allSystems.filter(
        (s) => s.item?.parent_monitored_system_id === systemId,
    );
}

function getBlockStatus(system: NavSystemItem): ReportSystemStatus {
    return getSystemStatus(system);
}

function getBorderClass(status: ReportSystemStatus): string {
    if (status === 'enabled') {
        return 'border-l-emerald-500';
    }

    if (status === 'editing') {
        return 'border-l-amber-500';
    }

    return 'border-l-red-500';
}

function getDotClass(status: ReportSystemStatus): string {
    if (status === 'enabled') {
        return 'bg-emerald-500';
    }

    if (status === 'editing') {
        return 'bg-amber-500';
    }

    return 'bg-red-500';
}

const navEntries = computed(() => {
    const query = searchQuery.value
        .trim()
        .toLocaleLowerCase('pt-BR')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    return props.sections
        .map((system) => {
            const children = getChildrenFor(system.id);
            const members = [system, ...children];
            const status = getBlockStatus(system);
            const totalData = members.reduce(
                (sum, m) => sum + (props.dataCountsBySystem?.[m.id] ?? 0),
                0,
            );

            return {
                id: system.id,
                system,
                members,
                isGrouped: children.length > 0,
                status,
                totalData,
            };
        })
        .filter((entry) => {
            if (
                statusFilter.value === 'pending' &&
                entry.status !== 'disabled'
            ) {
                return false;
            }

            if (
                statusFilter.value === 'editing' &&
                entry.status !== 'editing'
            ) {
                return false;
            }

            if (
                statusFilter.value === 'enabled' &&
                entry.status !== 'enabled'
            ) {
                return false;
            }

            if (!query) {
                return true;
            }

            return entry.members.some((member) =>
                member.name
                    .toLocaleLowerCase('pt-BR')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .includes(query),
            );
        });
});

const totalVisibleSystems = computed(() =>
    navEntries.value.reduce((acc, entry) => acc + entry.members.length, 0),
);
</script>

<template>
    <div class="contents">
        <!-- Mobile/tablet backdrop when drawer open -->
        <button
            v-if="open"
            type="button"
            aria-label="Fechar painel de navegação"
            class="fixed inset-0 z-40 bg-black/50 backdrop-blur-[1px] lg:hidden"
            @click="emit('close')"
        />

        <aside
            v-if="open"
            aria-label="Navegação pelos sistemas"
            class="fixed inset-y-3 left-3 z-50 flex w-[min(305px,calc(100vw-24px))] flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-2xl lg:sticky lg:top-24 lg:z-10 lg:max-h-[calc(100vh-115px)] lg:w-full lg:shadow-sm"
        >
            <div class="flex items-start justify-between gap-2 px-3 pt-3 pb-2">
                <div class="min-w-0">
                    <strong class="block text-xs font-medium text-foreground">
                        {{
                            mode === 'lateral'
                                ? 'Sistemas do relatório'
                                : 'Navegação dos sistemas'
                        }}
                    </strong>
                    <small
                        class="mt-0.5 block text-[10px] text-muted-foreground"
                    >
                        Selecione para localizar e abrir a avaliação
                    </small>
                </div>
                <button
                    v-if="mode === 'sequence'"
                    type="button"
                    aria-label="Minimizar navegação"
                    title="Minimizar navegação"
                    class="hidden shrink-0 rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground lg:inline-flex"
                    @click="emit('close')"
                >
                    <PanelLeftClose class="size-4" />
                </button>
                <button
                    type="button"
                    aria-label="Fechar navegação"
                    title="Fechar navegação"
                    class="rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground lg:hidden"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="px-3 pb-2">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Buscar sistema..."
                        aria-label="Buscar sistema pelo nome"
                        class="h-7.5 w-full rounded-lg border border-input bg-background pr-2.5 pl-8 text-[11px] text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-hidden"
                    />
                </div>

                <div class="mt-2 flex flex-wrap gap-0.5">
                    <button
                        v-for="tab in [
                            { key: 'all', label: 'Todos' },
                            { key: 'pending', label: 'Pendentes' },
                            { key: 'editing', label: 'Em avaliação' },
                            { key: 'enabled', label: 'Concluídos' },
                        ] as const"
                        :key="tab.key"
                        type="button"
                        class="rounded-md px-1.5 py-0.5 text-[10px] font-medium transition"
                        :class="
                            statusFilter === tab.key
                                ? 'bg-[var(--report-soft-blue)] text-primary'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="statusFilter = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <nav
                aria-label="Sistemas do relatório"
                class="flex flex-1 flex-col gap-1 overflow-y-auto border-t border-border/60 px-2 py-2"
            >
                <button
                    v-for="entry in navEntries"
                    :key="entry.id"
                    type="button"
                    :data-nav-system-id="entry.id"
                    :aria-current="
                        activeSystemId === entry.id ? 'location' : undefined
                    "
                    class="w-full rounded-lg border border-l-[3px] px-2.5 py-1.5 text-left transition"
                    :class="[
                        getBorderClass(entry.status),
                        activeSystemId === entry.id
                            ? 'border-[var(--report-soft-blue-border)] bg-[var(--report-soft-blue)] shadow-2xs'
                            : 'border-transparent hover:border-border hover:bg-muted/70',
                    ]"
                    @click="emit('select', entry.id)"
                >
                    <span
                        v-if="entry.isGrouped"
                        class="mb-1 block text-[9px] font-medium tracking-wider text-muted-foreground uppercase"
                    >
                        ↗ Sistemas agrupados · {{ entry.members.length }}
                    </span>

                    <span
                        v-for="member in entry.members"
                        :key="member.id"
                        class="flex items-start gap-2 text-[11px] leading-snug"
                        :class="[
                            entry.members.length > 1 ? 'mb-0.5 last:mb-0' : '',
                            activeSystemId === entry.id
                                ? 'font-medium text-foreground'
                                : 'font-normal text-foreground/90',
                        ]"
                    >
                        <span
                            class="mt-1 size-2 shrink-0 rounded-full"
                            :class="
                                getDotClass(
                                    getSystemStatus(member) === 'disabled' &&
                                        entry.isGrouped &&
                                        hasSavedReportComment(entry.system.item)
                                        ? 'enabled'
                                        : getSystemStatus(member),
                                )
                            "
                        />
                        <span class="min-w-0 flex-1">{{ member.name }}</span>
                    </span>

                    <span
                        class="mt-0.5 flex items-center justify-between gap-2 pl-4 text-[9.5px] text-muted-foreground"
                    >
                        <span>
                            {{ STATUS_LABELS[entry.status] }}
                            <template v-if="editingSystems[entry.id]">
                                · Em edição
                            </template>
                        </span>
                        <span v-if="dataCountsBySystem">
                            {{
                                entry.totalData === 0
                                    ? 'Sem dados'
                                    : `${entry.totalData} dados`
                            }}
                        </span>
                    </span>
                </button>

                <p
                    v-if="navEntries.length === 0"
                    class="px-2 py-6 text-center text-xs text-muted-foreground"
                >
                    Nenhum sistema encontrado.
                </p>
            </nav>

            <div
                class="border-t border-border/70 bg-muted/30 px-3 py-2 text-[10px] text-muted-foreground"
            >
                <div class="font-medium text-foreground">
                    {{ totalVisibleSystems }} sistema(s) ·
                    {{ navEntries.length }} item(ns)
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1">
                    <span class="inline-flex items-center gap-1">
                        <i class="size-1.5 rounded-full bg-red-500" /> Pendente
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <i class="size-1.5 rounded-full bg-amber-500" /> Em
                        avaliação
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <i class="size-1.5 rounded-full bg-emerald-500" />
                        Concluído
                    </span>
                </div>
            </div>
        </aside>
    </div>
</template>
