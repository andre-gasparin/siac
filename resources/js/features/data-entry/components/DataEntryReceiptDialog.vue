<script setup lang="ts">
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Edit3,
    FileText,
    Loader2,
    Send,
} from '@lucide/vue';
import { computed } from 'vue';
import type { DraftStorageMap } from '@/features/data-entry/composables/useDataEntryDraft';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';

interface ParameterItem {
    id: number;
    monitored_system_id: number;
    team_id: number;
    name: string;
    code?: string | null;
    tag?: string | null;
    unit?: string | null;
    decimals: number;
    sort_order: number;
    alert_1_min: number | null;
    alert_1_max: number | null;
}

interface SystemItem {
    id: number;
    team_id: number;
    name: string;
    sort_order: number;
    parameters: ParameterItem[];
}

const props = defineProps<{
    open: boolean;
    collectionDate: string;
    collectionTime: string;
    systems: SystemItem[];
    drafts: DraftStorageMap;
    isSubmitting?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'editSystem', systemId: number): void;
    (e: 'confirmSend'): void;
}>();

function parseNumber(val: string | undefined): number | null {
    if (!val || val.trim() === '') {
        return null;
    }

    const clean = val.trim().replace(/\s/g, '').replace(',', '.');
    const parsed = parseFloat(clean);

    return isNaN(parsed) ? null : parsed;
}

function formatDisplayValue(
    val: number | null | undefined,
    decimals: number,
): string {
    if (val === null || val === undefined || isNaN(val)) {
        return '-';
    }

    return val.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals ?? 2,
        maximumFractionDigits: decimals ?? 2,
    });
}

function formatDisplayLimit(
    val: number | null | undefined,
    decimals: number,
): string {
    if (val === null || val === undefined || isNaN(val)) {
        return '-';
    }

    return val.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals ?? 2,
        maximumFractionDigits: decimals ?? 2,
    });
}

function isParamOutOfLimits(
    param: ParameterItem,
    rawVal: string | undefined,
): boolean {
    const numericVal = parseNumber(rawVal);

    if (numericVal === null) {
        return false;
    }

    if (param.alert_1_min !== null && numericVal < param.alert_1_min) {
        return true;
    }

    if (param.alert_1_max !== null && numericVal > param.alert_1_max) {
        return true;
    }

    return false;
}

interface SystemReceiptSummary {
    system: SystemItem;
    filledParams: Array<{
        param: ParameterItem;
        rawValue: string;
        numericValue: number;
        isOutOfLimits: boolean;
    }>;
    comment: string;
    hasOutOfLimits: boolean;
}

const filledSystemsList = computed<SystemReceiptSummary[]>(() => {
    const list: SystemReceiptSummary[] = [];

    for (const system of props.systems) {
        const draft = props.drafts[system.id];

        if (!draft) {
            continue;
        }

        const filledParams: SystemReceiptSummary['filledParams'] = [];
        let hasOutOfLimits = false;

        for (const param of system.parameters) {
            const rawVal = draft.values?.[param.id];
            const num = parseNumber(rawVal);

            if (num !== null) {
                const outOfLimits = isParamOutOfLimits(param, rawVal);

                if (outOfLimits) {
                    hasOutOfLimits = true;
                }

                filledParams.push({
                    param,
                    rawValue: rawVal!,
                    numericValue: num,
                    isOutOfLimits: outOfLimits,
                });
            }
        }

        const trimmedComment = (draft.comment || '').trim();

        if (filledParams.length > 0 || trimmedComment !== '') {
            list.push({
                system,
                filledParams,
                comment: trimmedComment,
                hasOutOfLimits,
            });
        }
    }

    return list;
});

const emptySystemsList = computed<SystemItem[]>(() => {
    const filledIds = new Set(filledSystemsList.value.map((s) => s.system.id));

    return props.systems.filter((s) => !filledIds.has(s.id));
});

const totalFilledValuesCount = computed<number>(() => {
    return filledSystemsList.value.reduce(
        (acc, item) => acc + item.filledParams.length,
        0,
    );
});

const formattedDateTime = computed<string>(() => {
    if (!props.collectionDate) {
        return '';
    }

    const [year, month, day] = props.collectionDate.split('-');
    const dateFormatted = `${day}/${month}/${year}`;
    const timeFormatted = props.collectionTime || '00:00';

    return `${dateFormatted} às ${timeFormatted}`;
});

function handleEdit(systemId: number) {
    emit('update:open', false);
    emit('editSystem', systemId);
}

