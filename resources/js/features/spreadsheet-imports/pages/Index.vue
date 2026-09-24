<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    History,
    Inbox,
    Layers,
    Mail,
    RefreshCw,
    Sliders,
    UploadCloud,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ImportHistoryTable from '@/features/spreadsheet-imports/components/ImportHistoryTable.vue';
import ImportPreviewDialog from '@/features/spreadsheet-imports/components/ImportPreviewDialog.vue';
import ImportQueueTable from '@/features/spreadsheet-imports/components/ImportQueueTable.vue';
import PendingConfirmationsTable from '@/features/spreadsheet-imports/components/PendingConfirmationsTable.vue';
import type {
    ImportQueueItem,
    PendingConfirmationItem,
    PreviewResponse,
    SpreadsheetImportBatchItem,
    SpreadsheetTemplateItem,
} from '@/features/spreadsheet-imports/types';
import {
    enqueueManual as spreadsheetImportsEnqueueManual,
    execute as spreadsheetImportsExecute,
    index as spreadsheetImportsIndex,
    preview as spreadsheetImportsPreview,
    revert as spreadsheetImportsRevert,
} from '@/routes/spreadsheet-imports';
import {
    bulkEnqueue as confirmationsBulkEnqueue,
    destroy as confirmationsDestroy,
    enqueue as confirmationsEnqueue,
    preview as confirmationsPreview,
    update as confirmationsUpdate,
} from '@/routes/spreadsheet-imports/confirmations';
import {
    destroy as queueDestroy,
    retry as queueRetry,
} from '@/routes/spreadsheet-imports/queue';
import { index as rulesIndex } from '@/routes/spreadsheet-imports/rules';
import { index as templatesIndex } from '@/routes/spreadsheet-imports/templates';
import { Button } from '@/shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/shared/components/ui/card';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import type { Team } from '@/shared/types';

const props = defineProps<{
    templates: SpreadsheetTemplateItem[];
    batches: {
        data: SpreadsheetImportBatchItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    pendingConfirmations: {
        data: PendingConfirmationItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    queueItems: {
        data: ImportQueueItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    pendingCount: number;
    queueActiveCount: number;
    currentTeam: Team;
    availableTeams?: Array<{ id: number; name: string; slug: string }>;
    selectedTeamId?: string;
    defaultReferenceDate: string;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Importação de Planilhas',
            href: props.currentTeam
                ? spreadsheetImportsIndex.url({
                      current_team: props.currentTeam.slug,
                  })
                : '/',
        },
    ],
});

const activeTab = ref<'upload' | 'pending' | 'queue' | 'history'>('upload');

// Company Filter State
const selectedTeamFilter = ref<string>(props.selectedTeamId || 'all');

const isGlobalView = computed(() => {
    return (
        (props.availableTeams?.length ?? 0) > 1 &&
        selectedTeamFilter.value === 'all'
    );
});

const currentEffectiveTeam = computed(() => {
    if (selectedTeamFilter.value && selectedTeamFilter.value !== 'all') {
        const found = props.availableTeams?.find(
            (t) => String(t.id) === String(selectedTeamFilter.value),
        );

        if (found) {
            return found;
        }
    }

    return props.currentTeam;
});

