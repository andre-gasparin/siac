<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Mail,
    RefreshCw,
    RotateCcw,
    Trash2,
    UploadCloud,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
import type { ImportQueueItem } from '@/features/spreadsheet-imports/types';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';

import {
    formatBrazilianDate,
    formatBrazilianDateTime,
} from '@/shared/lib/utils';

withDefaults(
    defineProps<{
        items: ImportQueueItem[];
        isRefreshing?: boolean;
        showTeamColumn?: boolean;
    }>(),
    {
        isRefreshing: false,
        showTeamColumn: false,
    },
);

const emit = defineEmits<{
    (e: 'retry', item: ImportQueueItem): void;
    (e: 'delete', item: ImportQueueItem): void;
    (e: 'refresh'): void;
}>();

const selectedErrorItem = ref<ImportQueueItem | null>(null);
</script>

<template>
    <div class="space-y-4">
        <!-- Header Controls -->
        <div class="flex items-center justify-between">
            <p class="text-xs text-muted-foreground">
                Itens na fila são processados sequencialmente a cada 1 minuto
                pelo Cron do sistema.
            </p>
            <Button
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 text-xs"
                :disabled="isRefreshing"
                @click="emit('refresh')"
            >
                <RefreshCw
                    :class="[
                        'h-3.5 w-3.5',
                        isRefreshing ? 'animate-spin text-primary' : '',
                    ]"
                />
                <span>Atualizar Fila</span>
            </Button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-lg border bg-card">
            <table class="w-full text-left text-xs">
                <thead
                    class="border-b bg-muted/50 text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Origem & Arquivo</th>
                        <th v-if="showTeamColumn" class="px-4 py-3">Empresa</th>
                        <th class="px-4 py-3">Modelo</th>
                        <th class="px-4 py-3">Data de Referência</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Enfileirado por</th>
                        <th class="px-4 py-3">Data de Envio</th>
                        <th class="w-28 px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="transition-colors hover:bg-muted/30"
                    >
                        <!-- Origin & File -->
                        <td class="px-4 py-3">
                            <div class="flex items-start gap-2.5">
                                <div
                                    :class="[
                                        'mt-0.5 rounded-md p-1.5',
                                        item.source_type === 'email'
                                            ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                            : 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
                                    ]"
                                >
                                    <Mail
                                        v-if="item.source_type === 'email'"
                                        class="h-4 w-4"
                                    />
                                    <UploadCloud v-else class="h-4 w-4" />
                                </div>
                                <div class="space-y-0.5">
                                    <p class="font-medium text-foreground">
                                        {{ item.file_name }}
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{
                                            item.source_type === 'email'
                                                ? 'Captura via E-mail'
                                                : 'Upload Manual'
                                        }}
                                        <span
                                            class="ml-1 text-[10px] text-muted-foreground/70"
                                            >#{{ item.id }}</span
                                        >
                                    </p>
                                </div>
                            </div>
                        </td>

                        <!-- Company / Team -->
                        <td v-if="showTeamColumn" class="px-4 py-3">
                            <span
                                class="inline-flex rounded bg-muted px-2 py-0.5 text-[11px] font-medium text-foreground"
                            >
                                {{ item.team?.name || '-' }}
                            </span>
                        </td>

                        <!-- Template -->
                        <td class="px-4 py-3">
                            <span class="font-medium text-foreground">
                                {{
                                    item.template?.name ??
                                    'Modelo #' + item.spreadsheet_template_id
                                }}
                            </span>
                        </td>

                        <!-- Reference Date -->
                        <td class="px-4 py-3">
                            <span
                                v-if="item.reference_date"
                                class="font-mono text-foreground"
                            >
                                {{ formatBrazilianDate(item.reference_date) }}
                            </span>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-3">
                            <div class="flex flex-col items-start gap-1">
                                <!-- Pending -->
                                <Badge
                                    v-if="item.status === 'pending'"
                                    variant="outline"
                                    class="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400"
                                >
                                    <Clock class="mr-1 h-3 w-3" />
                                    Pendente
                                </Badge>

                                <!-- Processing -->
                                <Badge
                                    v-else-if="item.status === 'processing'"
                                    variant="outline"
                                    class="border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-400"
                                >
                                    <RefreshCw
                                        class="mr-1 h-3 w-3 animate-spin"
                                    />
                                    Processando...
                                </Badge>

                                <!-- Completed -->
                                <Badge
                                    v-else-if="item.status === 'completed'"
                                    variant="outline"
                                    class="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="mr-1 h-3 w-3" />
                                    Concluído ({{ item.saved_values_count }}
                                    medições)
                                </Badge>

                                <!-- Failed -->
                                <div
                                    v-else-if="item.status === 'failed'"
                                    class="flex items-center gap-1.5"
                                >
                                    <Badge
                                        variant="outline"
                                        class="cursor-pointer border-destructive/30 bg-destructive/10 text-destructive hover:bg-destructive/20"
                                        @click="selectedErrorItem = item"
                                    >
                                        <XCircle class="mr-1 h-3 w-3" />
                                        Falha na Importação
                                    </Badge>
                                </div>
                            </div>
                        </td>

                        <!-- User -->
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ item.user?.name ?? 'Sistema (Cron)' }}
                        </td>

                        <!-- Created At -->
                        <td class="px-4 py-3 font-mono text-muted-foreground">
                            {{ formatBrazilianDateTime(item.created_at) }}
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="item.status === 'failed'"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 px-2 text-xs"
                                    title="Tentar Novamente"
                                    @click="emit('retry', item)"
                                >
                                    <RotateCcw class="mr-1 h-3 w-3" />
                                    Repetir
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    title="Remover item da fila"
                                    @click="emit('delete', item)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </Button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="items.length === 0">
                        <td
                            :colspan="showTeamColumn ? 8 : 7"
                            class="py-12 text-center text-muted-foreground"
                        >
                            <div
                                class="flex flex-col items-center justify-center gap-2"
                            >
                                <FileSpreadsheet
                                    class="h-8 w-8 text-muted-foreground/60"
                                />
                                <p class="text-sm font-medium">
                                    Nenhum item na fila de importação.
                                </p>
                                <p class="text-xs">
                                    Novos uploads ou arquivos confirmados de
                                    e-mail entrarão na fila para processamento.
                                </p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Error Detail Modal -->
        <Dialog
            :open="!!selectedErrorItem"
            @update:open="selectedErrorItem = null"
        >
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-destructive"
                    >
                        <AlertCircle class="h-5 w-5" />
                        Detalhes do Erro na Importação
                    </DialogTitle>
                    <DialogDescription>
                        {{ selectedErrorItem?.file_name }} (Item #{{
                            selectedErrorItem?.id
                        }})
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 py-2 text-xs">
                    <div
                        class="rounded-md bg-destructive/10 p-3 text-destructive"
                    >
                        <p class="font-semibold">Mensagem de Erro:</p>
                        <p
                            class="mt-1 font-mono text-[11px] whitespace-pre-wrap"
                        >
                            {{
                                selectedErrorItem?.error_message ||
                                'Erro desconhecido durante o processamento da planilha.'
                            }}
                        </p>
                    </div>

                    <div
                        v-if="selectedErrorItem?.error_trace"
                        class="space-y-1"
                    >
                        <p class="font-medium text-muted-foreground">
                            Rastreamento técnico (Stack trace):
                        </p>
                        <pre
                            class="max-h-40 overflow-y-auto rounded-md bg-muted p-2 font-mono text-[10px] text-foreground/80"
                            >{{ selectedErrorItem.error_trace }}</pre>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="selectedErrorItem = null"
                    >
                        Fechar
                    </Button>
                    <Button
                        size="sm"
                        @click="
                            if (selectedErrorItem) {
                                emit('retry', selectedErrorItem);
                                selectedErrorItem = null;
                            }
                        "
                    >
                        <RotateCcw class="mr-1.5 h-3.5 w-3.5" />
                        Tentar Novamente
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
