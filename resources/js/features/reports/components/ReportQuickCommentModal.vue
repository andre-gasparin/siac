<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    ChevronDown,
    Info,
    Loader2,
    Pencil,
    Plus,
    Search,
    Send,
    Trash2,
    X,
} from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import type {
    QuickCommentPayload,
    ReportCommentTemplate,
    ReportIconKind,
    ReportParameter,
} from '@/features/reports/types';
import {
    destroy as destroyCommentTemplate,
    index as indexCommentTemplates,
    store as storeCommentTemplate,
    update as updateCommentTemplate,
} from '@/routes/reports/comment-templates';

const props = defineProps<{
    show: boolean;
    teamSlug: string;
    parameters: ReportParameter[];
}>();

const emit = defineEmits<{
    close: [];
    submit: [payload: QuickCommentPayload];
}>();

interface StatusOption {
    value: ReportIconKind;
    label: string;
    colorClass: string;
}

const statusOptions: StatusOption[] = [
    { value: 'success', label: 'Sucesso', colorClass: 'text-emerald-600' },
    { value: 'warning', label: 'Aviso', colorClass: 'text-amber-500' },
    { value: 'error', label: 'Erro', colorClass: 'text-red-600' },
    { value: 'info', label: 'Informação', colorClass: 'text-blue-600' },
];

const selectedStatus = ref<ReportIconKind | null>(null);
const showStatusDropdown = ref(false);
const statusDropdownRef = ref<HTMLElement | null>(null);

const selectedParameterIds = ref<number[]>([]);
const showParameterDropdown = ref(false);
const parameterDropdownRef = ref<HTMLElement | null>(null);
const parameterSearchInputRef = ref<HTMLInputElement | null>(null);
const parameterSearch = ref('');

const templates = ref<ReportCommentTemplate[]>([]);
const isLoadingTemplates = ref(false);
const templatesLoaded = ref(false);
const selectedTemplateId = ref<number | null>(null);

const formMode = ref<'create' | 'edit' | null>(null);
const editingTemplateId = ref<number | null>(null);
const draftBody = ref('');
const isMutating = ref(false);
const confirmingDeleteId = ref<number | null>(null);
const errorMessage = ref<string | null>(null);
const draftTextareaRef = ref<HTMLTextAreaElement | null>(null);

onClickOutside(statusDropdownRef, () => {
    showStatusDropdown.value = false;
});

onClickOutside(parameterDropdownRef, () => {
    showParameterDropdown.value = false;
});

const csrfToken = (): string =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const selectedStatusOption = computed(
    () =>
        statusOptions.find((option) => option.value === selectedStatus.value) ??
        null,
);

const filteredParameters = computed(() => {
    const query = parameterSearch.value.trim().toLowerCase();

    if (!query) {
        return props.parameters;
    }

    return props.parameters.filter((parameter) =>
        parameter.name.toLowerCase().includes(query),
    );
});

const selectedParameters = computed(() =>
    props.parameters.filter((parameter) =>
        selectedParameterIds.value.includes(parameter.id),
    ),
);

const selectedTemplate = computed(
    () =>
        templates.value.find(
            (template) => template.id === selectedTemplateId.value,
        ) ?? null,
);

function naturalJoin(names: string[]): string {
    if (names.length <= 1) {
        return names[0] ?? '';
    }

    return `${names.slice(0, -1).join(', ')} e ${names.at(-1)}`;
}

const formattedParametersPreview = computed(() =>
    naturalJoin(selectedParameters.value.map((parameter) => parameter.name)),
);

const canSubmit = computed(
    () =>
        selectedStatus.value !== null ||
        selectedParameterIds.value.length > 0 ||
        selectedTemplate.value !== null,
);

function toggleParameterDropdown(): void {
    showParameterDropdown.value = !showParameterDropdown.value;
    showStatusDropdown.value = false;

    if (showParameterDropdown.value) {
        void nextTick(() => parameterSearchInputRef.value?.focus());
    }
}

