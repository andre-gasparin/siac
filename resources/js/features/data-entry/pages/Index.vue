<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { CheckCircle2, Clock } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DataEntryForm from '@/features/data-entry/components/DataEntryForm.vue';
import type { SystemItem } from '@/features/data-entry/components/DataEntryForm.vue';
import DataEntryHistorySheet from '@/features/data-entry/components/DataEntryHistorySheet.vue';
import DataEntryReceiptDialog from '@/features/data-entry/components/DataEntryReceiptDialog.vue';
import { useDataEntryDraft } from '@/features/data-entry/composables/useDataEntryDraft';
import {
    entries as dataEntryEntries,
    index as dataEntryIndex,
    storeBatch as dataEntryStoreBatch,
} from '@/routes/data-entry';
import type { Team } from '@/shared/types';

interface InitialEntries {
    systems_with_data: number[];
    values: Record<number, number>;
    comments: Record<number, string>;
    collected_at: string;
}

const props = defineProps<{
    systems: SystemItem[];
    currentTeam: Team;
    currentTimestamp: string;
    defaultCollectionDate: string;
    defaultCollectionTime: string;
    initialEntries?: InitialEntries;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Entrada de dados',
            href: props.currentTeam
                ? dataEntryIndex.url({ current_team: props.currentTeam.slug })
                : '/',
        },
    ],
});

// Selected system and dialog states
const selectedSystemId = ref<number>(props.systems[0]?.id ?? 0);
const isHistoryOpen = ref(false);
const isReceiptOpen = ref(false);
const isSubmittingBatch = ref(false);

const currentSystem = computed<SystemItem | undefined>(() => {
    return (
        props.systems.find((s) => s.id === selectedSystemId.value) ||
        props.systems[0]
    );
});

// Collection Date & Time
const collectionDate = ref<string>(props.defaultCollectionDate);
const collectionTime = ref<string>(props.defaultCollectionTime);

// Live current registration timestamp (Hora do registro)
const liveTimestamp = ref<string>(props.currentTimestamp);
let timerInterval: ReturnType<typeof setInterval> | null = null;

