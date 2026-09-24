<script setup lang="ts">
import {
    AlertCircle,
    Calendar,
    Check,
    Eye,
    FileSpreadsheet,
    Mail,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type {
    PendingConfirmationItem,
    SpreadsheetTemplateItem,
} from '@/features/spreadsheet-imports/types';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';

const props = withDefaults(
    defineProps<{
        items: PendingConfirmationItem[];
        templates: SpreadsheetTemplateItem[];
        isProcessing?: boolean;
        showTeamColumn?: boolean;
    }>(),
    {
        isProcessing: false,
        showTeamColumn: false,
    },
);

const emit = defineEmits<{
    (e: 'preview', item: PendingConfirmationItem): void;
    (
        e: 'enqueue',
        item: PendingConfirmationItem,
        templateId: number,
        date: string | null,
    ): void;
    (e: 'discard', item: PendingConfirmationItem): void;
    (e: 'bulkEnqueue', ids: number[]): void;
    (
        e: 'updateItem',
        item: PendingConfirmationItem,
        templateId: number,
        date: string | null,
    ): void;
}>();

const selectedIds = ref<number[]>([]);
const localTemplates = ref<Record<number, number>>({});
const localDates = ref<Record<number, string>>({});

// Initialize local edits
props.items.forEach((item) => {
    localTemplates.value[item.id] = item.spreadsheet_template_id;
    localDates.value[item.id] = item.extracted_reference_date
        ? item.extracted_reference_date.substring(0, 10)
        : '';
});

const isAllSelected = computed(
    () =>
        props.items.length > 0 &&
        props.items.every((item) => selectedIds.value.includes(item.id)),
);

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.items.map((i) => i.id);
    }
}

