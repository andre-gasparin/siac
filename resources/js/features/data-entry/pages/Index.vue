<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { CheckCircle2, Clock } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DataEntryForm from '@/features/data-entry/components/DataEntryForm.vue';
import type { SystemItem } from '@/features/data-entry/components/DataEntryForm.vue';
import DataEntryHistorySheet from '@/features/data-entry/components/DataEntryHistorySheet.vue';
import DataEntryReceiptDialog from '@/features/data-entry/components/DataEntryReceiptDialog.vue';
import type {
    ModifiedParamItem,
    ModifiedSystemItem,
} from '@/features/data-entry/components/DataEntryReceiptDialog.vue';
import {
    computeSystemDiff,
    useDataEntryDraft,
} from '@/features/data-entry/composables/useDataEntryDraft';
import type { SystemDiff } from '@/features/data-entry/composables/useDataEntryDraft';
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

// In-memory draft composable with localStorage persistence
const {
    drafts,
    loadDrafts,
    saveSystemDraft,
    removeSystemDraft,
    clearAllDrafts,
    getStoredResponsible,
    saveStoredResponsible,
} = useDataEntryDraft(props.currentTeam.slug);

// Responsável state (persisted across sessions for this team)
const responsible = ref<string>(getStoredResponsible());

watch(
    () => responsible.value,
    (val) => {
        saveStoredResponsible(val);
    },
);

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

// Map of systems that have ACTUAL modifications compared to loaded DB values
const modifiedSystemsMap = computed<Record<number, SystemDiff>>(() => {
    const map: Record<number, SystemDiff> = {};

    for (const system of props.systems) {
        const isCurrent = system.id === selectedSystemId.value;
        const inputs = isCurrent
            ? paramInputs.value
            : drafts.value[system.id]?.values || {};
        const comm = isCurrent
            ? comment.value
            : drafts.value[system.id]?.comment || '';

        const hasDraft = Boolean(drafts.value[system.id]);

        if (!isCurrent && !hasDraft) {
            continue;
        }

        const diff = computeSystemDiff(
            system.id,
            inputs,
            comm,
            system.parameters,
            loadedValues.value,
            loadedComments.value,
        );

        if (diff.isModified) {
            map[system.id] = diff;
        }
    }

    return map;
});