function toggleParameter(parameterId: number): void {
    if (selectedParameterIds.value.includes(parameterId)) {
        selectedParameterIds.value = selectedParameterIds.value.filter(
            (id) => id !== parameterId,
        );

        return;
    }

    selectedParameterIds.value = [...selectedParameterIds.value, parameterId];
}

function removeParameter(parameterId: number): void {
    selectedParameterIds.value = selectedParameterIds.value.filter(
        (id) => id !== parameterId,
    );
}

async function fetchTemplates(): Promise<void> {
    isLoadingTemplates.value = true;
    errorMessage.value = null;

    try {
        const response = await fetch(
            indexCommentTemplates.url({ current_team: props.teamSlug }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Não foi possível carregar os comentários salvos.');
        }

        templates.value = await response.json();
        templatesLoaded.value = true;
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Erro ao carregar os comentários salvos.';
    } finally {
        isLoadingTemplates.value = false;
    }
}

function sortTemplates(list: ReportCommentTemplate[]): ReportCommentTemplate[] {
    return [...list].sort((a, b) =>
        a.body.localeCompare(b.body, 'pt-BR', { sensitivity: 'base' }),
    );
}

function startCreate(): void {
    formMode.value = 'create';
    editingTemplateId.value = null;
    draftBody.value = '';
    confirmingDeleteId.value = null;
    errorMessage.value = null;
    void nextTick(() => draftTextareaRef.value?.focus());
}

function startEdit(template: ReportCommentTemplate): void {
    formMode.value = 'edit';
    editingTemplateId.value = template.id;
    draftBody.value = template.body;
    confirmingDeleteId.value = null;
    errorMessage.value = null;
    void nextTick(() => draftTextareaRef.value?.focus());
}

function cancelForm(): void {
    formMode.value = null;
    editingTemplateId.value = null;
    draftBody.value = '';
    errorMessage.value = null;
}

async function saveTemplate(): Promise<void> {
    const trimmed = draftBody.value.trim();

    if (!trimmed) {
        errorMessage.value = 'Informe o texto do comentário.';

        return;
    }

    isMutating.value = true;
    errorMessage.value = null;

    try {
        const isEditing =
            formMode.value === 'edit' && editingTemplateId.value !== null;
        const url = isEditing
            ? updateCommentTemplate.url({
                  current_team: props.teamSlug,
                  comment_template: editingTemplateId.value!,
              })
            : storeCommentTemplate.url({ current_team: props.teamSlug });

        const response = await fetch(url, {
            method: isEditing ? 'PUT' : 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ body: trimmed }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            const firstError =
                payload?.errors?.body?.[0] ??
                payload?.message ??
                'Não foi possível salvar o comentário.';

            throw new Error(firstError);
        }

        const saved: ReportCommentTemplate = await response.json();

        if (isEditing) {
            templates.value = sortTemplates(
                templates.value.map((item) =>
                    item.id === saved.id ? saved : item,
                ),
            );
        } else {
            templates.value = sortTemplates([...templates.value, saved]);
            selectedTemplateId.value = saved.id;
        }

        cancelForm();
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Erro ao salvar o comentário.';
    } finally {
        isMutating.value = false;
    }
}

async function removeTemplate(templateId: number): Promise<void> {
    isMutating.value = true;
    errorMessage.value = null;

    try {
        const response = await fetch(
            destroyCommentTemplate.url({
                current_team: props.teamSlug,
                comment_template: templateId,
            }),
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Não foi possível remover o comentário.');
        }

        templates.value = templates.value.filter(
            (item) => item.id !== templateId,
        );

        if (selectedTemplateId.value === templateId) {
            selectedTemplateId.value = null;
        }

        if (editingTemplateId.value === templateId) {
            cancelForm();
        }

        confirmingDeleteId.value = null;
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Erro ao remover o comentário.';
    } finally {
        isMutating.value = false;
    }
}

function resetSelections(): void {
    selectedStatus.value = null;
    selectedParameterIds.value = [];
    selectedTemplateId.value = null;
    parameterSearch.value = '';
    confirmingDeleteId.value = null;
    showStatusDropdown.value = false;
    showParameterDropdown.value = false;
    cancelForm();
}

function handleSubmit(): void {
    if (!canSubmit.value) {
        return;
    }

    emit('submit', {
        status: selectedStatus.value,
        parameterIds: [...selectedParameterIds.value],
        comment: selectedTemplate.value?.body ?? '',
    });
    resetSelections();
}

function handleClose(): void {
    showStatusDropdown.value = false;
    showParameterDropdown.value = false;
    confirmingDeleteId.value = null;
    cancelForm();
    emit('close');
}

function handleKeydown(event: KeyboardEvent): void {
    if (!props.show || event.key !== 'Escape') {
        return;
    }

    if (showParameterDropdown.value) {
        showParameterDropdown.value = false;

        return;
    }

    if (showStatusDropdown.value) {
        showStatusDropdown.value = false;

        return;
    }

    if (formMode.value !== null) {
        cancelForm();

        return;
    }

    if (confirmingDeleteId.value !== null) {
        confirmingDeleteId.value = null;

        return;
    }

    handleClose();
}

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            errorMessage.value = null;
            void fetchTemplates();
        }
    },
);

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div
        v-if="show"
        data-test="quick-comment-modal"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-xs"
        @click.self="handleClose"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-5xl flex-col rounded-2xl border border-border bg-card shadow-2xl"
        >
            <!-- Cabeçalho -->
            <div
                class="flex items-center justify-between border-b border-border px-6 py-4"
            >
                <div>
                    <h3 class="text-sm font-semibold text-foreground">
                        Inserir comentário rápido
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Combine status, parâmetros e um comentário padrão.
                    </p>
                </div>
                <button
                    type="button"
                    data-test="close-quick-comment-modal"
                    class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    title="Fechar"
                    @click="handleClose"
                >
                    <X class="size-4" />
                    <span class="sr-only">Fechar</span>
                </button>
            </div>

            <!-- Corpo em 3 Colunas -->
            <div class="p-6">
                <div
                    class="grid grid-cols-1 gap-5 md:grid-cols-[180px_280px_1fr]"
                >
                    <!-- Coluna 1: Status -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold text-foreground">
                            Status:
                        </label>
                        <div ref="statusDropdownRef" class="relative">
                            <button
                                type="button"
                                data-test="quick-comment-status-trigger"
                                class="flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-border bg-background px-3 text-xs text-foreground shadow-2xs transition hover:bg-muted/40"
                                @click="
                                    showStatusDropdown = !showStatusDropdown;
                                    showParameterDropdown = false;
                                "
                            >
                                <span
                                    v-if="selectedStatusOption"
                                    class="inline-flex items-center gap-2 font-medium"
                                >
                                    <Check
                                        v-if="
                                            selectedStatusOption.value ===
                                            'success'
                                        "
                                        class="size-4 text-emerald-600"
                                    />
                                    <AlertTriangle
                                        v-else-if="
                                            selectedStatusOption.value ===
                                            'warning'
                                        "
                                        class="size-4 text-amber-500"
                                    />
                                    <X
                                        v-else-if="
                                            selectedStatusOption.value ===
                                            'error'
                                        "
                                        class="size-4 text-red-600"
                                    />
                                    <Info v-else class="size-4 text-blue-600" />
                                    <span>{{
                                        selectedStatusOption.label
                                    }}</span>
                                </span>
                                <span v-else class="text-muted-foreground">
                                    Selecionar
                                </span>
                                <ChevronDown
                                    class="size-3.5 shrink-0 text-muted-foreground"
                                />
                            </button>

                            <div
                                v-if="showStatusDropdown"
                                data-test="quick-comment-status-menu"
                                class="absolute top-full left-0 z-30 mt-1 w-full rounded-lg border border-border bg-popover p-1 shadow-xl"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-xs text-muted-foreground transition hover:bg-muted"
                                    :class="
                                        selectedStatus === null
                                            ? 'bg-muted/70 font-medium text-foreground'
                                            : ''
                                    "
                                    @click="
                                        selectedStatus = null;
                                        showStatusDropdown = false;
                                    "
                                >
                                    Sem status
                                </button>
                                <button
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    type="button"
                                    :data-test="`quick-comment-status-${option.value}`"
                                    class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-xs text-foreground transition hover:bg-muted"
                                    :class="
                                        selectedStatus === option.value
                                            ? 'bg-muted font-medium'
                                            : ''
                                    "
                                    @click="
                                        selectedStatus = option.value;
                                        showStatusDropdown = false;
                                    "
                                >
                                    <Check
                                        v-if="option.value === 'success'"
                                        class="size-4 text-emerald-600"
                                    />
                                    <AlertTriangle
                                        v-else-if="option.value === 'warning'"
                                        class="size-4 text-amber-500"
                                    />
                                    <X
                                        v-else-if="option.value === 'error'"
                                        class="size-4 text-red-600"
                                    />
                                    <Info v-else class="size-4 text-blue-600" />
                                    <span>{{ option.label }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna 2: Parâmetro (Multi-select combobox estilo Select2) -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold text-foreground">
                            Parâmetro:
                        </label>
                        <div ref="parameterDropdownRef" class="relative">
                            <div
                                role="combobox"
                                tabindex="0"
                                :aria-expanded="showParameterDropdown"
                                data-test="quick-comment-parameters-trigger"
                                class="flex min-h-10 w-full cursor-pointer items-center justify-between gap-2 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs text-foreground shadow-2xs transition hover:bg-muted/30"
                                :class="
                                    showParameterDropdown
                                        ? 'ring-2 ring-primary/30'
                                        : ''
                                "
                                @click="toggleParameterDropdown"
                                @keydown.enter.prevent="toggleParameterDropdown"
                                @keydown.space.prevent="toggleParameterDropdown"
                            >
                                <div
                                    class="flex min-w-0 flex-1 flex-wrap items-center gap-1"
                                >
                                    <template
                                        v-if="selectedParameters.length > 0"
                                    >
                                        <span
                                            v-for="parameter in selectedParameters"
                                            :key="parameter.id"
                                            class="inline-flex max-w-full items-center gap-1 rounded-md border border-emerald-500/25 bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300"
                                        >
                                            <span class="truncate">{{
                                                parameter.name
                                            }}</span>
                                            <button
                                                type="button"
                                                class="rounded-sm p-0.5 hover:bg-emerald-500/20"
                                                :title="`Remover ${parameter.name}`"
                                                @click.stop="
                                                    removeParameter(
                                                        parameter.id,
                                                    )
                                                "
                                            >
                                                <X class="size-2.5" />
                                            </button>
                                        </span>
                                    </template>
                                    <span
                                        v-else
                                        class="px-0.5 text-muted-foreground"
                                    >
                                        Selecionar
                                    </span>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        v-if="selectedParameters.length > 0"
                                        type="button"
                                        title="Limpar parâmetros selecionados"
                                        class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                        @click.stop="selectedParameterIds = []"
                                    >
                                        <X class="size-3" />
                                    </button>
                                    <ChevronDown
                                        class="size-3.5 text-muted-foreground transition-transform"
                                        :class="
                                            showParameterDropdown
                                                ? 'rotate-180'
                                                : ''
                                        "
                                    />
                                </div>
                            </div>

                            <div
                                v-if="showParameterDropdown"
                                data-test="quick-comment-parameters-menu"
                                class="absolute top-full left-0 z-30 mt-1 w-full rounded-lg border border-border bg-popover p-1.5 shadow-xl"
                            >
                                <div class="relative mb-1.5">
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <input
                                        ref="parameterSearchInputRef"
                                        v-model="parameterSearch"
                                        type="text"
                                        placeholder="Buscar parâmetro..."
                                        class="h-8 w-full rounded-md border border-border bg-background pr-2.5 pl-8 text-xs text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/30 focus:outline-hidden"
                                        @click.stop
                                    />
                                </div>

                                <div
                                    class="max-h-52 space-y-0.5 overflow-y-auto"
                                >
                                    <button
                                        v-for="parameter in filteredParameters"
                                        :key="parameter.id"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-1.5 text-left text-xs transition hover:bg-muted"
                                        :class="
                                            selectedParameterIds.includes(
                                                parameter.id,
                                            )
                                                ? 'bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300'
                                                : 'text-foreground'
                                        "
                                        @click="toggleParameter(parameter.id)"
                                    >
                                        <span class="truncate">{{
                                            parameter.name
                                        }}</span>
                                        <Check
                                            v-if="
                                                selectedParameterIds.includes(
                                                    parameter.id,
                                                )
                                            "
                                            class="size-3.5 shrink-0 text-emerald-600"
                                        />
                                    </button>
                                    <p
                                        v-if="filteredParameters.length === 0"
                                        class="px-2 py-3 text-center text-xs text-muted-foreground"
                                    >
                                        Nenhum parâmetro encontrado.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna 3: Comentário + Novo / Editar / Remover -->
                    <div class="flex min-w-0 flex-col gap-2">
                        <label class="text-xs font-semibold text-foreground">
                            Comentário:
                        </label>

                        <div class="flex items-center gap-1.5">
                            <select
                                v-model="selectedTemplateId"
                                data-test="quick-comment-select"
                                :disabled="isLoadingTemplates"
                                class="h-10 min-w-0 flex-1 rounded-lg border border-border bg-background px-3 text-xs text-foreground shadow-2xs focus:ring-2 focus:ring-primary/30 focus:outline-hidden disabled:opacity-60"
                            >
                                <option :value="null">
                                    {{
                                        isLoadingTemplates
                                            ? 'Carregando comentários...'
                                            : 'Selecionar'
                                    }}
                                </option>
                                <option
                                    v-for="template in templates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.body }}
                                </option>
                            </select>

                            <button
                                type="button"
                                data-test="quick-comment-edit-selected"
                                :disabled="!selectedTemplate || isMutating"
                                title="Editar comentário selecionado"
                                class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                @click="
                                    selectedTemplate &&
                                    startEdit(selectedTemplate)
                                "
                            >
                                <Pencil class="size-3.5" />
                            </button>

                            <button
                                type="button"
                                data-test="quick-comment-delete-selected"
                                :disabled="!selectedTemplate || isMutating"
                                title="Remover comentário selecionado"
                                class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground transition hover:border-red-300 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:border-red-900 dark:hover:bg-red-950/30"
                                @click="
                                    selectedTemplate &&
                                    (confirmingDeleteId = selectedTemplate.id)
                                "
                            >
                                <Trash2 class="size-3.5" />
                            </button>

                            <button
                                type="button"
                                data-test="quick-comment-new-button"
                                :disabled="isMutating"
                                title="Novo comentário padrão"
                                class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-emerald-600 transition hover:border-emerald-500/40 hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="startCreate"
                            >
                                <Plus class="size-4" />
                            </button>
                        </div>

                        <!-- Confirmação de remoção -->
                        <div
                            v-if="
                                selectedTemplate &&
                                confirmingDeleteId === selectedTemplate.id
                            "
                            class="flex items-center justify-between gap-2 rounded-lg border border-red-200 bg-red-50/70 px-3 py-2 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200"
                        >
                            <span>Remover este comentário padrão?</span>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-[11px] font-medium hover:bg-red-100 dark:hover:bg-red-900/40"
                                    @click="confirmingDeleteId = null"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    data-test="quick-comment-confirm-delete"
                                    :disabled="isMutating"
                                    class="inline-flex items-center gap-1 rounded bg-red-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-red-700 disabled:opacity-50"
                                    @click="removeTemplate(selectedTemplate.id)"
                                >
                                    <Loader2
                                        v-if="isMutating"
                                        class="size-3 animate-spin"
                                    />
                                    Remover
                                </button>
                            </div>
                        </div>

                        <p
                            v-if="
                                templatesLoaded &&
                                templates.length === 0 &&
                                formMode === null
                            "
                            class="rounded-lg border border-dashed border-border p-3 text-center text-xs text-muted-foreground"
                        >
                            Nenhum comentário cadastrado ainda. Clique no botão
                            <strong>+</strong> para criar o primeiro.
                        </p>

                        <!-- Formulário Inline de Criação / Edição -->
                        <div
                            v-if="formMode !== null"
                            data-test="quick-comment-form"
                            class="space-y-2 rounded-xl border border-border bg-muted/35 p-3"
                        >
                            <p
                                class="text-[11px] font-semibold text-foreground"
                            >
                                {{
                                    formMode === 'edit'
                                        ? 'Editar comentário padrão'
                                        : 'Novo comentário padrão'
                                }}
                            </p>
                            <textarea
                                ref="draftTextareaRef"
                                v-model="draftBody"
                                data-test="quick-comment-body-input"
                                rows="3"
                                placeholder="Ex.: apresentou valor acima do limite especificado."
                                class="w-full resize-none rounded-lg border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/30 focus:outline-hidden"
                            />
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    @click="cancelForm"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    data-test="quick-comment-save-template"
                                    :disabled="isMutating || !draftBody.trim()"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                                    @click="saveTemplate"
                                >
                                    <Loader2
                                        v-if="isMutating"
                                        class="size-3.5 animate-spin"
                                    />
                                    {{
                                        formMode === 'edit'
                                            ? 'Salvar alterações'
                                            : 'Salvar comentário'
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- Mensagem de erro -->
                        <p
                            v-if="errorMessage"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
                        >
                            {{ errorMessage }}
                        </p>
                    </div>
                </div>

                <!-- Prévia ao vivo -->
                <div
                    v-if="canSubmit"
                    data-test="quick-comment-preview"
                    class="mt-5 rounded-xl border border-border bg-muted/40 px-4 py-3 text-xs text-foreground"
                >
                    <p
                        class="mb-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Prévia no editor
                    </p>
                    <p class="leading-relaxed">
                        <span
                            v-if="selectedStatus === 'success'"
                            class="mr-1 font-bold text-emerald-600"
                            >✓</span
                        >
                        <span
                            v-else-if="selectedStatus === 'warning'"
                            class="mr-1 font-bold text-amber-500"
                            >⚠</span
                        >
                        <span
                            v-else-if="selectedStatus === 'error'"
                            class="mr-1 font-bold text-red-600"
                            >✕</span
                        >
                        <span
                            v-else-if="selectedStatus === 'info'"
                            class="mr-1 font-bold text-blue-600"
                            >ⓘ</span
                        >
                        <strong v-if="formattedParametersPreview">
                            {{ formattedParametersPreview }}
                        </strong>
                        <span
                            v-if="
                                formattedParametersPreview && selectedTemplate
                            "
                            >:
                        </span>
                        <span v-if="selectedTemplate">
                            {{ selectedTemplate.body }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Rodapé -->
            <div
                class="flex items-center justify-end gap-2 rounded-b-2xl border-t border-border bg-muted/20 px-6 py-3.5"
            >
                <button
                    type="button"
                    class="rounded-lg border border-border bg-background px-4 py-2 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    @click="handleClose"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    data-test="quick-comment-submit"
                    :disabled="!canSubmit"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="handleSubmit"
                >
                    <Send class="size-3.5" />
                    Enviar
                </button>
            </div>
        </div>
    </div>
</template>
