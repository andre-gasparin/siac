<script setup lang="ts">
import {
    AlertCircle,
    Download,
    Eye,
    FileSpreadsheet,
    RotateCcw,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ImportBatchDetailsDialog from '@/features/spreadsheet-imports/components/ImportBatchDetailsDialog.vue';
import type {
    PreviewResponse,
    SpreadsheetImportBatchItem,
} from '@/features/spreadsheet-imports/types';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';
import {
    formatBrazilianDate,
    formatBrazilianDateTime,
} from '@/shared/lib/utils';

const props = withDefaults(
    defineProps<{
        batches:
            | {
                  data: SpreadsheetImportBatchItem[];
                  current_page?: number;
                  last_page?: number;
                  total?: number;
                  links?: Array<{
                      url: string | null;
                      label: string;
                      active: boolean;
                  }>;
              }
            | SpreadsheetImportBatchItem[];
        isReverting: boolean;
        currentTeamSlug?: string;
        showTeamColumn?: boolean;
    }>(),
    {
        currentTeamSlug: '',
        showTeamColumn: false,
    },
);

const batchList = computed<SpreadsheetImportBatchItem[]>(() => {
    if (Array.isArray(props.batches)) {
        return props.batches;
    }

    return props.batches?.data ?? [];
});

const emit = defineEmits<{
    (e: 'revert', batchId: number): void;
}>();

const batchToRevert = ref<SpreadsheetImportBatchItem | null>(null);
const isConfirmOpen = ref(false);

// Details Dialog State
const isDetailsOpen = ref(false);
const selectedBatchForDetails = ref<SpreadsheetImportBatchItem | null>(null);
const detailsPreview = ref<PreviewResponse | null>(null);
const isLoadingDetails = ref(false);

function openRevertDialog(batch: SpreadsheetImportBatchItem) {
    batchToRevert.value = batch;
    isConfirmOpen.value = true;
}

function handleConfirmRevert() {
    if (batchToRevert.value) {
        emit('revert', batchToRevert.value.id);
        isConfirmOpen.value = false;
    }
}

function getTeamSlug(batch: SpreadsheetImportBatchItem): string {
    return batch.team?.slug || props.currentTeamSlug || 'innova';
}

function getDownloadUrl(batch: SpreadsheetImportBatchItem): string {
    return `/${getTeamSlug(batch)}/importacao-planilhas/lotes/${batch.id}/download`;
}

async function openDetailsDialog(batch: SpreadsheetImportBatchItem) {
    selectedBatchForDetails.value = batch;
    detailsPreview.value = null;
    isDetailsOpen.value = true;
    isLoadingDetails.value = true;

    try {
        const teamSlug = getTeamSlug(batch);
        const response = await fetch(
            `/${teamSlug}/importacao-planilhas/lotes/${batch.id}/itens`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
            },
        );

        const res = await response.json();

        if (res.success && res.preview) {
            detailsPreview.value = res.preview;
        } else {
            toast.error(res.message || 'Erro ao carregar os itens do lote.');
        }
    } catch {
        toast.error('Erro de conexão ao carregar itens do lote.');
    } finally {
        isLoadingDetails.value = false;
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-lg border bg-card">
            <table class="w-full border-collapse text-left text-xs">
                <thead
                    class="bg-muted/50 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="border-b p-3">Data / Hora Importação</th>
                        <th v-if="showTeamColumn" class="border-b p-3">
                            Empresa
                        </th>
                        <th class="border-b p-3">Arquivo</th>
                        <th class="border-b p-3">Modelo Utilizado</th>
                        <th class="border-b p-3">Operador</th>
                        <th class="border-b p-3 text-center">Data Ref.</th>
                        <th class="border-b p-3 text-center">Medições</th>
                        <th class="border-b p-3 text-center">Status</th>
                        <th class="border-b p-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="batch in batchList"
                        :key="batch.id"
                        class="transition-colors hover:bg-muted/20"
                    >
                        <td class="p-3 font-mono text-muted-foreground">
                            {{ formatBrazilianDateTime(batch.created_at) }}
                        </td>
                        <td v-if="showTeamColumn" class="p-3">
                            <span
                                class="inline-flex rounded bg-muted px-2 py-0.5 text-[11px] font-medium text-foreground"
                            >
                                {{ batch.team?.name || '-' }}
                            </span>
                        </td>
                        <td class="p-3 font-medium text-foreground">
                            <div class="flex items-center gap-1.5">
                                <a
                                    v-if="batch.file_path"
                                    :href="getDownloadUrl(batch)"
                                    download
                                    class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                    :title="`Clique para baixar: ${batch.file_name}`"
                                >
                                    <FileSpreadsheet
                                        class="h-3.5 w-3.5 text-primary/80"
                                    />
                                    <span>{{ batch.file_name }}</span>
                                    <Download
                                        class="h-3 w-3 text-muted-foreground hover:text-primary"
                                    />
                                </a>
                                <span
                                    v-else
                                    class="text-foreground"
                                    :title="'Arquivo físico original não disponível'"
                                >
                                    {{ batch.file_name }}
                                </span>
                            </div>
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ batch.template?.name || 'Modelo removido' }}
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ batch.user?.name || '-' }}
                        </td>
                        <td
                            class="p-3 text-center font-mono text-muted-foreground"
                        >
                            {{ formatBrazilianDate(batch.reference_date) }}
                        </td>
                        <td
                            class="p-3 text-center font-mono font-bold text-foreground"
                        >
                            {{ batch.saved_values_count }}
                        </td>
                        <td class="p-3 text-center">
                            <span
                                v-if="batch.status === 'completed'"
                                class="inline-flex items-center rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400"
                            >
                                Concluído
                            </span>
                            <span
                                v-else-if="batch.status === 'reverted'"
                                class="inline-flex items-center rounded-full bg-zinc-500/15 px-2.5 py-0.5 text-[10px] font-medium text-zinc-600 dark:text-zinc-400"
                                :title="`Revertido por ${batch.reverted_by?.name ?? ''} em ${formatBrazilianDateTime(batch.reverted_at)}`"
                            >
                                Revertido
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full bg-rose-500/15 px-2.5 py-0.5 text-[10px] font-medium text-rose-700 dark:text-rose-400"
                            >
                                Falhou
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-7 px-2 text-xs"
                                    title="Visualizar medições importadas"
                                    @click="openDetailsDialog(batch)"
                                >
                                    <Eye class="mr-1 h-3 w-3" />
                                    Ver Dados
                                </Button>
                                <Button
                                    v-if="batch.status === 'completed'"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 text-xs text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                    :disabled="isReverting"
                                    @click="openRevertDialog(batch)"
                                >
                                    <RotateCcw class="mr-1 h-3 w-3" />
                                    Reverter
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="batchList.length === 0">
                        <td
                            :colspan="showTeamColumn ? 9 : 8"
                            class="p-8 text-center text-muted-foreground"
                        >
                            Nenhum histórico de importação encontrado para esta
                            seleção.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Details Dialog -->
        <ImportBatchDetailsDialog
            :open="isDetailsOpen"
            :file-name="selectedBatchForDetails?.file_name || ''"
            :file-path="selectedBatchForDetails?.file_path"
            :download-url="
                selectedBatchForDetails?.file_path
                    ? getDownloadUrl(selectedBatchForDetails)
                    : null
            "
            :preview="detailsPreview"
            :is-loading="isLoadingDetails"
            @update:open="isDetailsOpen = $event"
        />

        <!-- Revert Confirmation Dialog -->
        <Dialog :open="isConfirmOpen" @update:open="isConfirmOpen = $event">
            <DialogContent class="sm:max-w-[440px]">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-destructive"
                    >
                        <AlertCircle class="h-5 w-5" />
                        <span>Reverter Lote de Importação?</span>
                    </DialogTitle>
                    <DialogDescription>
                        Esta ação irá desfazer todas as
                        <strong>{{ batchToRevert?.saved_values_count }}</strong>
                        medições importadas a partir do arquivo
                        <strong class="font-mono text-foreground">{{
                            batchToRevert?.file_name
                        }}</strong
                        >. Registros atualizados voltarão ao seu valor anterior
                        e novos registros criados por este lote serão removidos.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        size="sm"
                        type="button"
                        @click="isConfirmOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        type="button"
                        :disabled="isReverting"
                        @click="handleConfirmRevert"
                    >
                        Confirmar Reversão
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