function onTeamFilterChange(newTeamId: string) {
    selectedTeamFilter.value = newTeamId;
    router.get(
        spreadsheetImportsIndex.url({
            current_team: props.currentTeam.slug,
        }),
        {
            filter_team_id: newTeamId,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

// Upload Form State
const selectedTemplateId = ref<number>(props.templates[0]?.id ?? 0);
const referenceDate = ref<string>(props.defaultReferenceDate);
const dateScope = ref<'single' | 'all'>('single');
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const isGeneratingPreview = ref(false);
const isExecutingImport = ref(false);
const isEnqueuingManual = ref(false);
const isReverting = ref(false);
const isRefreshingQueue = ref(false);
const isProcessingConfirmation = ref(false);

// Preview Modal State
const isPreviewOpen = ref(false);
const previewData = ref<PreviewResponse | null>(null);
const previewFileName = ref<string>('');
const previewFilePath = ref<string | null>(null);

const selectedTemplate = computed(() =>
    props.templates.find((t) => t.id === selectedTemplateId.value),
);

// Drag & drop state
const isDragging = ref(false);

function onFileSelect(e: Event) {
    const target = e.target as HTMLInputElement;

    if (target.files?.[0]) {
        selectedFile.value = target.files[0];
    }
}

function onDropFile(e: DragEvent) {
    isDragging.value = false;

    if (e.dataTransfer?.files?.[0]) {
        selectedFile.value = e.dataTransfer.files[0];
    }
}

async function handleGeneratePreview() {
    if (!selectedTemplateId.value) {
        toast.error('Selecione um modelo de planilha.');

        return;
    }

    if (!selectedFile.value) {
        toast.error('Selecione o arquivo da planilha para enviar.');

        return;
    }

    isGeneratingPreview.value = true;
    const formData = new FormData();
    formData.append(
        'spreadsheet_template_id',
        String(selectedTemplateId.value),
    );
    formData.append('file', selectedFile.value);

    if (referenceDate.value) {
        formData.append('reference_date', referenceDate.value);
    }

    formData.append('date_scope', dateScope.value);

    try {
        const response = await fetch(
            spreadsheetImportsPreview.url({
                current_team: props.currentTeam.slug,
            }),
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: formData,
            },
        );

        const res = await response.json();

        if (res.success && res.preview) {
            previewData.value = res.preview;
            previewFileName.value = res.file_name || selectedFile.value.name;
            previewFilePath.value = res.file_path || null;
            isPreviewOpen.value = true;
        } else {
            toast.error(res.message || 'Erro ao processar prévia da planilha.');
        }
    } catch {
        toast.error('Erro de conexão ao processar arquivo.');
    } finally {
        isGeneratingPreview.value = false;
    }
}

async function handleEnqueueManual() {
    if (!selectedTemplateId.value) {
        toast.error('Selecione um modelo de planilha.');

        return;
    }

    if (!selectedFile.value) {
        toast.error('Selecione o arquivo da planilha para enviar.');

        return;
    }

    isEnqueuingManual.value = true;
    const formData = new FormData();
    formData.append(
        'spreadsheet_template_id',
        String(selectedTemplateId.value),
    );
    formData.append('file', selectedFile.value);

    if (referenceDate.value) {
        formData.append('reference_date', referenceDate.value);
    }

    formData.append('date_scope', dateScope.value);

    try {
        const response = await fetch(
            spreadsheetImportsEnqueueManual.url({
                current_team: props.currentTeam.slug,
            }),
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: formData,
            },
        );

        const res = await response.json();

        if (res.success) {
            toast.success(
                res.message || 'Arquivo enviado para a fila com sucesso!',
            );
            selectedFile.value = null;

            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }

            activeTab.value = 'queue';
            router.reload({ only: ['queueItems', 'queueActiveCount'] });
        } else {
            toast.error(res.message || 'Erro ao enviar arquivo para a fila.');
        }
    } catch {
        toast.error('Erro de conexão ao enviar para a fila.');
    } finally {
        isEnqueuingManual.value = false;
    }
}

async function handleConfirmImport() {
    if (!previewData.value || !selectedTemplateId.value) {
        return;
    }

    isExecutingImport.value = true;

    try {
        const response = await fetch(
            spreadsheetImportsExecute.url({
                current_team: props.currentTeam.slug,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    spreadsheet_template_id: selectedTemplateId.value,
                    file_name: previewFileName.value,
                    file_path: previewFilePath.value,
                    reference_date: referenceDate.value || null,
                    items: previewData.value.items,
                }),
            },
        );

        const res = await response.json();

        if (res.success) {
            toast.success(res.message || 'Dados importados com sucesso!');
            isPreviewOpen.value = false;
            selectedFile.value = null;

            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }

            activeTab.value = 'history';
            router.reload({ only: ['batches'] });
        } else {
            toast.error(res.message || 'Erro ao efetivar importação.');
        }
    } catch {
        toast.error('Falha de conexão ao salvar medições.');
    } finally {
        isExecutingImport.value = false;
    }
}

