<script setup lang="ts">
import { AlertTriangle, CheckCircle2, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { PreviewResponse } from '@/features/spreadsheet-imports/types';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';

const props = defineProps<{
    open: boolean;
    fileName: string;
    preview: PreviewResponse | null;
    isSubmitting: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}>();

const statusFilter = ref<'all' | 'valid' | 'updates' | 'invalid'>('all');
const dateFilter = ref<string>('all');

const uniqueDates = computed(() => {
    if (!props.preview?.items) {
        return [];
    }

    const dates = new Set<string>();

    for (const item of props.preview.items) {
        if (item.measured_at) {
            const d = item.measured_at.split(' ')[0];

            if (d) {
                dates.add(d);
            }
        }
    }

    return Array.from(dates).sort();
});

function formatOnlyDate(val: string): string {
    const parts = val.split('-');

    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    return val;
}

const filteredItems = computed(() => {
    if (!props.preview?.items) {
        return [];
    }

    let items = props.preview.items;

    if (dateFilter.value !== 'all') {
        items = items.filter((i) =>
            i.measured_at?.startsWith(dateFilter.value),
        );
    }

    if (statusFilter.value === 'valid') {
        return items.filter((i) => i.status === 'valid' && !i.is_update);
    }

    if (statusFilter.value === 'updates') {
        return items.filter((i) => i.is_update);
    }

    if (statusFilter.value === 'invalid') {
        return items.filter((i) => i.status === 'empty_or_invalid');
    }

    return items;
});

const validCount = computed(() => {
    return props.preview?.items.filter((i) => i.status === 'valid').length ?? 0;
});

function formatDateTime(val?: string | null): string {
    if (!val) {
        return '-';
    }

    const [datePart, timePart] = val.split(' ');

    if (!datePart) {
        return val;
    }

    const [y, m, d] = datePart.split('-');

    if (!y || !m || !d) {
        return val;
    }

    const formattedDate = `${d}/${m}/${y}`;

    if (timePart && timePart !== '00:00:00') {
        return `${formattedDate} ${timePart.substring(0, 5)}`;
    }

    return formattedDate;
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[90vh] flex-col p-6 sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span>Conferência de Importação</span>
                        <span
                            class="rounded bg-muted px-2.5 py-0.5 font-mono text-xs font-medium text-foreground"
                        >
                            {{ fileName }}
                        </span>
                    </div>
                </DialogTitle>
                <DialogDescription>
                    Revise os valores extraídos da planilha, datas resolvidas e
                    multiplicadores aplicados antes de gravar definitivamente.
                </DialogDescription>
            </DialogHeader>

            <div v-if="preview" class="flex min-h-0 flex-1 flex-col gap-3 py-2">
                <!-- Stat Cards -->
                <div class="grid grid-cols-4 gap-2 text-xs">
                    <button
                        type="button"
                        :class="[
                            'rounded-lg border p-2.5 text-left transition-colors',
                            statusFilter === 'all'
                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                : 'bg-muted/30 hover:bg-muted/50',
                        ]"
                        @click="statusFilter = 'all'"
                    >
                        <div class="font-medium text-muted-foreground">
                            Total Extraído
                        </div>
                        <div class="text-lg font-bold text-foreground">
                            {{ preview.total_extracted }}
                        </div>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'rounded-lg border p-2.5 text-left transition-colors',
                            statusFilter === 'valid'
                                ? 'border-emerald-500 bg-emerald-500/5 ring-1 ring-emerald-500'
                                : 'bg-muted/30 hover:bg-muted/50',
                        ]"
                        @click="statusFilter = 'valid'"
                    >
                        <div
                            class="font-medium text-emerald-700 dark:text-emerald-400"
                        >
                            Novos Registros
                        </div>
                        <div
                            class="text-lg font-bold text-emerald-800 dark:text-emerald-300"
                        >
                            {{ preview.total_new }}
                        </div>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'rounded-lg border p-2.5 text-left transition-colors',
                            statusFilter === 'updates'
                                ? 'border-amber-500 bg-amber-500/5 ring-1 ring-amber-500'
                                : 'bg-muted/30 hover:bg-muted/50',
                        ]"
                        @click="statusFilter = 'updates'"
                    >
                        <div
                            class="font-medium text-amber-700 dark:text-amber-400"
                        >
                            Atualizações (Upsert)
                        </div>
                        <div
                            class="text-lg font-bold text-amber-800 dark:text-amber-300"
                        >
                            {{ preview.total_updates }}
                        </div>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'rounded-lg border p-2.5 text-left transition-colors',
                            statusFilter === 'invalid'
                                ? 'border-rose-500 bg-rose-500/5 ring-1 ring-rose-500'
                                : 'bg-muted/30 hover:bg-muted/50',
                        ]"
                        @click="statusFilter = 'invalid'"
                    >
                        <div
                            class="font-medium text-rose-700 dark:text-rose-400"
                        >
                            Inválidos / Vazios
                        </div>
                        <div
                            class="text-lg font-bold text-rose-800 dark:text-rose-300"
                        >
                            {{ preview.total_empty_or_invalid }}
                        </div>
                    </button>
                </div>

                <!-- Warnings alert -->
                <div
                    v-if="preview.warnings && preview.warnings.length > 0"
                    class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300"
                >
                    <div class="mb-1 flex items-center gap-1.5 font-bold">
                        <AlertTriangle class="h-4 w-4" />
                        <span
                            >Avisos encontrados ({{
                                preview.warnings.length
                            }})</span
                        >
                    </div>
                    <ul class="list-inside list-disc space-y-0.5">
                        <li v-for="(warn, idx) in preview.warnings" :key="idx">
                            {{ warn }}
                        </li>
                    </ul>
                </div>

                <!-- Date Filter Strip (quando há múltiplas datas na importação) -->
                <div
                    v-if="uniqueDates.length > 1"
                    class="flex items-center justify-between rounded-lg border bg-muted/20 px-3 py-1.5 text-xs"
                >
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-muted-foreground"
                            >📅 Filtrar por Data da Medição:</span
                        >
                        <select
                            v-model="dateFilter"
                            class="h-7 rounded border border-input bg-background px-2 text-xs font-semibold"
                        >
                            <option value="all">
                                Todas as datas ({{ uniqueDates.length }} dias
                                encontrados)
                            </option>
                            <option
                                v-for="d in uniqueDates"
                                :key="d"
                                :value="d"
                            >
                                {{ formatOnlyDate(d) }}
                            </option>
                        </select>
                    </div>
                    <span class="text-[11px] text-muted-foreground">
                        Mostrando {{ filteredItems.length }} medições
                    </span>
                </div>

                <!-- Items Table -->
                <div
                    class="flex-1 overflow-auto rounded-md border border-border"
                >
                    <table class="w-full border-collapse text-left text-xs">
                        <thead
                            class="sticky top-0 z-10 bg-muted/80 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="border-b p-2">Aba / Célula</th>
                                <th class="border-b p-2">
                                    Sistema / Parâmetro
                                </th>
                                <th class="border-b p-2 text-right">
                                    Valor Lido
                                </th>
                                <th class="border-b p-2 text-center">Fator</th>
                                <th class="border-b p-2 text-right">
                                    Valor Final
                                </th>
                                <th class="border-b p-2">
                                    Data / Hora Medição
                                </th>
                                <th class="border-b p-2 text-center">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="(item, idx) in filteredItems"
                                :key="idx"
                                :class="[
                                    'transition-colors hover:bg-muted/20',
                                    item.status === 'empty_or_invalid'
                                        ? 'bg-rose-500/5 opacity-60'
                                        : '',
                                ]"
                            >
                                <td class="p-2 font-mono">
                                    <span class="font-bold">{{
                                        item.cell
                                    }}</span>
                                    <span class="ml-1 text-muted-foreground"
                                        >({{ item.sheet_name }})</span
                                    >
                                </td>
                                <td class="p-2">
                                    <div class="font-medium text-foreground">
                                        {{ item.parameter_name }}
                                    </div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{ item.system_name }}
                                        {{ item.unit ? `• ${item.unit}` : '' }}
                                    </div>
                                </td>
                                <td class="p-2 text-right font-mono">
                                    {{ item.raw_value ?? '-' }}
                                </td>
                                <td class="p-2 text-center font-mono">
                                    <span
                                        v-if="item.multiplier !== 1"
                                        class="rounded bg-amber-500/20 px-1 py-0.5 text-[10px] font-bold text-amber-700 dark:text-amber-300"
                                    >
                                        x{{ item.multiplier }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >-</span
                                    >
                                </td>
                                <td
                                    class="p-2 text-right font-mono font-bold text-foreground"
                                >
                                    <span v-if="item.final_value !== null">{{
                                        item.final_value
                                    }}</span>
                                    <span
                                        v-else-if="item.status === 'valid'"
                                        class="text-xs font-normal text-muted-foreground italic"
                                    >
                                        vazio (null)
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >-</span
                                    >
                                </td>
                                <td class="p-2 font-mono text-muted-foreground">
                                    {{ formatDateTime(item.measured_at) }}
                                </td>
                                <td class="p-2 text-center">
                                    <span
                                        v-if="
                                            item.status === 'empty_or_invalid'
                                        "
                                        class="inline-flex items-center rounded-full bg-rose-500/10 px-2 py-0.5 text-[10px] font-medium text-rose-600 dark:text-rose-400"
                                    >
                                        Ignorado
                                    </span>
                                    <span
                                        v-else-if="item.is_update"
                                        class="inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400"
                                    >
                                        Atualizar (antigo:
                                        {{ item.existing_value ?? 'vazio'
                                        }}{{
                                            item.final_value === null
                                                ? ' ➔ vazio'
                                                : ''
                                        }})
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400"
                                    >
                                        {{
                                            item.final_value === null
                                                ? 'Novo (vazio)'
                                                : 'Novo'
                                        }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="filteredItems.length === 0">
                                <td
                                    colspan="7"
                                    class="p-6 text-center text-muted-foreground"
                                >
                                    Nenhum registro correspondente ao filtro
                                    selecionado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <DialogFooter class="flex items-center pt-2 sm:justify-between">
                <Button
                    variant="outline"
                    type="button"
                    :disabled="isSubmitting"
                    @click="emit('update:open', false)"
                >
                    Cancelar
                </Button>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">
                        {{ validCount }} medições válidas prontas para importar
                    </span>
                    <Button
                        type="button"
                        :disabled="isSubmitting || validCount === 0"
                        @click="emit('confirm')"
                    >
                        <RefreshCw
                            v-if="isSubmitting"
                            class="mr-1.5 h-4 w-4 animate-spin"
                        />
                        <CheckCircle2 v-else class="mr-1.5 h-4 w-4" />
                        {{
                            isSubmitting
                                ? 'Salvando no banco...'
                                : 'Efetivar Importação'
                        }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