function toggleSelect(id: number) {
    const idx = selectedIds.value.indexOf(id);

    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

function handleSaveRow(item: PendingConfirmationItem) {
    emit(
        'updateItem',
        item,
        localTemplates.value[item.id],
        localDates.value[item.id] || null,
    );
}

function handleEnqueueSingle(item: PendingConfirmationItem) {
    emit(
        'enqueue',
        item,
        localTemplates.value[item.id],
        localDates.value[item.id] || null,
    );
}

function handleBulkEnqueue() {
    if (selectedIds.value.length === 0) {
        return;
    }

    emit('bulkEnqueue', selectedIds.value);
}

function formatBytes(bytes: number, decimals = 1) {
    if (bytes === 0) {
        return '0 Bytes';
    }

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function formatDate(dateStr?: string | null) {
    if (!dateStr) {
        return '-';
    }

    try {
        const d = new Date(dateStr);

        return d.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
}
</script>

<template>
    <div class="space-y-4">
        <!-- Bulk Action Header -->
        <div
            v-if="selectedIds.length > 0"
            class="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm"
        >
            <div class="flex items-center gap-2 font-medium text-foreground">
                <Check class="h-4 w-4 text-primary" />
                <span>{{ selectedIds.length }} planilha(s) selecionada(s)</span>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    size="sm"
                    :disabled="isProcessing"
                    @click="handleBulkEnqueue"
                >
                    <Send class="mr-1.5 h-3.5 w-3.5" />
                    Enviar Selecionados para Fila
                </Button>
                <Button variant="ghost" size="sm" @click="selectedIds = []">
                    Cancelar
                </Button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-lg border bg-card">
            <table class="w-full text-left text-xs">
                <thead
                    class="border-b bg-muted/50 text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input
                                type="checkbox"
                                :checked="isAllSelected"
                                class="rounded border-input text-primary focus:ring-primary"
                                @change="toggleSelectAll"
                            />
                        </th>
                        <th class="px-4 py-3">Planilha & Origem (E-mail)</th>
                        <th v-if="showTeamColumn" class="px-4 py-3">Empresa</th>
                        <th class="w-56 px-4 py-3">Modelo de Mapeamento</th>
                        <th class="w-44 px-4 py-3">Data de Referência</th>
                        <th class="px-4 py-3">Regra</th>
                        <th class="w-40 px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        :class="[
                            'transition-colors hover:bg-muted/30',
                            selectedIds.includes(item.id) ? 'bg-primary/5' : '',
                        ]"
                    >
                        <!-- Checkbox -->
                        <td class="px-4 py-3">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(item.id)"
                                class="rounded border-input text-primary focus:ring-primary"
                                @change="toggleSelect(item.id)"
                            />
                        </td>

                        <!-- File & Email Details -->
                        <td class="px-4 py-3">
                            <div class="flex items-start gap-2.5">
                                <div
                                    class="mt-0.5 rounded-md bg-emerald-500/10 p-1.5 text-emerald-600 dark:text-emerald-400"
                                >
                                    <FileSpreadsheet class="h-4 w-4" />
                                </div>
                                <div class="space-y-0.5">
                                    <p class="font-medium text-foreground">
                                        {{ item.file_name }}
                                    </p>
                                    <div
                                        class="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground"
                                    >
                                        <span>{{
                                            formatBytes(item.file_size_bytes)
                                        }}</span>
                                        <span>•</span>
                                        <span class="flex items-center gap-1">
                                            <Mail class="h-3 w-3" />
                                            {{
                                                item.sender_name
                                                    ? `${item.sender_name} <${item.sender_email}>`
                                                    : item.sender_email
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Assunto:
                                        <span class="text-foreground">{{
                                            item.subject
                                        }}</span>
                                        <span class="ml-2"
                                            >({{
                                                formatDate(
                                                    item.email_received_at,
                                                )
                                            }})</span
                                        >
                                    </p>
                                </div>
                            </div>
                        </td>

                        <!-- Company Column -->
                        <td v-if="showTeamColumn" class="px-4 py-3">
                            <span
                                class="inline-flex rounded bg-muted px-2 py-0.5 text-[11px] font-medium text-foreground"
                            >
                                {{ item.team?.name || '-' }}
                            </span>
                        </td>

                        <!-- Template Selector -->
                        <td class="px-4 py-3">
                            <div class="space-y-1">
                                <select
                                    v-model="localTemplates[item.id]"
                                    class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                                    @change="handleSaveRow(item)"
                                >
                                    <option
                                        v-for="t in templates"
                                        :key="t.id"
                                        :value="t.id"
                                    >
                                        {{
                                            t.team?.name
                                                ? `[${t.team.name}] `
                                                : ''
                                        }}{{ t.name }}
                                    </option>
                                </select>
                            </div>
                        </td>

                        <!-- Reference Date -->
                        <td class="px-4 py-3">
                            <div class="space-y-1">
                                <div class="relative">
                                    <Input
                                        v-model="localDates[item.id]"
                                        type="date"
                                        class="h-8 pr-7 text-xs"
                                        @change="handleSaveRow(item)"
                                    />
                                    <Calendar
                                        class="pointer-events-none absolute top-2.5 right-2 h-3.5 w-3.5 text-muted-foreground"
                                    />
                                </div>
                                <span
                                    v-if="item.extracted_reference_date"
                                    class="block text-[10px] text-emerald-600 dark:text-emerald-400"
                                >
                                    Detectada automaticamente
                                </span>
                            </div>
                        </td>

                        <!-- Rule Badge -->
                        <td class="px-4 py-3">
                            <Badge
                                v-if="item.rule"
                                variant="outline"
                                class="text-[10px] font-normal"
                            >
                                {{ item.rule.name }}
                            </Badge>
                            <span
                                v-else
                                class="text-[11px] text-muted-foreground"
                            >
                                -
                            </span>
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-2 text-xs"
                                    title="Prévia da Planilha"
                                    @click="emit('preview', item)"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    size="sm"
                                    class="h-8 px-2 text-xs"
                                    title="Enviar para Fila de Importação"
                                    :disabled="isProcessing"
                                    @click="handleEnqueueSingle(item)"
                                >
                                    <Send class="mr-1 h-3.5 w-3.5" />
                                    Enviar
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    title="Descartar arquivo"
                                    @click="emit('discard', item)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="items.length === 0">
                        <td
                            :colspan="showTeamColumn ? 7 : 6"
                            class="py-12 text-center text-muted-foreground"
                        >
                            <div
                                class="flex flex-col items-center justify-center gap-2"
                            >
                                <AlertCircle
                                    class="h-8 w-8 text-muted-foreground/60"
                                />
                                <p class="text-sm font-medium">
                                    Nenhum arquivo aguardando confirmação.
                                </p>
                                <p class="text-xs">
                                    Novos arquivos capturados via e-mail (Cron)
                                    aparecerão aqui automaticamente.
                                </p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