// Only systems with REAL differences show the Clock (pendente) icon!
const draftSystems = computed<number[]>(() => {
    return Object.keys(modifiedSystemsMap.value).map(Number);
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

    const diff = computeSystemDiff(
        currentSystem.value.id,
        paramInputs.value,
        comment.value,
        currentSystem.value.parameters,
        loadedValues.value,
        loadedComments.value,
    );

    if (diff.isModified) {
        saveSystemDraft(
            currentSystem.value.id,
            paramInputs.value,
            comment.value,
            collectionDate.value,
            collectionTime.value,
        );
    } else {
        // If not modified relative to DB, clean up redundant draft
        removeSystemDraft(currentSystem.value.id);
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

// Save to browser memory (Draft) with dirty-check notification
function saveToMemory(andAdvance: boolean) {
    if (!currentSystem.value) {
        return;
    }

    if (!currentCollectedAt.value) {
        toast.error('Por favor, informe a data e hora da coleta.');

        return;
    }

    const diff = computeSystemDiff(
        currentSystem.value.id,
        paramInputs.value,
        comment.value,
        currentSystem.value.parameters,
        loadedValues.value,
        loadedComments.value,
    );

    if (!diff.isModified) {
        removeSystemDraft(currentSystem.value.id);

        if (systemsWithData.value.includes(currentSystem.value.id)) {
            toast.info(
                `Os dados de ${currentSystem.value.name} já estão idênticos aos gravados no banco.`,
            );
        } else {
            toast.info(
                `Nenhum dado informado para salvar em ${currentSystem.value.name}.`,
            );
        }
    } else {
        saveSystemDraft(
            currentSystem.value.id,
            paramInputs.value,
            comment.value,
            collectionDate.value,
            collectionTime.value,
        );
        toast.success(
            `Alterações do ${currentSystem.value.name} salvas na memória!`,
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

// Data structures for Recibo de Conferência
const receiptModifiedSystems = computed<ModifiedSystemItem[]>(() => {
    const list: ModifiedSystemItem[] = [];

    for (const sysId of draftSystems.value) {
        const system = props.systems.find((s) => s.id === sysId);
        const diff = modifiedSystemsMap.value[sysId];

        if (!system || !diff) {
            continue;
        }

        const changedParams: ModifiedParamItem[] = diff.changedParams.map(
            (p) => {
                const paramItem = system.parameters.find(
                    (item) => item.id === p.parameter_id,
                )!;
                let isOutOfLimits = false;

                if (p.new_value !== null) {
                    if (
                        paramItem.alert_1_min !== null &&
                        p.new_value < paramItem.alert_1_min
                    ) {
                        isOutOfLimits = true;
                    }

                    if (
                        paramItem.alert_1_max !== null &&
                        p.new_value > paramItem.alert_1_max
                    ) {
                        isOutOfLimits = true;
                    }
                }

                return {
                    ...p,
                    param: paramItem,
                    isOutOfLimits,
                };
            },
        );

        const hasOutOfLimits = changedParams.some((p) => p.isOutOfLimits);

        list.push({
            system,
            changedParams,
            commentChanged: diff.commentChanged,
            oldComment: loadedComments.value[sysId] || '',
            newComment: diff.newComment,
            hasOutOfLimits,
        });
    }

    return list;
});

const receiptUnmodifiedSystems = computed<SystemItem[]>(() => {
    const modSet = new Set(draftSystems.value);

    return props.systems.filter((s) => !modSet.has(s.id));
});

// Open Receipt Dialog
function handleOpenReceipt() {
    saveCurrentSystemToDraft();

    const requiresResp = Boolean(
        props.currentTeam.requireDataEntryResponsible ??
        props.currentTeam.require_data_entry_responsible,
    );

    if (
        requiresResp &&
        (!responsible.value || responsible.value.trim() === '')
    ) {
        toast.error(
            'O preenchimento do campo Responsável é obrigatório para esta unidade.',
        );

        return;
    }

    if (draftSystems.value.length === 0) {
        toast.warning(
            'Nenhuma alteração pendente para envio. Altere algum parâmetro ou comentário antes de enviar.',
        );

        return;
    }

    isReceiptOpen.value = true;
}

// Submit Batch of ONLY modified fields to Backend
async function handleSubmitBatch() {
    if (!currentCollectedAt.value) {
        toast.error('Data e hora inválidas.');

        return;
    }

    const requiresResp = Boolean(
        props.currentTeam.requireDataEntryResponsible ??
        props.currentTeam.require_data_entry_responsible,
    );

    if (
        requiresResp &&
        (!responsible.value || responsible.value.trim() === '')
    ) {
        toast.error(
            'O preenchimento do campo Responsável é obrigatório para esta unidade.',
        );

        return;
    }

    const systemsPayload: Array<{
        monitored_system_id: number;
        values: Array<{ parameter_id: number; value: number | null }>;
        comment: string | null;
    }> = [];

    for (const item of receiptModifiedSystems.value) {
        const values = item.changedParams.map((p) => ({
            parameter_id: p.parameter_id,
            value: p.new_value,
        }));

        systemsPayload.push({
            monitored_system_id: item.system.id,
            values,
            comment: item.commentChanged ? item.newComment : null,
        });
    }

    if (systemsPayload.length === 0) {
        toast.warning('Nenhuma alteração válida para envio.');

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
                responsible: responsible.value
                    ? responsible.value.trim()
                    : null,
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

        // Update local cache with newly persisted values
        for (const item of receiptModifiedSystems.value) {
            if (!systemsWithData.value.includes(item.system.id)) {
                systemsWithData.value = [
                    ...systemsWithData.value,
                    item.system.id,
                ];
            }

            for (const p of item.changedParams) {
                if (p.new_value !== null) {
                    loadedValues.value[p.parameter_id] = p.new_value;
                } else {
                    delete loadedValues.value[p.parameter_id];
                }
            }

            if (item.commentChanged) {
                if (item.newComment !== null) {
                    loadedComments.value[item.system.id] = item.newComment;
                } else {
                    delete loadedComments.value[item.system.id];
                }
            }
        }

        clearAllDrafts();
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

                        <!-- Indicador de Status: Alterado/Pendente (Âmbar) ou No Banco (Verde) -->
                        <div
                            v-if="draftSystems.includes(system.id)"
                            class="flex shrink-0 items-center text-amber-500 dark:text-amber-400"
                            title="Modificações pendentes de envio"
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
                v-model:responsible="responsible"
                :require-responsible="
                    Boolean(
                        currentTeam.requireDataEntryResponsible ??
                        currentTeam.require_data_entry_responsible,
                    )
                "
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
            :responsible="responsible"
            :modified-systems="receiptModifiedSystems"
            :unmodified-systems="receiptUnmodifiedSystems"
            :is-submitting="isSubmittingBatch"
            @edit-system="(sysId) => selectSystem(sysId)"
            @confirm-send="handleSubmitBatch"
        />
    </div>
</template>