// Pending Confirmation Handlers
async function handlePendingPreview(item: PendingConfirmationItem) {
    isProcessingConfirmation.value = true;

    try {
        const url = new URL(
            confirmationsPreview.url({
                current_team: props.currentTeam.slug,
                item: item.id,
            }),
            window.location.origin,
        );

        if (item.extracted_reference_date) {
            url.searchParams.set(
                'reference_date',
                item.extracted_reference_date.substring(0, 10),
            );
        }

        const response = await fetch(url.toString(), {
            headers: {
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
        });

        const res = await response.json();

        if (res.success && res.preview) {
            previewData.value = res.preview;
            previewFileName.value = res.file_name;
            selectedTemplateId.value = item.spreadsheet_template_id;
            referenceDate.value = item.extracted_reference_date
                ? item.extracted_reference_date.substring(0, 10)
                : '';
            isPreviewOpen.value = true;
        } else {
            toast.error(res.message || 'Erro ao obter prévia do arquivo.');
        }
    } catch {
        toast.error('Erro de conexão ao gerar prévia do anexo.');
    } finally {
        isProcessingConfirmation.value = false;
    }
}

async function handlePendingUpdate(
    item: PendingConfirmationItem,
    templateId: number,
    date: string | null,
) {
    try {
        const response = await fetch(
            confirmationsUpdate.url({
                current_team: props.currentTeam.slug,
                item: item.id,
            }),
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    spreadsheet_template_id: templateId,
                    reference_date: date,
                }),
            },
        );

        const res = await response.json();

        if (res.success) {
            toast.success('Alterações salvas.');
        } else {
            toast.error(res.message || 'Erro ao salvar alterações.');
        }
    } catch {
        toast.error('Erro de conexão ao salvar alterações.');
    }
}

async function handlePendingEnqueue(
    item: PendingConfirmationItem,
    templateId: number,
    date: string | null,
) {
    isProcessingConfirmation.value = true;

    try {
        const response = await fetch(
            confirmationsEnqueue.url({
                current_team: props.currentTeam.slug,
                item: item.id,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    spreadsheet_template_id: templateId,
                    reference_date: date,
                }),
            },
        );

        const res = await response.json();

        if (res.success) {
            toast.success(res.message || 'Arquivo enviado para a fila!');
            router.reload({
                only: [
                    'pendingConfirmations',
                    'pendingCount',
                    'queueItems',
                    'queueActiveCount',
                ],
            });
        } else {
            toast.error(res.message || 'Erro ao enfileirar arquivo.');
        }
    } catch {
        toast.error('Erro de conexão ao enviar para a fila.');
    } finally {
        isProcessingConfirmation.value = false;
    }
}

