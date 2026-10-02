<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Clock,
    History,
    Layers,
    Loader2,
    MessageSquare,
    RotateCcw,
    Trash2,
    User as UserIcon,
    X,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { history as dataEntryHistory } from '@/routes/data-entry';
import {
    destroy as dataEntryHistoryDestroy,
    destroyGroup as dataEntryHistoryDestroyGroup,
    showGroup as dataEntryHistoryShowGroup,
} from '@/routes/data-entry/history';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/shared/components/ui/sheet';
import type { Team } from '@/shared/types';

interface SystemItem {
    id: number;
    team_id: number;
    name: string;
    sort_order: number;
}

interface ParameterDetail {
    parameter_id: number;
    name: string;
    code?: string | null;
    unit?: string | null;
    decimals: number;
    value: number | null;
    alert_1_min: number | null;
    alert_1_max: number | null;
}

interface EnvioSystemItem {
    id: number;
    monitored_system_id: number;
    system_name: string;
    user_name?: string;
    user_id?: number | null;
    responsible?: string;
    status: 'completed' | 'reverted';
    collected_at?: string;
    created_at?: string | null;
    reverted_at: string | null;
    reverted_by_name: string | null;
    saved_values_count: number;
    comment: string | null;
    can_revert: boolean;
    parameters_data?: ParameterDetail[];
}

interface EnvioGroupItem {
    batch_group_uuid: string;
    created_at: string | null;
    collected_at: string | null;
    responsible: string;
    user_name: string;
    user_id: number | null;
    status: 'completed' | 'reverted' | 'partial';
    systems_count: number;
    systems_names: string[];
    systems: EnvioSystemItem[];
    total_values_count: number;
    reverted_at: string | null;
    reverted_by_name: string | null;
    can_revert: boolean;
}

const props = defineProps<{
    open: boolean;
    currentTeam: Team;
    systems: SystemItem[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const isLoading = ref(false);
const envios = ref<EnvioGroupItem[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);

const filterSystemId = ref<string>('all');
const filterStatus = ref<'all' | 'completed' | 'reverted'>('all');

const expandedGroupUuids = ref<string[]>([]);
const expandedSystemIds = ref<number[]>([]);
const groupDetailsCache = ref<Record<string, EnvioSystemItem[]>>({});
const loadingGroupDetails = ref<Record<string, boolean>>({});

const confirmingDeleteGroupUuid = ref<string | null>(null);
const confirmingDeleteSystemId = ref<number | null>(null);
const isDeleting = ref(false);

async function fetchHistory(page = 1) {
    isLoading.value = true;
    confirmingDeleteGroupUuid.value = null;
    confirmingDeleteSystemId.value = null;

    try {
        const query: Record<string, string | number> = {
            page,
        };

        if (filterSystemId.value !== 'all') {
            query.system_id = filterSystemId.value;
        }

        if (filterStatus.value !== 'all') {
            query.status = filterStatus.value;
        }

        const response = await fetch(
            dataEntryHistory.url(
                { current_team: props.currentTeam.slug },
                { query },
            ),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Erro ao carregar histórico.');
        }

        const data = await response.json();
        envios.value = data.data || [];
        currentPage.value = data.current_page || 1;
        lastPage.value = data.last_page || 1;
        total.value = data.total || 0;
    } catch (err: unknown) {
        console.error('Erro ao buscar histórico:', err);
        toast.error('Não foi possível carregar o histórico de envios.');
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            fetchHistory(1);
        }
    },
);

async function toggleExpandGroup(groupUuid: string) {
    if (expandedGroupUuids.value.includes(groupUuid)) {
        expandedGroupUuids.value = expandedGroupUuids.value.filter(
            (u) => u !== groupUuid,
        );

        return;
    }

    expandedGroupUuids.value.push(groupUuid);

    // Background fetch of full details if not already cached
    if (!groupDetailsCache.value[groupUuid]) {
        loadingGroupDetails.value[groupUuid] = true;

        try {
            const url = dataEntryHistoryShowGroup.url({
                current_team: props.currentTeam.slug,
                batch_group_uuid: groupUuid,
            });
            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (res.ok) {
                const data = await res.json();
                groupDetailsCache.value[groupUuid] = data.systems || [];
            }
        } catch (e) {
            console.error('Erro ao buscar detalhes do envio em background:', e);
        } finally {
            loadingGroupDetails.value[groupUuid] = false;
        }
    }
}

function isGroupExpanded(groupUuid: string): boolean {
    return expandedGroupUuids.value.includes(groupUuid);
}

function toggleExpandSystem(batchId: number) {
    if (expandedSystemIds.value.includes(batchId)) {
        expandedSystemIds.value = expandedSystemIds.value.filter(
            (id) => id !== batchId,
        );
    } else {
        expandedSystemIds.value.push(batchId);
    }
}

function isSystemExpanded(batchId: number): boolean {
    return expandedSystemIds.value.includes(batchId);
}

function getSystemsForGroup(envio: EnvioGroupItem): EnvioSystemItem[] {
    return groupDetailsCache.value[envio.batch_group_uuid] || envio.systems;
}

function formatValue(val: number | null | undefined, decimals: number): string {
    if (val === null || val === undefined || isNaN(val)) {
        return '-';
    }

    return val.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals ?? 2,
        maximumFractionDigits: decimals ?? 2,
    });
}

