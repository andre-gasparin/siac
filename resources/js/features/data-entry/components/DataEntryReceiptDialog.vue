<script setup lang="ts">
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Edit3,
    FileText,
    Loader2,
    Send,
    User as UserIcon,
} from '@lucide/vue';
import { computed } from 'vue';
import type {
    ParameterItem,
    SystemItem,
} from '@/features/data-entry/components/DataEntryForm.vue';
import type { ParamDiff } from '@/features/data-entry/composables/useDataEntryDraft';
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

export interface ModifiedParamItem extends ParamDiff {
    param: ParameterItem;
    isOutOfLimits: boolean;
}

export interface ModifiedSystemItem {
    system: SystemItem;
    changedParams: ModifiedParamItem[];
    commentChanged: boolean;
    oldComment: string;
    newComment: string | null;
    hasOutOfLimits: boolean;
}

const props = defineProps<{
    open: boolean;
    collectionDate: string;
    collectionTime: string;
    responsible?: string;
    modifiedSystems: ModifiedSystemItem[];
    unmodifiedSystems: SystemItem[];
    isSubmitting?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'editSystem', systemId: number): void;
    (e: 'confirmSend'): void;
}>();

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

const totalModifiedParamsCount = computed<number>(() => {
    return props.modifiedSystems.reduce(
        (acc, item) => acc + item.changedParams.length,
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
                            Recibo de Conferência - Alterações de Medição
                        </DialogTitle>
                        <DialogDescription
                            class="text-xs text-muted-foreground"
                        >
                            Confira as alterações que serão gravadas no banco de
                            dados. Apenas os campos modificados são alterados.
                        </DialogDescription>
                    </div>
                </div>

                <!-- Barra de Metadados (Data/Hora e Responsável) -->
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/40 px-3.5 py-2.5 text-xs font-medium text-foreground"
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
                        <UserIcon class="h-3.5 w-3.5 text-muted-foreground" />
                        <span class="text-muted-foreground">Responsável:</span>
                        <span class="font-bold text-foreground">
                            {{
                                responsible && responsible.trim() !== ''
                                    ? responsible
                                    : 'Não informado'
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center gap-2 font-semibold text-primary"
                    >
                        <span
                            >{{ modifiedSystems.length }} sistema(s) com
                            alterações</span
                        >
                        <span class="text-muted-foreground">•</span>
                        <span
                            >{{ totalModifiedParamsCount }} campo(s)
                            alterado(s)</span
                        >
                    </div>
                </div>
            </DialogHeader>

            <!-- Conteúdo do Recibo -->
            <div class="space-y-4 p-6">
                <!-- Se não houver sistemas modificados -->
                <div
                    v-if="modifiedSystems.length === 0"
                    class="rounded-xl border border-dashed border-border/80 bg-muted/20 p-8 text-center"
                >
                    <CheckCircle2
                        class="mx-auto mb-2 h-8 w-8 text-emerald-500 opacity-60"
                    />
                    <h3 class="text-sm font-bold text-foreground">
                        Nenhuma alteração detectada
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Todos os dados desta coleta já são idênticos aos
                        gravados no banco de dados.
                    </p>
                </div>

                <!-- Lista de Sistemas com Alterações -->
                <div v-else class="flex flex-col gap-4">
                    <div
                        v-for="item in modifiedSystems"
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
                                <span class="text-xs text-muted-foreground">
                                    ({{ item.changedParams.length }} campo(s)
                                    modificado(s))
                                </span>
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

                        <!-- Tabela de Parâmetros Modificados -->
                        <div
                            v-if="item.changedParams.length > 0"
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
                                        <th class="px-2 py-1.5 text-center">
                                            Tipo
                                        </th>
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
                                        v-for="p in item.changedParams"
                                        :key="p.parameter_id"
                                        class="transition-colors hover:bg-muted/10"
                                    >
                                        <td
                                            class="py-2 pr-3 font-medium text-foreground"
                                        >
                                            {{ p.param.name }}
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <Badge
                                                v-if="p.status === 'added'"
                                                variant="outline"
                                                class="border-emerald-500/30 bg-emerald-500/10 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                            >
                                                Novo
                                            </Badge>
                                            <Badge
                                                v-else-if="
                                                    p.status === 'updated'
                                                "
                                                variant="outline"
                                                class="border-blue-500/30 bg-blue-500/10 text-[10px] font-semibold text-blue-600 dark:text-blue-400"
                                            >
                                                Alterado
                                            </Badge>
                                            <Badge
                                                v-else-if="
                                                    p.status === 'cleared'
                                                "
                                                variant="outline"
                                                class="border-amber-500/30 bg-amber-500/10 text-[10px] font-semibold text-amber-600 dark:text-amber-400"
                                            >
                                                Apagado
                                            </Badge>
                                        </td>
                                        <td
                                            class="py-2 pr-3 text-right font-mono font-bold"
                                        >
                                            <div
                                                v-if="p.status === 'updated'"
                                                class="flex items-center justify-end gap-1.5"
                                            >
                                                <span
                                                    class="text-xs font-normal text-muted-foreground line-through"
                                                >
                                                    {{
                                                        formatDisplayValue(
                                                            p.old_value,
                                                            p.param.decimals,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="font-normal text-muted-foreground"
                                                    >➔</span
                                                >
                                                <span
                                                    :class="
                                                        p.isOutOfLimits
                                                            ? 'text-red-600 dark:text-red-400'
                                                            : 'text-foreground'
                                                    "
                                                >
                                                    {{
                                                        formatDisplayValue(
                                                            p.new_value,
                                                            p.param.decimals,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                v-else-if="
                                                    p.status === 'cleared'
                                                "
                                                class="flex items-center justify-end gap-1.5"
                                            >
                                                <span
                                                    class="text-xs font-normal text-muted-foreground line-through"
                                                >
                                                    {{
                                                        formatDisplayValue(
                                                            p.old_value,
                                                            p.param.decimals,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="font-normal text-muted-foreground"
                                                    >➔</span
                                                >
                                                <span
                                                    class="font-normal text-amber-600 italic dark:text-amber-400"
                                                >
                                                    Removido
                                                </span>
                                            </div>
                                            <div
                                                v-else
                                                :class="
                                                    p.isOutOfLimits
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : 'text-foreground'
                                                "
                                            >
                                                {{
                                                    formatDisplayValue(
                                                        p.new_value,
                                                        p.param.decimals,
                                                    )
                                                }}
                                            </div>
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
                            v-if="item.commentChanged || item.newComment"
                            class="mt-3 rounded-lg bg-muted/40 p-2.5 text-xs text-foreground"
                        >
                            <span class="font-bold text-muted-foreground"
                                >Comentário:</span
                            >
                            <p
                                v-if="item.newComment"
                                class="mt-0.5 font-mono whitespace-pre-wrap"
                            >
                                {{ item.newComment }}
                            </p>
                            <p
                                v-else
                                class="mt-0.5 font-mono text-muted-foreground italic"
                            >
                                (Comentário removido)
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sistemas inalterados (informativo) -->
                <div
                    v-if="
                        unmodifiedSystems.length > 0 &&
                        modifiedSystems.length > 0
                    "
                    class="rounded-lg border border-dashed border-border/80 p-3 text-xs text-muted-foreground"
                >
                    <span class="font-semibold">
                        Sistemas inalterados (mantidos como estão no banco, não
                        reenviados):
                    </span>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span
                            v-for="s in unmodifiedSystems"
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
                class="flex flex-col-reverse items-center justify-between gap-3 border-t border-border/70 p-6 sm:flex-row"
            >
                <Button
                    type="button"
                    variant="outline"
                    @click="emit('update:open', false)"
                    class="w-full cursor-pointer gap-1.5 sm:w-auto"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span>Voltar ao Formulário</span>
                </Button>

                <Button
                    type="button"
                    @click="handleConfirm"
                    :disabled="isSubmitting || modifiedSystems.length === 0"
                    class="w-full cursor-pointer gap-2 bg-primary font-bold text-primary-foreground shadow-md hover:bg-primary/90 sm:w-auto"
                >
                    <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                    <Send v-else class="h-4 w-4" />
                    <span>Confirmar e Enviar Lote</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
