<script setup lang="ts">
import {
    AlertTriangle,
    Clock,
    FileText,
    History,
    Loader2,
    Save,
    Send,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import DatePicker from '@/shared/components/DatePicker.vue';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';

export interface ParameterItem {
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

export interface SystemItem {
    id: number;
    team_id: number;
    name: string;
    sort_order: number;
    parameters: ParameterItem[];
}

const props = defineProps<{
    currentSystem?: SystemItem;
    collectionDate: string;
    collectionTime: string;
    liveTimestamp: string;
    isLoadingEntries: boolean;
    paramInputs: Record<number, string>;
    comment: string;
    draftCount: number;
}>();

const emit = defineEmits<{
    (e: 'update:collectionDate', val: string): void;
    (e: 'update:collectionTime', val: string): void;
    (e: 'update:paramInputs', val: Record<number, string>): void;
    (e: 'update:comment', val: string): void;
    (e: 'save', andAdvance: boolean): void;
    (e: 'send'): void;
    (e: 'openHistory'): void;
}>();

const textareaRef = ref<HTMLTextAreaElement | null>(null);

function adjustTextareaHeight() {
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
        textareaRef.value.style.height = `${Math.max(80, textareaRef.value.scrollHeight)}px`;
    }
}

watch(
    () => props.comment,
    () => {
        nextTick(() => {
            adjustTextareaHeight();
        });
    },
);

function handleCommentInput(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    emit('update:comment', target.value);
    adjustTextareaHeight();
}

function sanitizeDecimalInput(val: string): string {
    if (!val) {
        return '';
    }

    let clean = val.replace(/\./g, ',');

    clean = clean.replace(/[^0-9,-]/g, '');

    const hasMinus = clean.startsWith('-');
    clean = clean.replace(/-/g, '');

    if (hasMinus) {
        clean = `-${clean}`;
    }

    const parts = clean.split(',');

    if (parts.length > 2) {
        clean = `${parts[0]},${parts.slice(1).join('')}`;
    }

    return clean;
}

function handleKeydown(e: KeyboardEvent) {
    if (
        e.key === 'Backspace' ||
        e.key === 'Delete' ||
        e.key === 'Tab' ||
        e.key === 'Escape' ||
        e.key === 'Enter' ||
        e.key === 'ArrowLeft' ||
        e.key === 'ArrowRight' ||
        e.key === 'ArrowUp' ||
        e.key === 'ArrowDown' ||
        e.key === 'Home' ||
        e.key === 'End' ||
        e.ctrlKey ||
        e.metaKey
    ) {
        return;
    }

    if (/^[0-9]$/.test(e.key)) {
        return;
    }

    if (e.key === ',' || e.key === '.') {
        const input = e.target as HTMLInputElement;

        if (input.value.includes(',') || input.value.includes('.')) {
            e.preventDefault();
        }

        return;
    }

    if (e.key === '-') {
        const input = e.target as HTMLInputElement;

        if (input.selectionStart !== 0 || input.value.includes('-')) {
            e.preventDefault();
        }

        return;
    }

    e.preventDefault();
}

function handleParamInput(paramId: number, val: string) {
    const sanitized = sanitizeDecimalInput(val);
    const updated = { ...props.paramInputs, [paramId]: sanitized };
    emit('update:paramInputs', updated);
}

function parseNumber(val: string | undefined): number | null {
    if (!val || val.trim() === '') {
        return null;
    }

    const clean = val.trim().replace(/\s/g, '').replace(',', '.');
    const parsed = parseFloat(clean);

    return isNaN(parsed) ? null : parsed;
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

function isParamOutOfLimits(param: ParameterItem): boolean {
    const rawVal = props.paramInputs[param.id];
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

const hasAnyOutOfLimits = computed<boolean>(() => {
    if (!props.currentSystem) {
        return false;
    }

    return props.currentSystem.parameters.some((p) => isParamOutOfLimits(p));
});
</script>

<template>
    <div
        class="w-full flex-1 rounded-2xl border border-border/80 bg-card p-6 shadow-xs md:p-8"
    >
        <!-- Cabeçalho do Sistema e Botão Histórico -->
        <div
            class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-3"
        >
            <h1 class="text-xl font-bold text-foreground">
                {{ currentSystem?.name || 'Selecione um Sistema' }}
            </h1>

            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="emit('openHistory')"
                class="cursor-pointer gap-1.5 rounded-xl text-xs font-semibold shadow-2xs hover:bg-accent"
            >
                <History class="h-4 w-4 text-primary" />
                <span>Histórico de envios</span>
            </Button>
        </div>

        <div class="flex flex-col gap-8">
            <!-- Seção de Horários (Hora da coleta e Hora do registro) -->
            <div class="flex max-w-xl flex-col gap-4">
                <!-- Hora da Coleta -->
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4"
                >
                    <span
                        class="w-36 shrink-0 text-sm font-bold text-foreground"
                    >
                        Hora da coleta
                    </span>
                    <div class="flex flex-1 items-center gap-2">
                        <div class="w-36">
                            <DatePicker
                                :model-value="collectionDate"
                                @update:model-value="
                                    (val) => emit('update:collectionDate', val)
                                "
                                placeholder="Data"
                            />
                        </div>
                        <div class="w-28">
                            <Input
                                :model-value="collectionTime"
                                @update:model-value="
                                    (val) =>
                                        emit(
                                            'update:collectionTime',
                                            String(val),
                                        )
                                "
                                type="time"
                                class="h-9 px-2 text-center font-mono text-sm"
                            />
                        </div>
                        <div
                            v-if="isLoadingEntries"
                            class="flex animate-in items-center gap-1.5 text-xs font-medium text-muted-foreground duration-200 fade-in"
                        >
                            <Loader2
                                class="h-4 w-4 animate-spin text-primary"
                            />
                            <span class="hidden sm:inline">Buscando...</span>
                        </div>
                    </div>
                </div>

                <!-- Hora do Registro -->
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4"
                >
                    <span
                        class="w-36 shrink-0 text-sm font-bold text-foreground"
                    >
                        Hora do registro
                    </span>
                    <div
                        class="flex items-center gap-2 font-mono text-sm font-medium text-muted-foreground"
                    >
                        <Clock class="h-4 w-4 opacity-70" />
                        <span>{{ liveTimestamp }}</span>
                    </div>
                </div>
            </div>

            <!-- Tabela de Parâmetros -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-border/80 text-xs font-bold text-foreground"
                        >
                            <th class="w-52 px-2 py-3 text-left">Parâmetro</th>
                            <th class="w-40 px-2 py-3 text-left">Resultado</th>
                            <th class="w-28 px-2 py-3 text-center">Unidade</th>
                            <th class="w-28 px-2 py-3 text-center">Minimo</th>
                            <th class="w-28 px-2 py-3 text-center">Maximo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr
                            v-for="param in currentSystem?.parameters || []"
                            :key="param.id"
                            class="transition-colors hover:bg-muted/10"
                        >
                            <!-- Nome do Parâmetro -->
                            <td class="px-2 py-3 font-semibold text-foreground">
                                {{ param.name }}
                            </td>

                            <!-- Resultado (Input) -->
                            <td class="px-2 py-2.5 text-left">
                                <input
                                    :value="paramInputs[param.id] || ''"
                                    @keydown="handleKeydown"
                                    @input="
                                        (e) =>
                                            handleParamInput(
                                                param.id,
                                                (e.target as HTMLInputElement)
                                                    .value,
                                            )
                                    "
                                    type="text"
                                    inputmode="decimal"
                                    class="h-9 w-32 rounded-md border bg-background px-3 text-left text-sm font-medium transition-all outline-none"
                                    :class="[
                                        isParamOutOfLimits(param)
                                            ? 'border-red-500 bg-red-50/20 font-bold text-red-600 ring-2 ring-red-500/20 dark:bg-red-950/20 dark:text-red-400'
                                            : 'border-border/80 text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20',
                                    ]"
                                />
                            </td>

                            <!-- Unidade -->
                            <td
                                class="px-2 py-3 text-center font-medium text-muted-foreground"
                            >
                                {{ param.unit || '-' }}
                            </td>

                            <!-- Mínimo -->
                            <td
                                class="px-2 py-3 text-center font-mono text-muted-foreground"
                            >
                                {{
                                    formatDisplayLimit(
                                        param.alert_1_min,
                                        param.decimals,
                                    )
                                }}
                            </td>

                            <!-- Máximo -->
                            <td
                                class="px-2 py-3 text-center font-mono text-muted-foreground"
                            >
                                {{
                                    formatDisplayLimit(
                                        param.alert_1_max,
                                        param.decimals,
                                    )
                                }}
                            </td>
                        </tr>

                        <tr
                            v-if="
                                !currentSystem?.parameters ||
                                currentSystem.parameters.length === 0
                            "
                        >
                            <td
                                colspan="5"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                Nenhum parâmetro cadastrado para este sistema.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Seção de Comentário -->
            <div class="flex flex-col items-start gap-4 pt-2 sm:flex-row">
                <span
                    class="w-36 shrink-0 pt-2 text-sm font-bold text-foreground"
                >
                    Comentário
                </span>
                <div class="flex w-full flex-1 flex-col gap-2">
                    <textarea
                        ref="textareaRef"
                        :value="comment"
                        @input="handleCommentInput"
                        rows="3"
                        placeholder="Insira observações ou justificativas..."
                        class="w-full resize-none rounded-md border bg-background p-3 text-sm transition-all outline-none"
                        :class="[
                            hasAnyOutOfLimits
                                ? 'border-red-500 text-foreground ring-2 ring-red-500/20'
                                : 'border-border/80 text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20',
                        ]"
                    ></textarea>

                    <!-- Mensagem de Advertência de Limites -->
                    <div
                        v-if="hasAnyOutOfLimits"
                        class="flex items-start gap-2 pt-1 text-xs leading-relaxed font-semibold text-red-600 transition-all dark:text-red-400"
                    >
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            Os parâmetros acima destacados em vermelho estão em
                            desacordo com as respectivas faixas de controle
                            estabelecidas. Recomenda-se confirmar e inserir as
                            justificativas e/ou ações tomadas no campo de
                            comentários acima.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex flex-wrap items-center gap-3 pt-4">
                <!-- Salvar -->
                <Button
                    type="button"
                    @click="emit('save', false)"
                    class="flex cursor-pointer items-center gap-2 rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white shadow-xs transition-all hover:bg-emerald-900"
                >
                    <Save class="h-4 w-4" />
                    <span>Salvar</span>
                </Button>

                <!-- Salvar e Avançar -->
                <Button
                    type="button"
                    @click="emit('save', true)"
                    class="flex cursor-pointer items-center gap-2 rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white shadow-xs transition-all hover:bg-emerald-900"
                >
                    <Send class="h-4 w-4" />
                    <span>Salvar e Avançar</span>
                </Button>

                <!-- Enviar (Abre o Recibo) -->
                <Button
                    type="button"
                    variant="default"
                    @click="emit('send')"
                    class="flex cursor-pointer items-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary/90"
                >
                    <FileText class="h-4 w-4" />
                    <span>Enviar</span>
                    <span
                        v-if="draftCount > 0"
                        class="ml-1 rounded-full bg-primary-foreground/20 px-2 py-0.5 text-xs font-bold"
                    >
                        {{ draftCount }}
                    </span>
                </Button>
            </div>
        </div>
    </div>
</template>
