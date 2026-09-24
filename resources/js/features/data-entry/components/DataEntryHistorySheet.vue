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
import { destroy as dataEntryHistoryDestroy } from '@/routes/data-entry/history';
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

interface BatchItem {
    id: number;
    system_name: string;
    monitored_system_id: number;
    user_name: string;
    user_id: number | null;
    status: 'completed' | 'reverted';
    collected_at: string;
    created_at: string | null;
    reverted_at: string | null;
    reverted_by_name: string | null;
    saved_values_count: number;
    comment: string | null;
    parameters_data: ParameterDetail[];
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
const batches = ref<BatchItem[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);

const filterSystemId = ref<string>('all');
const filterStatus = ref<'all' | 'completed' | 'reverted'>('all');

const expandedBatchIds = ref<number[]>([]);
const confirmingDeleteId = ref<number | null>(null);
const isDeletingId = ref<number | null>(null);

function toggleExpand(id: number) {
    if (expandedBatchIds.value.includes(id)) {
        expandedBatchIds.value = expandedBatchIds.value.filter(
            (bId) => bId !== id,
        );
    } else {
        expandedBatchIds.value.push(id);
    }
}

function isExpanded(id: number): boolean {
    return expandedBatchIds.value.includes(id);
}

async function fetchHistory(page = 1) {
    isLoading.value = true;
    confirmingDeleteId.value = null;

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
        batches.value = data.data || [];
        currentPage.value = data.current_page || 1;
        lastPage.value = data.last_page || 1;
        total.value = data.total || 0;
    } catch (err: any) {
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

async function revertBatch(batch: BatchItem) {
    isDeletingId.value = batch.id;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const response = await fetch(
            dataEntryHistoryDestroy.url({
                current_team: props.currentTeam.slug,
                batch: batch.id,
            }),
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );

        const data = await response.json();

        if (!response.ok) {
            const errorMsg = data.message || 'Falha ao reverter o envio.';
            toast.error(errorMsg);

            return;
        }

        toast.success(
            'Envio excluído e medições revertidas com sucesso! O registro foi mantido no histórico.',
        );
        confirmingDeleteId.value = null;
        await fetchHistory(currentPage.value);
    } catch (err: any) {
        console.error('Erro ao reverter envio:', err);
        toast.error('Erro na comunicação com o servidor.');
    } finally {
        isDeletingId.value = null;
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
                            Auditoria e reversão de medições manuais inseridas
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
                    <label class="text-xs font-semibold text-muted-foreground"
                        >Sistema</label
                    >
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
                    <label class="text-xs font-semibold text-muted-foreground"
                        >Status</label
                    >
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

            <!-- Conteúdo da Lista -->
            <div class="flex-1 space-y-3 overflow-y-auto p-4">
                <!-- Loading State -->
                <div
                    v-if="isLoading && batches.length === 0"
                    class="flex flex-col items-center justify-center py-16 text-muted-foreground"
                >
                    <Loader2 class="mb-2 h-8 w-8 animate-spin text-primary" />
                    <span class="text-sm font-medium"
                        >Carregando histórico...</span
                    >
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="batches.length === 0"
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

                <!-- List of Batches -->
                <div
                    v-for="batch in batches"
                    :key="batch.id"
                    class="overflow-hidden rounded-xl border transition-all"
                    :class="[
                        batch.status === 'reverted'
                            ? 'border-border/60 bg-muted/10 opacity-75'
                            : 'border-border/80 bg-card shadow-xs hover:border-border',
                    ]"
                >
                    <!-- Card Header / Summary -->
                    <div class="flex flex-col gap-2.5 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <span
                                    class="truncate text-sm font-bold"
                                    :class="[
                                        batch.status === 'reverted'
                                            ? 'text-muted-foreground line-through'
                                            : 'text-foreground',
                                    ]"
                                    :title="batch.system_name"
                                >
                                    {{ batch.system_name }}
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <!-- Status Badge -->
                                <Badge
                                    v-if="batch.status === 'completed'"
                                    variant="outline"
                                    class="flex items-center gap-1 border-emerald-500/20 bg-emerald-500/10 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Ativo
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="flex items-center gap-1 border-red-500/20 bg-red-500/10 py-0.5 text-[11px] font-semibold text-red-600 dark:text-red-400"
                                >
                                    <X class="h-3 w-3" />
                                    Cancelado
                                </Badge>
                            </div>
                        </div>

                        <!-- Metadados -->
                        <div
                            class="grid grid-cols-1 gap-x-4 gap-y-1.5 text-xs text-muted-foreground sm:grid-cols-2"
                        >
                            <div class="flex items-center gap-1.5">
                                <Clock
                                    class="h-3.5 w-3.5 shrink-0 opacity-70"
                                />
                                <span
                                    >Coleta:
                                    <strong class="text-foreground/90">{{
                                        batch.collected_at
                                    }}</strong></span
                                >
                            </div>
                            <div class="flex items-center gap-1.5">
                                <UserIcon
                                    class="h-3.5 w-3.5 shrink-0 opacity-70"
                                />
                                <span class="truncate"
                                    >Por:
                                    <strong class="text-foreground/90">{{
                                        batch.user_name
                                    }}</strong></span
                                >
                            </div>
                        </div>

                        <!-- Detalhes de cancelamento se revertido -->
                        <div
                            v-if="batch.status === 'reverted'"
                            class="flex items-center gap-2 rounded-lg border border-red-200/60 bg-red-50/50 p-2 text-[11px] text-red-600 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-400"
                        >
                            <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                            <span>
                                Cancelado por
                                <strong>{{
                                    batch.reverted_by_name || 'Usuário'
                                }}</strong>
                                em
                                {{ batch.reverted_at || 'data desconhecida' }}.
                            </span>
                        </div>