async function handlePendingBulkEnqueue(ids: number[]) {
    isProcessingConfirmation.value = true;

    try {
        const response = await fetch(
            confirmationsBulkEnqueue.url({
                current_team: props.currentTeam.slug,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({ ids }),
            },
        );

        const res = await response.json();

        if (res.success) {
            toast.success(res.message || 'Arquivos enviados para a fila!');
            router.reload({
                only: [
                    'pendingConfirmations',
                    'pendingCount',
                    'queueItems',
                    'queueActiveCount',
                ],
            });
        } else {
            toast.error(res.message || 'Erro ao processar envio em lote.');
        }
    } catch {
        toast.error('Erro de conexão ao processar envio em lote.');
    } finally {
        isProcessingConfirmation.value = false;
    }
}

async function handlePendingDiscard(item: PendingConfirmationItem) {
    if (!confirm(`Deseja realmente descartar o arquivo "${item.file_name}"?`)) {
        return;
    }

    try {
        const response = await fetch(
            confirmationsDestroy.url({
                current_team: props.currentTeam.slug,
                item: item.id,
            }),
            {
                method: 'DELETE',
                headers: {
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

        if (res.success) {
            toast.success('Arquivo descartado.');
            router.reload({
                only: ['pendingConfirmations', 'pendingCount'],
            });
        } else {
            toast.error(res.message || 'Erro ao descartar arquivo.');
        }
    } catch {
        toast.error('Erro de conexão ao descartar.');
    }
}

// Queue Handlers
async function handleQueueRetry(item: ImportQueueItem) {
    try {
        const response = await fetch(
            queueRetry.url({
                current_team: props.currentTeam.slug,
                queue: item.id,
            }),
            {
                method: 'POST',
                headers: {
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

        if (res.success) {
            toast.success(res.message || 'Item reenviado para a fila!');
            router.reload({
                only: ['queueItems', 'queueActiveCount'],
            });
        } else {
            toast.error(res.message || 'Erro ao reenviar item.');
        }
    } catch {
        toast.error('Erro de conexão.');
    }
}

async function handleQueueDelete(item: ImportQueueItem) {
    if (!confirm(`Remover o item #${item.id} da fila?`)) {
        return;
    }

    try {
        const response = await fetch(
            queueDestroy.url({
                current_team: props.currentTeam.slug,
                queue: item.id,
            }),
            {
                method: 'DELETE',
                headers: {
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

        if (res.success) {
            toast.success('Item removido da fila.');
            router.reload({
                only: ['queueItems', 'queueActiveCount'],
            });
        } else {
            toast.error(res.message || 'Erro ao remover da fila.');
        }
    } catch {
        toast.error('Erro de conexão.');
    }
}

function handleRefreshQueue() {
    isRefreshingQueue.value = true;
    router.reload({
        only: ['queueItems', 'queueActiveCount'],
        onFinish: () => {
            isRefreshingQueue.value = false;
        },
    });
}

// Revert Batch Handler
async function handleRevertBatch(batchId: number) {
    isReverting.value = true;

    try {
        const response = await fetch(
            spreadsheetImportsRevert.url({
                current_team: props.currentTeam.slug,
                batch: batchId,
            }),
            {
                method: 'POST',
                headers: {
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

        if (res.success) {
            toast.success(res.message || 'Lote revertido com sucesso!');
            router.reload({ only: ['batches'] });
        } else {
            toast.error(res.message || 'Erro ao reverter lote.');
        }
    } catch {
        toast.error('Erro de conexão ao reverter lote.');
    } finally {
        isReverting.value = false;
    }
}
</script>

<template>
    <Head title="Importação de Planilhas Excel" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 border-b pb-5 sm:flex-row sm:items-center"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    <FileSpreadsheet class="h-7 w-7 text-primary" />
                    Importação de Planilhas
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Envie planilhas Excel (.xlsx, .xls, .csv) ou capture
                    automaticamente via e-mail para importar medições em lote.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Multi-company filter -->
                <div
                    v-if="availableTeams && availableTeams.length > 1"
                    class="flex items-center gap-2 rounded-lg border bg-muted/30 px-3 py-1.5"
                >
                    <span
                        class="text-xs font-semibold whitespace-nowrap text-muted-foreground"
                    >
                        Empresa:
                    </span>
                    <select
                        :value="selectedTeamFilter"
                        class="h-8 rounded-md border border-input bg-background px-2.5 text-xs font-semibold shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                        @change="
                            onTeamFilterChange(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="all">
                            🏢 Todas as Empresas (Geral)
                        </option>
                        <option
                            v-for="team in availableTeams"
                            :key="team.id"
                            :value="String(team.id)"
                        >
                            {{ team.name }}
                        </option>
                    </select>
                </div>

                <Button variant="outline" as-child>
                    <Link
                        :href="
                            rulesIndex.url({
                                current_team: currentEffectiveTeam.slug,
                            })
                        "
                    >
                        <Mail class="mr-2 h-4 w-4" />
                        Regras de E-mail
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            templatesIndex.url({
                                current_team: currentEffectiveTeam.slug,
                            })
                        "
                    >
                        <Sliders class="mr-2 h-4 w-4" />
                        Gerenciar Modelos
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b">
            <!-- Tab 1: Upload e Importação -->
            <button
                type="button"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                    activeTab === 'upload'
                        ? 'border-primary font-semibold text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
                @click="activeTab = 'upload'"
            >
                <UploadCloud class="h-4 w-4" />
                <span>Upload Manual</span>
            </button>

            <!-- Tab 2: Aguardando Confirmação -->
            <button
                type="button"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                    activeTab === 'pending'
                        ? 'border-primary font-semibold text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
                @click="activeTab = 'pending'"
            >
                <Inbox class="h-4 w-4" />
                <span>Aguardando Confirmação</span>
                <span
                    v-if="pendingCount > 0"
                    class="rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold text-white dark:bg-amber-600"
                >
                    {{ pendingCount }}
                </span>
            </button>

            <!-- Tab 3: Fila de Processamento -->
            <button
                type="button"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                    activeTab === 'queue'
                        ? 'border-primary font-semibold text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
                @click="activeTab = 'queue'"
            >
                <Layers class="h-4 w-4" />
                <span>Fila de Processamento</span>
                <span
                    v-if="queueActiveCount > 0"
                    class="rounded-full bg-blue-500 px-2 py-0.5 text-xs font-semibold text-white dark:bg-blue-600"
                >
                    {{ queueActiveCount }}
                </span>
            </button>

            <!-- Tab 4: Histórico de Lotes & Auditoria -->
            <button
                type="button"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                    activeTab === 'history'
                        ? 'border-primary font-semibold text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
                @click="activeTab = 'history'"
            >
                <History class="h-4 w-4" />
                <span>Histórico & Auditoria</span>
                <span
                    v-if="batches.total > 0"
                    class="rounded-full bg-muted px-2 py-0.5 text-xs"
                >
                    {{ batches.total }}
                </span>
            </button>
        </div>

        <!-- Tab 1: Upload Content -->
        <div v-if="activeTab === 'upload'" class="grid gap-6 md:grid-cols-3">
            <!-- Left: Settings Card -->
            <Card class="md:col-span-1">
                <CardHeader>
                    <CardTitle class="text-base"
                        >1. Configurar Importação</CardTitle
                    >
                    <CardDescription class="text-xs">
                        Escolha o modelo correspondente ao arquivo que será
                        enviado.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-xs">
                    <!-- Template Selector -->
                    <div class="space-y-1.5">
                        <Label for="template-select"
                            >Modelo de Mapeamento *</Label
                        >
                        <select
                            id="template-select"
                            v-model="selectedTemplateId"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option
                                v-for="t in templates"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.team?.name ? `[${t.team.name}] ` : ''
                                }}{{ t.name }}
                            </option>
                        </select>
                        <p
                            v-if="selectedTemplate?.description"
                            class="text-[11px] text-muted-foreground"
                        >
                            {{ selectedTemplate.description }}
                        </p>
                    </div>

                    <!-- Reference Date & Scope -->
                    <div class="space-y-3 border-t pt-2">
                        <div class="space-y-1.5">
                            <Label
                                for="reference-date"
                                class="flex items-center gap-1.5"
                            >
                                <Calendar
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                                <span>Data de Referência</span>
                            </Label>
                            <Input
                                id="reference-date"
                                v-model="referenceDate"
                                type="date"
                                class="h-9 text-xs"
                            />
                        </div>

                        <!-- Escopo de Datas para Séries / Tabelas -->
                        <div
                            class="space-y-1.5 rounded-lg border bg-muted/30 p-2.5"
                        >
                            <Label
                                class="text-xs font-semibold text-foreground"
                            >
                                Escopo da Importação (Séries/Tabelas)
                            </Label>
                            <div class="grid grid-cols-2 gap-1.5 text-xs">
                                <label
                                    :class="[
                                        'flex cursor-pointer items-center justify-center gap-1.5 rounded-md border p-2 text-center font-medium transition-colors',
                                        dateScope === 'single'
                                            ? 'border-primary bg-primary/10 font-bold text-primary'
                                            : 'border-border bg-background text-muted-foreground hover:bg-muted/50',
                                    ]"
                                >
                                    <input
                                        v-model="dateScope"
                                        type="radio"
                                        value="single"
                                        class="sr-only"
                                    />
                                    <span>📅 Apenas esta data</span>
                                </label>

                                <label
                                    :class="[
                                        'flex cursor-pointer items-center justify-center gap-1.5 rounded-md border p-2 text-center font-medium transition-colors',
                                        dateScope === 'all'
                                            ? 'border-primary bg-primary/10 font-bold text-primary'
                                            : 'border-border bg-background text-muted-foreground hover:bg-muted/50',
                                    ]"
                                >
                                    <input
                                        v-model="dateScope"
                                        type="radio"
                                        value="all"
                                        class="sr-only"
                                    />
                                    <span>🗓️ Todas as datas</span>
                                </label>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                {{
                                    dateScope === 'single'
                                        ? 'Localiza apenas a coluna ou linha correspondente ao dia de referência selecionado.'
                                        : 'Varre toda a planilha importando as medições de todas as datas válidas encontradas.'
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="templates.length === 0"
                        class="rounded-lg bg-amber-500/10 p-3 text-amber-800 dark:text-amber-300"
                    >
                        Nenhum modelo ativo encontrado.
                        <Link
                            :href="
                                templatesIndex.url({
                                    current_team: currentTeam.slug,
                                })
                            "
                            class="font-semibold underline"
                            >Crie um modelo</Link
                        >
                        antes de importar.
                    </div>
                </CardContent>
            </Card>

            <!-- Right: Dropzone & Action Card -->
            <Card class="flex flex-col justify-between md:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base"
                        >2. Selecionar Arquivo da Planilha</CardTitle
                    >
                    <CardDescription class="text-xs">
                        Suporte a arquivos no formato Excel (.xlsx, .xls) e CSV
                        (.csv).
                    </CardDescription>
                </CardHeader>
                <CardContent class="relative space-y-4">
                    <!-- Loading Overlay during Preview Generation -->
                    <div
                        v-if="isGeneratingPreview"
                        class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 rounded-lg bg-background/85 p-6 text-center backdrop-blur-xs"
                    >
                        <RefreshCw
                            class="h-10 w-10 animate-spin text-primary"
                        />
                        <div>
                            <p class="text-sm font-semibold text-foreground">
                                Lendo e processando planilha...
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Calculando valores de fórmulas, buscando datas e
                                aplicando multiplicadores
                            </p>
                        </div>
                    </div>

                    <!-- Dropzone -->
                    <div
                        :class="[
                            'flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-colors',
                            isDragging
                                ? 'border-primary bg-primary/5'
                                : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/20',
                            selectedFile
                                ? 'border-emerald-500/50 bg-emerald-500/5'
                                : '',
                        ]"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="onDropFile"
                        @click="fileInputRef?.click()"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="hidden"
                            @change="onFileSelect"
                        />

                        <div
                            v-if="!selectedFile"
                            class="flex flex-col items-center gap-2"
                        >
                            <div
                                class="rounded-full bg-primary/10 p-3 text-primary"
                            >
                                <UploadCloud class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    Clique para selecionar ou arraste o arquivo
                                    aqui
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Formatos aceitos: .xlsx, .xls, .csv (Máximo
                                    50MB)
                                </p>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex flex-col items-center gap-2 text-emerald-700 dark:text-emerald-400"
                        >
                            <div
                                class="rounded-full bg-emerald-500/10 p-3 text-emerald-600 dark:text-emerald-400"
                            >
                                <CheckCircle2 class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold">
                                    {{ selectedFile.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        (
                                            selectedFile.size /
                                            (1024 * 1024)
                                        ).toFixed(2)
                                    }}
                                    MB — Clique para trocar de arquivo
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div
                        class="flex flex-wrap items-center justify-end gap-3 pt-2"
                    >
                        <Button
                            variant="outline"
                            :disabled="
                                !selectedFile ||
                                isGeneratingPreview ||
                                isEnqueuingManual ||
                                templates.length === 0
                            "
                            @click="handleEnqueueManual"
                        >
                            <Clock
                                v-if="!isEnqueuingManual"
                                class="mr-2 h-4 w-4"
                            />
                            <RefreshCw
                                v-else
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            Enviar Diretamente para a Fila
                        </Button>

                        <Button
                            :disabled="
                                !selectedFile ||
                                isGeneratingPreview ||
                                templates.length === 0
                            "
                            @click="handleGeneratePreview"
                        >
                            <RefreshCw
                                v-if="isGeneratingPreview"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <FileSpreadsheet v-else class="mr-2 h-4 w-4" />
                            Gerar Prévia da Importação
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Tab 2: Pending Confirmations -->
        <div v-else-if="activeTab === 'pending'" class="space-y-4">
            <PendingConfirmationsTable
                :items="pendingConfirmations.data"
                :templates="templates"
                :is-processing="isProcessingConfirmation"
                :show-team-column="isGlobalView"
                @preview="handlePendingPreview"
                @enqueue="handlePendingEnqueue"
                @bulk-enqueue="handlePendingBulkEnqueue"
                @discard="handlePendingDiscard"
                @update-item="handlePendingUpdate"
            />
        </div>

        <!-- Tab 3: Processing Queue -->
        <div v-else-if="activeTab === 'queue'" class="space-y-4">
            <ImportQueueTable
                :items="queueItems.data"
                :is-refreshing="isRefreshingQueue"
                :show-team-column="isGlobalView"
                @retry="handleQueueRetry"
                @delete="handleQueueDelete"
                @refresh="handleRefreshQueue"
            />
        </div>

        <!-- Tab 4: History & Audit -->
        <div v-else-if="activeTab === 'history'" class="space-y-4">
            <ImportHistoryTable
                :batches="batches"
                :is-reverting="isReverting"
                :current-team-slug="currentTeam.slug"
                :show-team-column="isGlobalView"
                @revert="handleRevertBatch"
            />
        </div>

        <!-- Preview Modal -->
        <ImportPreviewDialog
            v-if="previewData"
            :open="isPreviewOpen"
            :file-name="previewFileName"
            :preview="previewData"
            :is-submitting="isExecutingImport"
            @update:open="isPreviewOpen = $event"
            @confirm="handleConfirmImport"
        />
    </div>
</template>