function handleConfirm() {
    emit('confirmSend');
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent
            class="max-h-[90vh] max-w-4xl overflow-y-auto p-0 sm:rounded-2xl"
        >
            <!-- Cabeçalho -->
            <DialogHeader class="border-b border-border/70 p-6 pb-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <FileText class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold text-foreground">
                            Recibo de Conferência - Entrada de Dados
                        </DialogTitle>
                        <DialogDescription
                            class="text-xs text-muted-foreground"
                        >
                            Confira as medições salvas em memória antes de
                            persistir no banco de dados.
                        </DialogDescription>
                    </div>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/40 px-3.5 py-2 text-xs font-medium text-foreground"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground"
                            >Horário da Coleta:</span
                        >
                        <span class="font-bold text-foreground">{{
                            formattedDateTime
                        }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            >{{ filledSystemsList.length }} de
                            {{ systems.length }} sistemas</span
                        >
                        <span>•</span>
                        <span
                            >{{ totalFilledValuesCount }} parâmetro(s)
                            medido(s)</span
                        >
                    </div>
                </div>
            </DialogHeader>

            <!-- Conteúdo: Lista de Sistemas com Dados -->
            <div class="flex flex-col gap-6 p-6">
                <!-- Se não houver nenhum dado em memória -->
                <div
                    v-if="filledSystemsList.length === 0"
                    class="flex flex-col items-center justify-center gap-3 py-12 text-center"
                >
                    <AlertTriangle class="h-10 w-10 text-amber-500" />
                    <h3 class="text-base font-semibold text-foreground">
                        Nenhum dado salvo em memória
                    </h3>
                    <p class="max-w-md text-xs text-muted-foreground">
                        Preencha os resultados dos parâmetros ou comentários nos
                        sistemas e clique em "Salvar" para adicioná-los à
                        memória antes de enviar.
                    </p>
                </div>

                <!-- Lista de Sistemas com Medições Preenchidas -->
                <div v-else class="flex flex-col gap-4">
                    <div
                        v-for="item in filledSystemsList"
                        :key="item.system.id"
                        class="rounded-xl border border-border/80 bg-card p-4 shadow-2xs transition-all"
                        :class="[
                            item.hasOutOfLimits
                                ? 'border-red-300 dark:border-red-900/50'
                                : '',
                        ]"
                    >
                        <!-- Topo do Card do Sistema -->
                        <div
                            class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-border/50 pb-3"
                        >
                            <div class="flex items-center gap-2.5">
                                <CheckCircle2
                                    v-if="!item.hasOutOfLimits"
                                    class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                                />
                                <AlertTriangle
                                    v-else
                                    class="h-4 w-4 text-red-500"
                                />
                                <h4 class="text-sm font-bold text-foreground">
                                    {{ item.system.name }}
                                </h4>
                                <Badge
                                    v-if="item.hasOutOfLimits"
                                    variant="destructive"
                                    class="text-[10px] font-semibold"
                                >
                                    Fora dos Limites
                                </Badge>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="handleEdit(item.system.id)"
                                class="h-7 cursor-pointer gap-1.5 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <Edit3 class="h-3.5 w-3.5" />
                                <span>Editar</span>
                            </Button>
                        </div>

                        <!-- Tabela de Parâmetros Preenchidos -->
                        <div
                            v-if="item.filledParams.length > 0"
                            class="overflow-x-auto"
                        >
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-border/60 text-[11px] font-bold text-muted-foreground"
                                    >
                                        <th class="py-1.5 pr-3">Parâmetro</th>
                                        <th class="py-1.5 pr-3 text-right">
                                            Resultado
                                        </th>
                                        <th class="px-3 py-1.5 text-center">
                                            Unidade
                                        </th>
                                        <th class="px-3 py-1.5 text-center">
                                            Faixa de Controle
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/30">
                                    <tr
                                        v-for="p in item.filledParams"
                                        :key="p.param.id"
                                        class="transition-colors hover:bg-muted/10"
                                    >
                                        <td
                                            class="py-2 pr-3 font-medium text-foreground"
                                        >
                                            {{ p.param.name }}
                                        </td>
                                        <td
                                            class="py-2 pr-3 text-right font-mono font-bold"
                                            :class="[
                                                p.isOutOfLimits
                                                    ? 'text-red-600 dark:text-red-400'
                                                    : 'text-foreground',
                                            ]"
                                        >
                                            {{
                                                formatDisplayValue(
                                                    p.numericValue,
                                                    p.param.decimals,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-center text-muted-foreground"
                                        >
                                            {{ p.param.unit || '-' }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-center font-mono text-muted-foreground"
                                        >
                                            <span
                                                v-if="
                                                    p.param.alert_1_min !==
                                                        null ||
                                                    p.param.alert_1_max !== null
                                                "
                                            >
                                                {{
                                                    formatDisplayLimit(
                                                        p.param.alert_1_min,
                                                        p.param.decimals,
                                                    )
                                                }}
                                                -
                                                {{
                                                    formatDisplayLimit(
                                                        p.param.alert_1_max,
                                                        p.param.decimals,
                                                    )
                                                }}
                                            </span>
                                            <span v-else>-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Comentário do Sistema -->
                        <div
                            v-if="item.comment"
                            class="mt-3 rounded-lg bg-muted/40 p-2.5 text-xs text-foreground"
                        >
                            <span class="font-bold text-muted-foreground"
                                >Comentário:</span
                            >
                            <p class="mt-0.5 font-mono whitespace-pre-wrap">
                                {{ item.comment }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sistemas sem dados (informativo) -->
                <div
                    v-if="
                        emptySystemsList.length > 0 &&
                        filledSystemsList.length > 0
                    "
                    class="rounded-lg border border-dashed border-border/80 p-3 text-xs text-muted-foreground"
                >
                    <span class="font-semibold"
                        >Sistemas não preenchidos (não serão enviados):</span
                    >
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span
                            v-for="s in emptySystemsList"
                            :key="s.id"
                            class="rounded-md bg-muted px-2 py-0.5 text-[11px]"
                        >
                            {{ s.name }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Rodapé com Ações -->
            <DialogFooter
                class="border-t border-border/70 p-4 sm:justify-between sm:gap-2"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="isSubmitting"
                    @click="emit('update:open', false)"
                    class="cursor-pointer gap-1.5 text-xs font-semibold"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span>Voltar para alterar</span>
                </Button>

                <Button
                    type="button"
                    :disabled="isSubmitting || filledSystemsList.length === 0"
                    @click="handleConfirm"
                    class="flex cursor-pointer items-center gap-2 rounded-lg bg-emerald-800 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition-all hover:bg-emerald-900"
                >
                    <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                    <Send v-else class="h-4 w-4" />
                    <span>Confirmar e Enviar para o Banco</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
