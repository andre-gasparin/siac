<script setup lang="ts">
import {
    AlertTriangle,
    BarChart3,
    Bold,
    Check,
    ChevronDown,
    Copy,
    Eraser,
    Highlighter,
    Image as ImageIcon,
    Info,
    Italic,
    Move,
    Pin,
    Redo2,
    RefreshCw,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Underline as UnderlineIcon,
    Undo2,
    X,
} from '@lucide/vue';
import type { Editor } from '@tiptap/vue-3';
import { onClickOutside } from '@vueuse/core';
import { ref } from 'vue';
import type { ReportContext } from '@/features/reports/types';

const selectedInlineParameterIds = defineModel<number[]>(
    'selectedInlineParameterIds',
    { required: true },
);

const props = defineProps<{
    editor?: Editor;
    context: ReportContext | null;
    isFloating?: boolean;
    showFloatingToggle?: boolean;
}>();

const emit = defineEmits<{
    insertCheckPhrase: [];
    insertIcon: [kind: 'error' | 'warning' | 'success' | 'info'];
    insertParameters: [];
    applyLatest: [mode: 'append' | 'replace'];
    loadHistory: [];
    prepareChartDialog: [];
    openChartUpdater: [];
    insertImage: [file: File];
    toggleFloating: [];
}>();

const showLatestPreview = ref(false);
const latestCommentContainerRef = ref<HTMLElement | null>(null);
const showTextColorPalette = ref(false);
const textColorContainerRef = ref<HTMLElement | null>(null);
const showHighlightPalette = ref(false);
const highlightColorContainerRef = ref<HTMLElement | null>(null);
const imageInputRef = ref<HTMLInputElement | null>(null);

onClickOutside(latestCommentContainerRef, () => {
    showLatestPreview.value = false;
});
onClickOutside(textColorContainerRef, () => {
    showTextColorPalette.value = false;
});
onClickOutside(highlightColorContainerRef, () => {
    showHighlightPalette.value = false;
});

const textColors = [
    { label: 'Rosa', value: '#ec4899' },
    { label: 'Preto', value: '#0f172a' },
    { label: 'Cinza', value: '#64748b' },
    { label: 'Vermelho', value: '#dc2626' },
    { label: 'Azul', value: '#2563eb' },
    { label: 'Verde', value: '#16a34a' },
    { label: 'Âmbar', value: '#d97706' },
];

const highlightColors = [
    { label: 'Rosa', value: '#fecdd3' },
    { label: 'Amarelo', value: '#fef08a' },
    { label: 'Verde', value: '#bbf7d0' },
    { label: 'Azul', value: '#bae6fd' },
    { label: 'Laranja', value: '#fed7aa' },
    { label: 'Roxo', value: '#e9d5ff' },
];

const selectedTextColor = ref(textColors[0].value);
const selectedHighlightColor = ref(highlightColors[0].value);

function applyTextColor(color = selectedTextColor.value) {
    selectedTextColor.value = color;
    props.editor?.chain().focus().setColor(color).run();
    showTextColorPalette.value = false;
}

function unsetTextColor() {
    props.editor?.chain().focus().unsetColor().run();
    showTextColorPalette.value = false;
}

function applyHighlightColor(color = selectedHighlightColor.value) {
    selectedHighlightColor.value = color;
    props.editor?.chain().focus().toggleHighlight({ color }).run();
    showHighlightPalette.value = false;
}

function unsetHighlightColor() {
    props.editor?.chain().focus().unsetHighlight().run();
    showHighlightPalette.value = false;
}