function isParamOutOfLimits(param: ParameterDetail): boolean {
    if (param.value === null || param.value === undefined) {
        return false;
    }

    if (param.alert_1_min !== null && param.value < param.alert_1_min) {
        return true;
    }

    if (param.alert_1_max !== null && param.value > param.alert_1_max) {
        return true;
    }

    return false;
}

async function revertEntireGroup(groupUuid: string) {
    isDeleting.value = true;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const url = dataEntryHistoryDestroyGroup.url({
            current_team: props.currentTeam.slug,
            batch_group_uuid: groupUuid,
        });

        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMsg = data.message || 'Falha ao reverter o envio.';
            toast.error(errorMsg);

            return;
        }

        toast.success(
            data.message ||
                'Envio cancelado e medições revertidas com sucesso!',
        );
        confirmingDeleteGroupUuid.value = null;
        delete groupDetailsCache.value[groupUuid];
        await fetchHistory(currentPage.value);
    } catch (err) {
        console.error('Erro ao reverter envio completo:', err);
        toast.error('Erro na comunicação com o servidor.');
    } finally {
        isDeleting.value = false;
    }
}

async function revertSingleSystem(batchId: number, groupUuid: string) {
    isDeleting.value = true;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const url = dataEntryHistoryDestroy.url({
            current_team: props.currentTeam.slug,
            batch: batchId,
        });

        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMsg = data.message || 'Falha ao reverter o sistema.';
            toast.error(errorMsg);

            return;
        }

        toast.success(
            data.message || 'Medições do sistema revertidas com sucesso!',
        );
        confirmingDeleteSystemId.value = null;
        delete groupDetailsCache.value[groupUuid];
        await fetchHistory(currentPage.value);
    } catch (err) {
        console.error('Erro ao reverter sistema individual:', err);
        toast.error('Erro na comunicação com o servidor.');
    } finally {
        isDeleting.value = false;
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="(val) => emit('update:open', val)">
        <SheetContent
            side="right"
            class="flex h-full w-full flex-col border-l border-border/80 bg-background p-0 shadow-2xl sm:max-w-xl md:max-w-2xl"
        >
            <!-- Header -->
            <SheetHeader
                class="flex flex-row items-center justify-between border-b border-border/70 p-5"
            >
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary">
                        <History class="h-5 w-5" />
                    </div>
                    <div>
                        <SheetTitle class="text-lg font-bold text-foreground">
                            Histórico de Envios
                        </SheetTitle>
                        <SheetDescription class="text-xs text-muted-foreground">
                            Auditoria agrupada por envio, com expansão em
                            cascata e reversão
                        </SheetDescription>
                    </div>
                </div>
            </SheetHeader>

            <!-- Filtros -->
            <div
                class="flex flex-wrap items-center gap-3 border-b border-border/60 bg-muted/20 p-4"
            >
                <!-- Filtro por Sistema -->
                <div class="flex min-w-[160px] flex-1 flex-col gap-1">
                    <label class="text-xs font-semibold text-muted-foreground">
                        Sistema
                    </label>
                    <select
                        v-model="filterSystemId"
                        @change="fetchHistory(1)"
                        class="h-9 rounded-lg border border-border/80 bg-background px-3 text-xs text-foreground outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="all">Todos os sistemas</option>
                        <option
                            v-for="system in systems"
                            :key="system.id"
                            :value="String(system.id)"
                        >
                            {{ system.name }}
                        </option>
                    </select>
                </div>

                <!-- Filtro por Status -->
                <div class="flex w-36 flex-col gap-1">
                    <label class="text-xs font-semibold text-muted-foreground">
                        Status
                    </label>
                    <select
                        v-model="filterStatus"
                        @change="fetchHistory(1)"
                        class="h-9 rounded-lg border border-border/80 bg-background px-3 text-xs text-foreground outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="all">Todos</option>
                        <option value="completed">Ativos</option>
                        <option value="reverted">Cancelados</option>
                    </select>
                </div>

                <!-- Botão Atualizar -->
                <div class="flex items-end pt-5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="isLoading"
                        @click="fetchHistory(currentPage)"
                        class="h-9 cursor-pointer gap-1.5 rounded-lg px-3 text-xs"
                    >
                        <RotateCcw
                            class="h-3.5 w-3.5"
                            :class="{ 'animate-spin': isLoading }"
                        />
                        <span>Atualizar</span>
                    </Button>
                </div>
            </div>

            <!-- Conteúdo da Lista de Envios -->
            <div class="flex-1 space-y-3 overflow-y-auto p-4">
                <!-- Loading State Inicial -->
                <div
                    v-if="isLoading && envios.length === 0"
                    class="flex flex-col items-center justify-center py-16 text-muted-foreground"
                >
                    <Loader2 class="mb-2 h-8 w-8 animate-spin text-primary" />
                    <span class="text-sm font-medium"
                        >Carregando histórico...</span
                    >
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="envios.length === 0"
                    class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 bg-muted/10 px-4 py-16 text-center text-muted-foreground"
                >
                    <History class="mb-3 h-10 w-10 stroke-1 opacity-40" />
                    <h3 class="mb-1 text-sm font-bold text-foreground">
                        Nenhum envio encontrado
                    </h3>
                    <p class="max-w-xs text-xs leading-relaxed">
                        Não foram encontrados registros de envios para os
                        filtros selecionados.
                    </p>
                </div>

                <!-- NÍVEL 1: Cartões de Envio Agrupados -->
                <div
                    v-for="envio in envios"
                    :key="envio.batch_group_uuid"
                    class="overflow-hidden rounded-xl border transition-all"
                    :class="[
                        envio.status === 'reverted'
                            ? 'border-border/60 bg-muted/10 opacity-75'
                            : 'border-border/80 bg-card shadow-xs hover:border-primary/40',
                    ]"
                >
                    <!-- Cabeçalho do Cartão de Envio (Nível 1) -->
                    <div class="flex flex-col gap-3 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <div
                                    class="rounded-lg bg-primary/10 p-1.5 text-primary"
                                >
                                    <Layers class="h-4 w-4" />
                                </div>
                                <span
                                    class="truncate text-sm font-bold text-foreground"
                                >
                                    Envio com
                                    {{ envio.systems_count }} sistema(s)
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <!-- Status Badge do Envio -->
                                <Badge
                                    v-if="envio.status === 'completed'"
                                    variant="outline"
                                    class="flex items-center gap-1 border-emerald-500/20 bg-emerald-500/10 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Ativo
                                </Badge>
                                <Badge
                                    v-else-if="envio.status === 'reverted'"
                                    variant="outline"
                                    class="flex items-center gap-1 border-red-500/20 bg-red-500/10 py-0.5 text-[11px] font-semibold text-red-600 dark:text-red-400"
                                >
                                    <X class="h-3 w-3" />
                                    Cancelado
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="flex items-center gap-1 border-amber-500/20 bg-amber-500/10 py-0.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400"
                                >
                                    Parcial
                                </Badge>
                            </div>
                        </div>

                        <!-- Metadados do Envio (Responsável, Coleta, Horário de Envio) -->
                        <div
                            class="grid grid-cols-1 gap-x-4 gap-y-1.5 text-xs text-muted-foreground sm:grid-cols-2"
                        >
                            <div class="flex items-center gap-1.5">
                                <Clock
                                    class="h-3.5 w-3.5 shrink-0 opacity-70"
                                />
                                <span>
                                    Coleta:
                                    <strong class="text-foreground/90">{{
                                        envio.collected_at
                                    }}</strong>
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <UserIcon
                                    class="h-3.5 w-3.5 shrink-0 opacity-70"
                                />
                                <span class="truncate">
                                    Responsável:
                                    <strong class="text-foreground/90">{{
                                        envio.responsible
                                    }}</strong>
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <History
                                    class="h-3.5 w-3.5 shrink-0 opacity-70"
                                />
                                <span>
                                    Enviado em:
                                    <strong class="text-foreground/90">{{
                                        envio.created_at
                                    }}</strong>
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <span class="truncate">
                                    Usuário:
                                    <strong class="text-foreground/90">{{
                                        envio.user_name
                                    }}</strong>
                                </span>
                            </div>
                        </div>

                        <!-- Nomes resumidos dos sistemas incluídos -->
                        <div
                            class="flex flex-wrap items-center gap-1 text-[11px] text-muted-foreground"
                        >
                            <span class="font-semibold text-foreground/80"
                                >Sistemas:</span
                            >
                            <span
                                v-for="(name, idx) in envio.systems_names"
                                :key="name"
                                class="rounded bg-muted/60 px-1.5 py-0.5 font-medium"
                            >
                                {{ name
                                }}<span
                                    v-if="idx < envio.systems_names.length - 1"
                                    >,</span
                                >
                            </span>
                        </div>

                        <!-- Aviso se revertido -->
                        <div
                            v-if="envio.status === 'reverted'"
                            class="flex items-center gap-2 rounded-lg border border-red-200/60 bg-red-50/50 p-2 text-[11px] text-red-600 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-400"
                        >
                            <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                            <span>
                                Envio cancelado por
                                <strong>{{
                                    envio.reverted_by_name || 'Usuário'
                                }}</strong>
                                em
                                {{ envio.reverted_at || 'data desconhecida' }}.
                            </span>
                        </div>

                        <!-- Barra de Ações do Envio (Expandir e Reverter Envio Completo) -->
                        <div
                            class="mt-1 flex items-center justify-between border-t border-border/40 pt-2.5"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="
                                    toggleExpandGroup(envio.batch_group_uuid)
                                "
                                class="h-7 cursor-pointer gap-1 px-2 text-xs font-semibold text-primary hover:bg-primary/10"
                            >
                                <span>
                                    {{
                                        isGroupExpanded(envio.batch_group_uuid)
                                            ? 'Recolher Sistemas'
                                            : 'Ver Sistemas Enviados'
                                    }}
                                </span>
                                <ChevronUp
                                    v-if="
                                        isGroupExpanded(envio.batch_group_uuid)
                                    "
                                    class="h-3.5 w-3.5"
                                />
                                <ChevronDown v-else class="h-3.5 w-3.5" />
                            </Button>

                            <!-- Botão Reverter Envio Completo -->
                            <div
                                v-if="
                                    envio.status !== 'reverted' &&
                                    envio.can_revert
                                "
                            >
                                <div
                                    v-if="
                                        confirmingDeleteGroupUuid ===
                                        envio.batch_group_uuid
                                    "
                                    class="flex items-center gap-1.5"
                                >
                                    <span
                                        class="text-[11px] font-semibold text-destructive"
                                    >
                                        Reverter envio completo?
                                    </span>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        :disabled="isDeleting"
                                        @click="
                                            revertEntireGroup(
                                                envio.batch_group_uuid,
                                            )
                                        "
                                        class="h-6 cursor-pointer px-2 text-[10px]"
                                    >
                                        <Loader2
                                            v-if="isDeleting"
                                            class="h-3 w-3 animate-spin"
                                        />
                                        <span v-else>Sim, cancelar</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="isDeleting"
                                        @click="
                                            confirmingDeleteGroupUuid = null
                                        "
                                        class="h-6 cursor-pointer px-1.5 text-[10px]"
                                    >
                                        Não
                                    </Button>
                                </div>
                                <Button
                                    v-else
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="
                                        confirmingDeleteGroupUuid =
                                            envio.batch_group_uuid
                                    "
                                    class="h-7 cursor-pointer gap-1 px-2 text-xs text-muted-foreground hover:bg-red-50 hover:text-destructive dark:hover:bg-red-950/20"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    <span>Cancelar Envio</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- NÍVEL 2: Sistemas do Envio Expandido (busca em background) -->
                    <div
                        v-if="isGroupExpanded(envio.batch_group_uuid)"
                        class="space-y-2.5 border-t border-border/60 bg-muted/20 p-3"
                    >
                        <div
                            v-if="loadingGroupDetails[envio.batch_group_uuid]"
                            class="flex items-center justify-center py-4 text-xs text-muted-foreground"
                        >
                            <Loader2
                                class="mr-2 h-4 w-4 animate-spin text-primary"
                            />
                            <span>Carregando sistemas e medições...</span>
                        </div>

                        <div
                            v-else
                            v-for="systemItem in getSystemsForGroup(envio)"
                            :key="systemItem.id"
                            class="rounded-lg border border-border/70 bg-card p-3 shadow-2xs"
                        >
                            <!-- Linha do Sistema (Nível 2) -->
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <button
                                    type="button"
                                    @click="toggleExpandSystem(systemItem.id)"
                                    class="flex flex-1 cursor-pointer items-center gap-2 text-left hover:opacity-80"
                                >
                                    <ChevronRight
                                        class="h-4 w-4 text-primary transition-transform duration-200"
                                        :class="{
                                            'rotate-90': isSystemExpanded(
                                                systemItem.id,
                                            ),
                                        }"
                                    />
                                    <span
                                        class="text-xs font-bold"
                                        :class="[
                                            systemItem.status === 'reverted'
                                                ? 'text-muted-foreground line-through'
                                                : 'text-foreground',
                                        ]"
                                    >
                                        {{ systemItem.system_name }}
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        class="py-0 text-[10px]"
                                    >
                                        {{ systemItem.saved_values_count }}
                                        parâmetro(s)
                                    </Badge>
                                </button>

                                <div class="flex items-center gap-2">
                                    <Badge
                                        v-if="systemItem.status === 'completed'"
                                        variant="outline"
                                        class="border-emerald-500/20 bg-emerald-500/10 py-0 text-[10px] text-emerald-600 dark:text-emerald-400"
                                    >
                                        Ativo
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="border-red-500/20 bg-red-500/10 py-0 text-[10px] text-red-600 dark:text-red-400"
                                    >
                                        Cancelado
                                    </Badge>

                                    <!-- Reversão individual deste sistema -->
                                    <div
                                        v-if="
                                            systemItem.status === 'completed' &&
                                            systemItem.can_revert
                                        "
                                    >
                                        <div
                                            v-if="
                                                confirmingDeleteSystemId ===
                                                systemItem.id
                                            "
                                            class="flex items-center gap-1"
                                        >
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                :disabled="isDeleting"
                                                @click="
                                                    revertSingleSystem(
                                                        systemItem.id,
                                                        envio.batch_group_uuid,
                                                    )
                                                "
                                                class="h-6 cursor-pointer px-2 text-[10px]"
                                            >
                                                Sim
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                :disabled="isDeleting"
                                                @click="
                                                    confirmingDeleteSystemId =
                                                        null
                                                "
                                                class="h-6 cursor-pointer px-1 text-[10px]"
                                            >
                                                Não
                                            </Button>
                                        </div>
                                        <Button
                                            v-else
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                confirmingDeleteSystemId =
                                                    systemItem.id
                                            "
                                            title="Cancelar apenas este sistema"
                                            class="h-6 w-6 cursor-pointer p-0 text-muted-foreground hover:text-destructive"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <!-- NÍVEL 3: Tabela de Valores dos Parâmetros do Sistema -->
                            <div
                                v-if="isSystemExpanded(systemItem.id)"
                                class="mt-3 border-t border-border/40 pt-2"
                            >
                                <div
                                    v-if="
                                        systemItem.parameters_data &&
                                        systemItem.parameters_data.length > 0
                                    "
                                    class="overflow-x-auto"
                                >
                                    <table
                                        class="w-full border-collapse text-left text-xs"
                                    >
                                        <thead>
                                            <tr
                                                class="border-b border-border/50 text-[10px] font-bold text-muted-foreground"
                                            >
                                                <th class="py-1 pr-2">
                                                    Parâmetro
                                                </th>
                                                <th
                                                    class="py-1 pr-2 text-right"
                                                >
                                                    Valor
                                                </th>
                                                <th
                                                    class="px-2 py-1 text-center"
                                                >
                                                    Unidade
                                                </th>
                                                <th
                                                    class="px-2 py-1 text-center"
                                                >
                                                    Faixa
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-border/20"
                                        >
                                            <tr
                                                v-for="p in systemItem.parameters_data"
                                                :key="p.parameter_id"
                                                class="hover:bg-muted/30"
                                            >
                                                <td
                                                    class="py-1.5 pr-2 font-medium text-foreground"
                                                >
                                                    {{ p.name }}
                                                </td>
                                                <td
                                                    class="py-1.5 pr-2 text-right font-mono font-bold"
                                                    :class="[
                                                        isParamOutOfLimits(p)
                                                            ? 'text-red-600 dark:text-red-400'
                                                            : 'text-foreground',
                                                    ]"
                                                >
                                                    <span
                                                        v-if="p.value !== null"
                                                    >
                                                        {{
                                                            formatValue(
                                                                p.value,
                                                                p.decimals,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="font-normal text-amber-500 italic"
                                                    >
                                                        Apagado
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-2 py-1.5 text-center text-muted-foreground"
                                                >
                                                    {{ p.unit || '-' }}
                                                </td>
                                                <td
                                                    class="px-2 py-1.5 text-center font-mono text-[10px] text-muted-foreground"
                                                >
                                                    <span
                                                        v-if="
                                                            p.alert_1_min !==
                                                                null ||
                                                            p.alert_1_max !==
                                                                null
                                                        "
                                                    >
                                                        {{
                                                            formatValue(
                                                                p.alert_1_min,
                                                                p.decimals,
                                                            )
                                                        }}
                                                        -
                                                        {{
                                                            formatValue(
                                                                p.alert_1_max,
                                                                p.decimals,
                                                            )
                                                        }}
                                                    </span>
                                                    <span v-else>-</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div
                                    v-else
                                    class="py-2 text-center text-xs text-muted-foreground"
                                >
                                    Nenhum parâmetro detalhado registrado.
                                </div>

                                <!-- Comentário do Sistema se houver -->
                                <div
                                    v-if="systemItem.comment"
                                    class="mt-2 flex items-start gap-1.5 rounded bg-muted/40 p-2 text-xs text-foreground"
                                >
                                    <MessageSquare
                                        class="mt-0.5 h-3 w-3 shrink-0 text-muted-foreground"
                                    />
                                    <p
                                        class="font-mono text-[11px] whitespace-pre-wrap"
                                    >
                                        {{ systemItem.comment }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paginação do Histórico -->
            <div
                v-if="lastPage > 1"
                class="flex items-center justify-between border-t border-border/70 bg-card p-4 text-xs text-muted-foreground"
            >
                <div>
                    Página <strong>{{ currentPage }}</strong> de
                    <strong>{{ lastPage }}</strong> ({{ total }} envios)
                </div>

                <div class="flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="currentPage <= 1 || isLoading"
                        @click="fetchHistory(currentPage - 1)"
                        class="h-8 w-8 cursor-pointer p-0"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="currentPage >= lastPage || isLoading"
                        @click="fetchHistory(currentPage + 1)"
                        class="h-8 w-8 cursor-pointer p-0"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