                        <!-- Rodapé do Card com Ações -->
                        <div
                            class="mt-1 flex items-center justify-between border-t border-border/40 pt-2"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="toggleExpand(batch.id)"
                                class="h-7 cursor-pointer gap-1 px-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                            >
                                <span>{{
                                    isExpanded(batch.id)
                                        ? 'Ocultar medições'
                                        : `Ver medições (${batch.saved_values_count || 0})`
                                }}</span>
                                <component
                                    :is="
                                        isExpanded(batch.id)
                                            ? ChevronUp
                                            : ChevronDown
                                    "
                                    class="h-3.5 w-3.5"
                                />
                            </Button>

                            <!-- Botão Excluir Envio -->
                            <div
                                v-if="
                                    batch.can_revert &&
                                    batch.status === 'completed'
                                "
                            >
                                <!-- Caixa de Confirmação Inline -->
                                <div
                                    v-if="confirmingDeleteId === batch.id"
                                    class="flex items-center gap-1.5"
                                >
                                    <span
                                        class="text-[11px] font-semibold text-red-600 dark:text-red-400"
                                        >Confirmar?</span
                                    >
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        :disabled="isDeletingId === batch.id"
                                        @click="revertBatch(batch)"
                                        class="h-7 cursor-pointer gap-1 px-2 text-xs font-semibold"
                                    >
                                        <Loader2
                                            v-if="isDeletingId === batch.id"
                                            class="h-3 w-3 animate-spin"
                                        />
                                        <span>Excluir</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        @click="confirmingDeleteId = null"
                                        class="h-7 cursor-pointer px-2 text-xs"
                                    >
                                        Cancelar
                                    </Button>
                                </div>

                                <Button
                                    v-else
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="confirmingDeleteId = batch.id"
                                    class="h-7 cursor-pointer gap-1 border-red-200 px-2 text-xs font-semibold text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-400 dark:hover:bg-red-950/30"
                                >
                                    <Trash2 class="h-3 w-3" />
                                    <span>Excluir envio</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Seção Retrátil: Tabela de Parâmetros e Comentário -->
                    <div
                        v-if="isExpanded(batch.id)"
                        class="flex flex-col gap-3 border-t border-border/60 bg-muted/20 p-4 text-xs"
                    >
                        <!-- Tabela de Valores -->
                        <div
                            class="overflow-hidden rounded-lg border border-border/60 bg-card"
                        >
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-border/80 bg-muted/40 font-bold text-foreground"
                                    >
                                        <th class="px-3 py-2 text-left">
                                            Parâmetro
                                        </th>
                                        <th class="px-3 py-2 text-center">
                                            Resultado
                                        </th>
                                        <th class="px-3 py-2 text-center">
                                            Unidade
                                        </th>
                                        <th class="px-3 py-2 text-center">
                                            Mín/Máx
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr
                                        v-for="param in batch.parameters_data"
                                        :key="param.parameter_id"
                                        class="hover:bg-muted/10"
                                    >
                                        <td
                                            class="px-3 py-2 font-medium text-foreground"
                                        >
                                            {{ param.name }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-center font-bold"
                                            :class="[
                                                isParamOutOfLimits(param)
                                                    ? 'text-red-600 dark:text-red-400'
                                                    : 'text-foreground',
                                            ]"
                                        >
                                            {{
                                                formatValue(
                                                    param.value,
                                                    param.decimals,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-center font-medium text-muted-foreground"
                                        >
                                            {{ param.unit || '-' }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-center font-mono text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                formatValue(
                                                    param.alert_1_min,
                                                    param.decimals,
                                                )
                                            }}
                                            /
                                            {{
                                                formatValue(
                                                    param.alert_1_max,
                                                    param.decimals,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !batch.parameters_data ||
                                            batch.parameters_data.length === 0
                                        "
                                    >
                                        <td
                                            colspan="4"
                                            class="py-4 text-center text-muted-foreground"
                                        >
                                            Nenhum detalhe disponível.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Comentário -->
                        <div
                            v-if="batch.comment"
                            class="flex flex-col gap-1 rounded-lg border border-border/60 bg-background p-3"
                        >
                            <div
                                class="flex items-center gap-1.5 text-[11px] font-bold text-foreground"
                            >
                                <MessageSquare class="h-3 w-3 text-primary" />
                                <span>Comentário / Justificativa:</span>
                            </div>
                            <p
                                class="mt-1 border-l-2 border-primary/40 pl-4 text-xs leading-relaxed whitespace-pre-wrap text-foreground/80"
                            >
                                {{ batch.comment }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer com Paginação -->
            <div
                v-if="lastPage > 1"
                class="flex items-center justify-between border-t border-border/70 bg-card p-4 text-xs text-muted-foreground"
            >
                <span
                    >Página <strong>{{ currentPage }}</strong> de
                    <strong>{{ lastPage }}</strong> (Total: {{ total }})</span
                >
                <div class="flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="currentPage <= 1 || isLoading"
                        @click="fetchHistory(currentPage - 1)"
                        class="h-8 cursor-pointer rounded-lg px-2 text-xs"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />
                        <span>Anterior</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="currentPage >= lastPage || isLoading"
                        @click="fetchHistory(currentPage + 1)"
                        class="h-8 cursor-pointer rounded-lg px-2 text-xs"
                    >
                        <span>Próxima</span>
                        <ChevronRight class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