function formatBrazilianDate(value: string): string {
    if (!value) {
        return '';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}

function handleImageFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (file) {
        emit('insertImage', file);
    }

    input.value = '';
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1 border-b p-2">
        <!-- 1. Ícones de Atenção & Status -->
        <button
            type="button"
            class="editor-action text-emerald-600"
            title="Todos dentro dos limites"
            @click="emit('insertCheckPhrase')"
        >
            <Check class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action text-red-600"
            title="Inserir ícone de erro"
            @click="emit('insertIcon', 'error')"
        >
            <X class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action text-amber-500"
            title="Inserir ícone de aviso"
            @click="emit('insertIcon', 'warning')"
        >
            <AlertTriangle class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action text-emerald-600"
            title="Inserir ícone de sucesso"
            @click="emit('insertIcon', 'success')"
        >
            <Check class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action text-blue-600"
            title="Inserir ícone de informação"
            @click="emit('insertIcon', 'info')"
        >
            <Info class="size-4" />
        </button>

        <!-- 2. Último comentário (logo após os ícones de atenção) com Prévia -->
        <div ref="latestCommentContainerRef" class="relative">
            <button
                type="button"
                class="editor-select"
                data-test="toggle-latest-comment"
                title="Visualizar e carregar último comentário"
                @click="showLatestPreview = !showLatestPreview"
            >
                Último comentário
                <span
                    v-if="context?.latest_comment"
                    class="font-normal text-muted-foreground"
                >
                    ({{
                        formatBrazilianDate(
                            context.latest_comment.date_reference,
                        )
                    }})
                </span>
                <ChevronDown class="size-3 text-muted-foreground" />
            </button>

            <!-- Popover de Prévia do Último Comentário -->
            <div
                v-if="showLatestPreview"
                class="absolute top-full left-0 z-30 mt-2 w-80 rounded-xl border bg-popover p-3 text-xs shadow-xl md:w-96"
                data-test="latest-comment-preview"
            >
                <div class="flex items-center justify-between border-b pb-2">
                    <p class="font-semibold text-foreground">
                        Último comentário salvo
                    </p>
                    <span
                        v-if="context?.latest_comment"
                        class="rounded bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                    >
                        {{
                            formatBrazilianDate(
                                context.latest_comment.date_reference,
                            )
                        }}
                    </span>
                </div>

                <div v-if="context?.latest_comment" class="mt-2.5">
                    <p
                        class="mb-1 text-[11px] font-medium text-muted-foreground"
                    >
                        Prévia do conteúdo:
                    </p>
                    <div
                        class="max-h-48 overflow-y-auto rounded-lg border bg-muted/30 p-2.5 text-xs leading-relaxed text-foreground select-text"
                        v-html="
                            context.latest_comment.html ||
                            '<p class=\'text-muted-foreground italic\'>Sem conteúdo textual.</p>'
                        "
                    />

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-700"
                            @click="
                                emit('applyLatest', 'append');
                                showLatestPreview = false;
                            "
                        >
                            <Copy class="size-3.5" />
                            Inserir ao final
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 px-3 py-2 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30"
                            @click="
                                emit('applyLatest', 'replace');
                                showLatestPreview = false;
                            "
                        >
                            <RefreshCw class="size-3.5" />
                            Substituir editor
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-3 rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground"
                >
                    Nenhum comentário anterior encontrado para este sistema.
                </div>

                <div class="mt-2.5 flex justify-end border-t pt-2">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1 text-xs text-muted-foreground hover:bg-muted"
                        @click="showLatestPreview = false"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. Relatórios anteriores (logo após o último comentário) -->
        <button
            type="button"
            class="editor-select"
            title="Ver histórico de relatórios anteriores"
            @click="emit('loadHistory')"
        >
            Relatórios anteriores
        </button>

        <div class="mx-1 h-5 w-px bg-border/60" />

        <!-- 4. Formatações de Texto Básicas -->
        <button
            type="button"
            class="editor-action"
            title="Negrito (Ctrl+B)"
            @click="editor?.chain().focus().toggleBold().run()"
        >
            <Bold class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            title="Itálico (Ctrl+I)"
            @click="editor?.chain().focus().toggleItalic().run()"
        >
            <Italic class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            title="Sublinhado (Ctrl+U)"
            @click="editor?.chain().focus().toggleUnderline().run()"
        >
            <UnderlineIcon class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            title="Tachado"
            @click="editor?.chain().focus().toggleStrike().run()"
        >
            <Strikethrough class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            title="Subscrito"
            @click="editor?.chain().focus().toggleSubscript().run()"
        >
            <SubscriptIcon class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            title="Sobrescrito"
            @click="editor?.chain().focus().toggleSuperscript().run()"
        >
            <SuperscriptIcon class="size-4" />
        </button>

        <!-- 5. Paleta de Cores Padrões de Texto (Rosa padrão + 7 cores) -->
        <div ref="textColorContainerRef" class="relative">
            <div
                class="inline-flex h-8 items-center overflow-hidden rounded-md border border-border bg-background"
            >
                <button
                    type="button"
                    data-test="apply-text-color"
                    class="inline-flex h-full w-7 flex-col items-center justify-center gap-0.5 transition hover:bg-muted"
                    title="Aplicar cor da fonte"
                    @click="applyTextColor()"
                >
                    <span class="text-xs leading-none font-bold">A</span>
                    <span
                        class="h-1 w-4 rounded-full"
                        :style="{ backgroundColor: selectedTextColor }"
                    />
                </button>
                <button
                    type="button"
                    data-test="toggle-text-color-palette"
                    class="inline-flex h-full w-4 items-center justify-center border-l border-border text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    title="Escolher cor do texto"
                    @click="
                        showTextColorPalette = !showTextColorPalette;
                        showHighlightPalette = false;
                    "
                >
                    <ChevronDown class="size-3" />
                </button>
            </div>
            <div
                v-if="showTextColorPalette"
                data-test="text-color-palette"
                class="absolute top-full left-0 z-30 mt-1 w-48 rounded-lg border bg-popover p-2 shadow-xl"
            >
                <p
                    class="mb-1.5 text-[11px] font-semibold text-muted-foreground"
                >
                    Cor da fonte
                </p>
                <div class="grid grid-cols-7 gap-1">
                    <button
                        v-for="color in textColors"
                        :key="color.value"
                        type="button"
                        :title="color.label"
                        class="size-5 rounded border border-border/80 shadow-2xs transition-transform hover:scale-110"
                        :class="
                            selectedTextColor === color.value
                                ? 'ring-2 ring-primary ring-offset-1'
                                : ''
                        "
                        :style="{ backgroundColor: color.value }"
                        @click="applyTextColor(color.value)"
                    />
                </div>
                <button
                    type="button"
                    class="mt-2 w-full rounded border px-2 py-1 text-[11px] text-muted-foreground transition hover:bg-muted"
                    @click="unsetTextColor"
                >
                    Padrão
                </button>
            </div>
        </div>

        <!-- 6. Paleta de Cores Padrões de Fundo / Destaque (Rosa padrão + 6 cores) -->
        <div ref="highlightColorContainerRef" class="relative">
            <div
                class="inline-flex h-8 items-center overflow-hidden rounded-md border border-border bg-background"
            >
                <button
                    type="button"
                    data-test="apply-highlight-color"
                    class="inline-flex h-full w-7 flex-col items-center justify-center gap-0.5 transition hover:bg-muted"
                    title="Aplicar cor de destaque / fundo"
                    @click="applyHighlightColor()"
                >
                    <Highlighter class="size-3.5" />
                    <span
                        class="h-1 w-4 rounded-full"
                        :style="{ backgroundColor: selectedHighlightColor }"
                    />
                </button>
                <button
                    type="button"
                    data-test="toggle-highlight-color-palette"
                    class="inline-flex h-full w-4 items-center justify-center border-l border-border text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    title="Escolher cor de destaque / fundo"
                    @click="
                        showHighlightPalette = !showHighlightPalette;
                        showTextColorPalette = false;
                    "
                >
                    <ChevronDown class="size-3" />
                </button>
            </div>
            <div
                v-if="showHighlightPalette"
                data-test="highlight-color-palette"
                class="absolute top-full left-0 z-30 mt-1 w-44 rounded-lg border bg-popover p-2 shadow-xl"
            >
                <p
                    class="mb-1.5 text-[11px] font-semibold text-muted-foreground"
                >
                    Destaque de fundo
                </p>
                <div class="grid grid-cols-6 gap-1">
                    <button
                        v-for="color in highlightColors"
                        :key="color.value"
                        type="button"
                        :title="color.label"
                        class="size-5 rounded border border-border/80 shadow-2xs transition-transform hover:scale-110"
                        :class="
                            selectedHighlightColor === color.value
                                ? 'ring-2 ring-primary ring-offset-1'
                                : ''
                        "
                        :style="{ backgroundColor: color.value }"
                        @click="applyHighlightColor(color.value)"
                    />
                </div>
                <button
                    type="button"
                    class="mt-2 w-full rounded border px-2 py-1 text-[11px] text-muted-foreground transition hover:bg-muted"
                    @click="unsetHighlightColor"
                >
                    Sem destaque
                </button>
            </div>
        </div>

        <button
            type="button"
            class="editor-action"
            title="Limpar formatação"
            @click="editor?.chain().focus().unsetAllMarks().clearNodes().run()"
        >
            <Eraser class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            title="Desfazer (Ctrl+Z)"
            @click="editor?.chain().focus().undo().run()"
        >
            <Undo2 class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            title="Refazer (Ctrl+Y)"
            @click="editor?.chain().focus().redo().run()"
        >
            <Redo2 class="size-4" />
        </button>

        <div class="mx-1 h-5 w-px bg-border/60" />

        <!-- 7. Menu de Parâmetros com Rolagem e 2 Colunas -->
        <details class="relative">
            <summary
                class="editor-select flex cursor-pointer items-center gap-1 select-none"
            >
                Parâmetros <ChevronDown class="size-3" />
            </summary>
            <div
                class="absolute top-full left-0 z-20 mt-1 flex max-h-72 w-80 flex-col rounded-xl border bg-popover p-2.5 shadow-xl"
            >
                <p
                    class="mb-1.5 text-[11px] font-semibold text-muted-foreground"
                >
                    Selecione os parâmetros:
                </p>
                <div
                    class="grid max-h-52 grid-cols-2 gap-1 overflow-y-auto pr-1"
                >
                    <label
                        v-for="parameter in context?.parameters"
                        :key="parameter.id"
                        class="flex cursor-pointer items-center gap-1.5 rounded p-1 text-xs text-foreground transition hover:bg-muted"
                        :title="parameter.name"
                    >
                        <input
                            v-model="selectedInlineParameterIds"
                            type="checkbox"
                            :value="parameter.id"
                            class="shrink-0"
                        />
                        <span class="truncate">{{ parameter.name }}</span>
                    </label>
                </div>
                <div class="mt-2 border-t pt-2">
                    <button
                        type="button"
                        class="w-full rounded-lg bg-blue-600 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-blue-700 disabled:opacity-50"
                        :disabled="!selectedInlineParameterIds.length"
                        @click="emit('insertParameters')"
                    >
                        Inserir selecionados
                    </button>
                </div>
            </div>
        </details>

        <!-- 8. Inserir Imagem Externa (Botão de Upload) -->
        <button
            type="button"
            class="editor-action"
            title="Inserir imagem do computador (ou cole com Ctrl+V / arraste)"
            @click="imageInputRef?.click()"
        >
            <ImageIcon class="size-4" />
        </button>
        <input
            ref="imageInputRef"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleImageFileSelected"
        />

        <!-- 9. Gráficos -->
        <button
            type="button"
            class="editor-select"
            title="Inserir novo gráfico personalizado"
            @click="emit('prepareChartDialog')"
        >
            <BarChart3 class="size-3" /> Novo gráfico
        </button>
        <button
            type="button"
            class="editor-select"
            title="Atualizar período dos gráficos existentes"
            @click="emit('openChartUpdater')"
        >
            <RefreshCw class="size-3" /> Atualizar gráficos
        </button>

        <!-- 10. Alternar Flutuar / Fixar -->
        <button
            v-if="showFloatingToggle"
            type="button"
            data-test="toggle-floating-editor"
            class="editor-select ml-auto"
            :class="
                isFloating ? 'border-primary/40 bg-primary/10 text-primary' : ''
            "
            :title="
                isFloating
                    ? 'Fixar editor no bloco do sistema'
                    : 'Flutuar editor de texto'
            "
            @click="emit('toggleFloating')"
        >
            <Pin v-if="isFloating" class="size-3.5" />
            <Move v-else class="size-3.5" />
            <span>{{ isFloating ? 'Fixar' : 'Flutuar' }}</span>
        </button>
    </div>
</template>

<style scoped>
.editor-action {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    background: var(--background);
    transition: background-color 0.15s ease;
}
.editor-select {
    display: inline-flex;
    height: 2rem;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    background: var(--background);
    padding: 0 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    transition: background-color 0.15s ease;
}
.editor-action:hover,
.editor-select:hover {
    background: var(--muted);
}
</style>