function updateLiveClock() {
    const now = new Date();
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    liveTimestamp.value = `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
}

// In-memory draft composable
const {
    drafts,
    loadDrafts,
    saveSystemDraft,
    removeSystemDraft,
    clearAllDrafts,
} = useDataEntryDraft(props.currentTeam.slug);

// Parameter inputs state: record mapping parameterId -> raw string input
const paramInputs = ref<Record<number, string>>({});
const comment = ref<string>('');

// Existing entries cache from backend for currently selected collection timestamp
const systemsWithData = ref<number[]>(
    props.initialEntries?.systems_with_data ?? [],
);
const loadedValues = ref<Record<number, number>>(
    props.initialEntries?.values ?? {},
);
const loadedComments = ref<Record<number, string>>(
    props.initialEntries?.comments ?? {},
);
const isLoadingEntries = ref(false);
let fetchTimeout: ReturnType<typeof setTimeout> | null = null;

function isValidDateTime(
    date: string | undefined,
    time: string | undefined,
): boolean {
    if (!date || !time) {
        return false;
    }

    const isDateValid = /^\d{4}-\d{2}-\d{2}$/.test(date.trim());
    const isTimeValid = /^\d{2}:\d{2}(:\d{2})?$/.test(time.trim());

    return isDateValid && isTimeValid;
}

const currentCollectedAt = computed<string>(() => {
    if (!isValidDateTime(collectionDate.value, collectionTime.value)) {
        return '';
    }

    const timeFormatted =
        collectionTime.value.length === 5
            ? `${collectionTime.value}:00`
            : collectionTime.value;

    return `${collectionDate.value} ${timeFormatted}`;
});

const draftSystems = computed<number[]>(() => {
    const ids: number[] = [];

    for (const [key, draft] of Object.entries(drafts.value)) {
        const hasValue = Object.values(draft.values || {}).some(
            (v) => v !== '' && v !== null && v !== undefined,
        );
        const hasComment =
            draft.comment !== undefined &&
            draft.comment !== null &&
            draft.comment.trim() !== '';

        if (hasValue || hasComment) {
            ids.push(Number(key));
        }
    }

    return ids;
});

function populateCurrentSystemInputs() {
    if (!currentSystem.value) {
        return;
    }

    const sysId = currentSystem.value.id;
    const draft = drafts.value[sysId];

    const nextInputs: Record<number, string> = {};

    for (const param of currentSystem.value.parameters) {
        if (draft && draft.values && draft.values[param.id] !== undefined) {
            nextInputs[param.id] = draft.values[param.id] ?? '';
        } else {
            const val = loadedValues.value[param.id];

            if (val !== undefined && val !== null) {
                nextInputs[param.id] = String(val).replace('.', ',');
            } else {
                nextInputs[param.id] = '';
            }
        }
    }

    paramInputs.value = nextInputs;

    if (draft && draft.comment !== undefined) {
        comment.value = draft.comment;
    } else {
        const systemComment = loadedComments.value[sysId];
        comment.value = systemComment ?? '';
    }
}

function saveCurrentSystemToDraft() {
    if (!currentSystem.value) {
        return;
    }

    let hasAnyData = false;

    for (const param of currentSystem.value.parameters) {
        if (
            paramInputs.value[param.id] &&
            paramInputs.value[param.id].trim() !== ''
        ) {
            hasAnyData = true;
            break;
        }
    }

    const hasComment = comment.value && comment.value.trim() !== '';

    if (hasAnyData || hasComment) {
        saveSystemDraft(
            currentSystem.value.id,
            paramInputs.value,
            comment.value,
            collectionDate.value,
            collectionTime.value,
        );
    }
}

function selectSystem(systemId: number) {
    if (selectedSystemId.value === systemId) {
        return;
    }

    saveCurrentSystemToDraft();
    selectedSystemId.value = systemId;
}

let currentAbortController: AbortController | null = null;

async function fetchEntriesForCollectionTime() {
    if (!currentCollectedAt.value) {
        isLoadingEntries.value = false;

        return;
    }

    if (currentAbortController) {
        currentAbortController.abort();
    }

    currentAbortController = new AbortController();

    isLoadingEntries.value = true;

    try {
        const url = dataEntryEntries.url(
            { current_team: props.currentTeam.slug },
            { query: { collected_at: currentCollectedAt.value } },
        );
        const res = await fetch(url, {
            signal: currentAbortController.signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (res.ok) {
            const data: InitialEntries = await res.json();
            systemsWithData.value = data.systems_with_data || [];
            loadedValues.value = data.values || {};
            loadedComments.value = data.comments || {};
            loadDrafts();
            populateCurrentSystemInputs();
        }
    } catch (e: unknown) {
        if (e instanceof Error && e.name !== 'AbortError') {
            console.error('Erro ao buscar medições:', e);
        }
    } finally {
        isLoadingEntries.value = false;
    }
}

watch([() => collectionDate.value, () => collectionTime.value], () => {
    if (fetchTimeout) {
        clearTimeout(fetchTimeout);
    }

    if (!isValidDateTime(collectionDate.value, collectionTime.value)) {
        isLoadingEntries.value = false;

        return;
    }

    loadDrafts();
    isLoadingEntries.value = true;
    fetchTimeout = setTimeout(() => {
        fetchEntriesForCollectionTime();
    }, 600);
});

// Populate parameter values when active system changes
watch(
    () => selectedSystemId.value,
    () => {
        populateCurrentSystemInputs();
    },
);

onMounted(() => {
    updateLiveClock();
    timerInterval = setInterval(updateLiveClock, 1000);

    const restored = loadDrafts();

    if (
        restored.collectionDate &&
        isValidDateTime(
            restored.collectionDate,
            restored.collectionTime || '00:00',
        )
    ) {
        collectionDate.value = restored.collectionDate;

        if (restored.collectionTime) {
            collectionTime.value = restored.collectionTime;
        }
    }

    populateCurrentSystemInputs();
});

onUnmounted(() => {
    if (timerInterval) {
        clearInterval(timerInterval);
    }

    if (fetchTimeout) {
        clearTimeout(fetchTimeout);
    }

    if (currentAbortController) {
        currentAbortController.abort();
    }
});

function parseNumber(val: string | undefined): number | null {
    if (!val || val.trim() === '') {
        return null;
    }

    const clean = val.trim().replace(/\s/g, '').replace(',', '.');
    const parsed = parseFloat(clean);

    return isNaN(parsed) ? null : parsed;
}

// Save to browser memory (Draft)
function saveToMemory(andAdvance: boolean) {
    if (!currentSystem.value) {
        return;
    }

    if (!currentCollectedAt.value) {
        toast.error('Por favor, informe a data e hora da coleta.');

        return;
    }

    let filledCount = 0;

    for (const param of currentSystem.value.parameters) {
        const raw = paramInputs.value[param.id];
        const num = parseNumber(raw);

        if (num !== null) {
            filledCount++;
        }
    }

    const trimmedComment = comment.value.trim();

    if (filledCount === 0 && trimmedComment === '') {
        removeSystemDraft(currentSystem.value.id);
        toast.info(`Rascunho de ${currentSystem.value.name} limpo.`);
    } else {
        saveSystemDraft(
            currentSystem.value.id,
            paramInputs.value,
            comment.value,
            collectionDate.value,
            collectionTime.value,
        );
        toast.success(
            `Dados do ${currentSystem.value.name} salvos na memória!`,
        );
    }

    if (andAdvance) {
        const currentIndex = props.systems.findIndex(
            (s) => s.id === selectedSystemId.value,
        );

        if (currentIndex >= 0 && currentIndex < props.systems.length - 1) {
            selectedSystemId.value = props.systems[currentIndex + 1].id;
        } else {
            selectedSystemId.value = props.systems[0].id;
            toast.info(
                'Rodada de coleta concluída em todos os sistemas! Clique em "Enviar" para revisar e confirmar.',
            );
        }
    }
}

// Open Receipt Dialog
function handleOpenReceipt() {
    if (currentSystem.value && currentCollectedAt.value) {
        let hasTypedValues = false;

        for (const param of currentSystem.value.parameters) {
            if (
                paramInputs.value[param.id] &&
                paramInputs.value[param.id].trim() !== ''
            ) {
                hasTypedValues = true;
                break;
            }
        }

        if (hasTypedValues || comment.value.trim() !== '') {
            saveSystemDraft(
                currentSystem.value.id,
                paramInputs.value,
                comment.value,
                collectionDate.value,
                collectionTime.value,
            );
        }
    }

    if (draftSystems.value.length === 0) {
        toast.warning(
            'Nenhum dado salvo em memória para envio. Preencha os parâmetros e clique em "Salvar".',
        );

        return;
    }

    isReceiptOpen.value = true;
}

// Submit Batch to Backend
async function handleSubmitBatch() {
    if (!currentCollectedAt.value) {
        toast.error('Data e hora inválidas.');

        return;
    }

    const systemsPayload: Array<{
        monitored_system_id: number;
        values: Array<{ parameter_id: number; value: number | null }>;
        comment: string | null;
    }> = [];

    for (const system of props.systems) {
        const draft = drafts.value[system.id];

        if (!draft) {
            continue;
        }

        const values: Array<{ parameter_id: number; value: number | null }> =
            [];

        for (const param of system.parameters) {
            const raw = draft.values?.[param.id];
            const num = parseNumber(raw);

            if (num !== null) {
                values.push({ parameter_id: param.id, value: num });
            }
        }

        const comm = (draft.comment || '').trim();

        if (values.length > 0 || comm !== '') {
            systemsPayload.push({
                monitored_system_id: system.id,
                values,
                comment: comm !== '' ? comm : null,
            });
        }
    }

    if (systemsPayload.length === 0) {
        toast.warning('Nenhum dado válido para envio.');

        return;
    }

    isSubmittingBatch.value = true;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const batchUrl = dataEntryStoreBatch.url({
            current_team: props.currentTeam.slug,
        });

        const response = await fetch(batchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                collected_at: currentCollectedAt.value,
                systems: systemsPayload,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMsg =
                data.message || 'Erro ao persistir dados no banco.';
            toast.error(errorMsg);

            return;
        }

        toast.success(
            data.message || 'Dados gravados no banco de dados com sucesso!',
        );

        clearAllDrafts();

        for (const item of systemsPayload) {
            if (!systemsWithData.value.includes(item.monitored_system_id)) {
                systemsWithData.value = [
                    ...systemsWithData.value,
                    item.monitored_system_id,
                ];
            }

            for (const v of item.values) {
                if (v.value !== null) {
                    loadedValues.value[v.parameter_id] = v.value;
                } else {
                    delete loadedValues.value[v.parameter_id];
                }
            }

            if (item.comment) {
                loadedComments.value[item.monitored_system_id] = item.comment;
            } else {
                delete loadedComments.value[item.monitored_system_id];
            }
        }

        populateCurrentSystemInputs();
        isReceiptOpen.value = false;
    } catch (err) {
        console.error('Erro ao enviar lote:', err);
        toast.error(
            'Falha de comunicação com o servidor ao persistir os dados.',
        );
    } finally {
        isSubmittingBatch.value = false;
    }
}
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 p-4 md:p-6"
    >
        <Head title="Entrada de Dados" />

        <div class="flex flex-col items-start gap-6 md:flex-row">
            <!-- Painel Esquerdo: Listagem de Sistemas -->
            <div
                class="w-full shrink-0 rounded-2xl border border-border/80 bg-card p-5 shadow-xs md:w-72"
            >
                <div class="mb-4 border-b border-border/60 pb-3">
                    <h2 class="text-base font-bold text-foreground">Sistema</h2>
                </div>

                <div class="flex flex-col gap-2">
                    <button
                        v-for="system in systems"
                        :key="system.id"
                        type="button"
                        @click="selectSystem(system.id)"
                        class="relative flex w-full items-center justify-between gap-2 rounded-lg border px-3.5 py-2.5 text-left text-sm font-medium transition-all"
                        :class="[
                            selectedSystemId === system.id
                                ? 'border-primary/80 bg-primary/5 font-semibold text-foreground shadow-xs ring-1 ring-primary/30'
                                : 'border-border/60 text-muted-foreground hover:border-primary/40 hover:bg-muted/40 hover:text-foreground',
                        ]"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-2.5">
                            <div
                                class="h-5 w-1 shrink-0 rounded-full transition-colors"
                                :class="[
                                    selectedSystemId === system.id
                                        ? 'bg-primary'
                                        : 'bg-transparent',
                                ]"
                            ></div>
                            <span
                                class="truncate whitespace-nowrap"
                                :title="system.name"
                            >
                                {{ system.name }}
                            </span>
                        </div>

                        <!-- Indicador de Status: Em memória (Âmbar) ou No Banco (Verde) -->
                        <div
                            v-if="draftSystems.includes(system.id)"
                            class="flex shrink-0 items-center text-amber-500 dark:text-amber-400"
                            title="Pendente de envio / Em memória"
                        >
                            <Clock class="h-4 w-4" />
                        </div>
                        <div
                            v-else-if="systemsWithData.includes(system.id)"
                            class="flex shrink-0 items-center text-emerald-600 dark:text-emerald-400"
                            title="Coleta gravada no banco de dados"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                        </div>
                    </button>
                </div>
            </div>

            <!-- Painel Direito: Formulário e Ações -->
            <DataEntryForm
                :current-system="currentSystem"
                v-model:collection-date="collectionDate"
                v-model:collection-time="collectionTime"
                :live-timestamp="liveTimestamp"
                :is-loading-entries="isLoadingEntries"
                v-model:param-inputs="paramInputs"
                v-model:comment="comment"
                :draft-count="draftSystems.length"
                @save="saveToMemory"
                @send="handleOpenReceipt"
                @open-history="isHistoryOpen = true"
            />
        </div>

        <!-- Gaveta Lateral de Histórico de Envios -->
        <DataEntryHistorySheet
            v-model:open="isHistoryOpen"
            :current-team="currentTeam"
            :systems="systems"
        />

        <!-- Modal de Recibo de Conferência -->
        <DataEntryReceiptDialog
            v-model:open="isReceiptOpen"
            :collection-date="collectionDate"
            :collection-time="collectionTime"
            :systems="systems"
            :drafts="drafts"
            :is-submitting="isSubmittingBatch"
            @edit-system="(sysId) => selectSystem(sysId)"
            @confirm-send="handleSubmitBatch"
        />
    </div>
</template>
