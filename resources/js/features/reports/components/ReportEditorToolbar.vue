<script setup lang="ts">
import {
    AlertTriangle,
    BarChart3,
    Bold,
    Check,
    ChevronDown,
    Copy,
    Eraser,
    Info,
    Italic,
    Maximize2,
    Redo2,
    RefreshCw,
    Sparkles,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Underline as UnderlineIcon,
    Undo2,
    X,
} from '@lucide/vue';
import type { Editor } from '@tiptap/vue-3';
import type { ReportContext } from '@/features/reports/types';

const hideData = defineModel<boolean>('hideData', { required: true });
const isStopped = defineModel<boolean>('isStopped', { required: true });
const selectedInlineParameterIds = defineModel<number[]>(
    'selectedInlineParameterIds',
    { required: true },
);
const showLatestChoice = defineModel<boolean>('showLatestChoice', {
    required: true,
});

defineProps<{
    editor?: Editor;
    context: ReportContext | null;
}>();

const emit = defineEmits<{
    insertCheckPhrase: [];
    insertIcon: [kind: 'error' | 'warning' | 'success' | 'info'];
    insertParameters: [];
    useLatest: [];
    applyLatest: [mode: 'append' | 'replace'];
    loadHistory: [];
    togglePhrases: [];
    openAgent: [];
    prepareChartDialog: [];
    openChartUpdater: [];
}>();

function formatBrazilianDate(value: string): string {
    if (!value) {
        return '';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}
</script>

<template>
    <div class="flex flex-wrap gap-1 border-b p-2">
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
            @click="emit('insertIcon', 'error')"
        >
            <X class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action text-amber-500"
            @click="emit('insertIcon', 'warning')"
        >
            <AlertTriangle class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action text-emerald-600"
            @click="emit('insertIcon', 'success')"
        >
            <Check class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action text-blue-600"
            @click="emit('insertIcon', 'info')"
        >
            <Info class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().toggleBold().run()"
        >
            <Bold class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().toggleItalic().run()"
        >
            <Italic class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().toggleUnderline().run()"
        >
            <UnderlineIcon class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().toggleStrike().run()"
        >
            <Strikethrough class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().toggleSubscript().run()"
        >
            <SubscriptIcon class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().toggleSuperscript().run()"
        >
            <SuperscriptIcon class="size-4" />
        </button>
        <select
            class="editor-select"
            @change="
                editor
                    ?.chain()
                    .focus()
                    .setFontFamily(($event.target as HTMLSelectElement).value)
                    .run()
            "
        >
            <option value="Instrument Sans">Fonte</option>
            <option value="Arial">Arial</option>
            <option value="Georgia">Georgia</option>
            <option value="monospace">Mono</option>
        </select>
        <select
            class="editor-select"
            @change="
                editor
                    ?.chain()
                    .focus()
                    .setFontSize(($event.target as HTMLSelectElement).value)
                    .run()
            "
        >
            <option value="14px">Tamanho</option>
            <option value="12px">12</option>
            <option value="16px">16</option>
            <option value="20px">20</option>
            <option value="28px">28</option>
        </select>
        <input
            type="color"
            class="h-8 w-8 rounded border"
            title="Cor do texto"
            @input="
                editor
                    ?.chain()
                    .focus()
                    .setColor(($event.target as HTMLInputElement).value)
                    .run()
            "
        />
        <input
            type="color"
            value="#ffff00"
            class="h-8 w-8 rounded border"
            title="Destaque"
            @input="
                editor
                    ?.chain()
                    .focus()
                    .toggleHighlight({
                        color: ($event.target as HTMLInputElement).value,
                    })
                    .run()
            "
        />
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().unsetAllMarks().clearNodes().run()"
        >
            <Eraser class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().undo().run()"
        >
            <Undo2 class="size-4" />
        </button>
        <button
            type="button"
            class="editor-action"
            @click="editor?.chain().focus().redo().run()"
        >
            <Redo2 class="size-4" />
        </button>

        <details class="relative">
            <summary
                class="editor-select flex cursor-pointer items-center gap-1"
            >
                Parâmetros <ChevronDown class="size-3" />
            </summary>
            <div
                class="absolute z-20 mt-1 w-64 rounded-md border bg-popover p-2 shadow-xl"
            >
                <label
                    v-for="parameter in context?.parameters"
                    :key="parameter.id"
                    class="flex gap-2 py-1 text-xs text-foreground"
                >
                    <input
                        v-model="selectedInlineParameterIds"
                        type="checkbox"
                        :value="parameter.id"
                    />{{ parameter.name }}
                </label>
                <button
                    type="button"
                    class="mt-2 w-full rounded bg-blue-600 py-1 text-xs text-white"
                    @click="emit('insertParameters')"
                >
                    Inserir
                </button>
            </div>
        </details>

        <div class="relative">
            <button
                type="button"
                class="editor-select"
                @click="emit('useLatest')"
            >
                Último comentário
                <span v-if="context?.latest_comment">
                    ({{
                        formatBrazilianDate(
                            context.latest_comment.date_reference,
                        )
                    }})
                </span>
            </button>
            <div
                v-if="showLatestChoice"
                class="absolute top-full left-0 z-30 mt-2 w-72 rounded-xl border bg-popover p-3 text-xs shadow-xl"
                data-test="latest-comment-choice"
            >
                <p class="font-semibold text-foreground">
                    Como deseja inserir?
                </p>
                <p class="mt-1 text-muted-foreground">
                    O editor já possui conteúdo.
                </p>
                <div class="mt-3 grid gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 font-medium text-white hover:bg-blue-700"
                        @click="emit('applyLatest', 'append')"
                    >
                        <Copy class="size-3.5" /> Adicionar ao final
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-300 px-3 py-2 font-medium text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30"
                        @click="emit('applyLatest', 'replace')"
                    >
                        <RefreshCw class="size-3.5" /> Sobrescrever editor
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-muted-foreground hover:bg-muted"
                        @click="showLatestChoice = false"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>

        <button
            type="button"
            class="editor-select"
            @click="emit('loadHistory')"
        >
            Relatórios anteriores
        </button>
        <button
            type="button"
            class="editor-select"
            @click="emit('togglePhrases')"
        >
            Frases / agrupamento
        </button>
        <button type="button" class="editor-select" @click="emit('openAgent')">
            <Sparkles class="size-3 text-violet-500" /> Agente
        </button>
        <button
            type="button"
            class="editor-select"
            @click="emit('prepareChartDialog')"
        >
            <BarChart3 class="size-3" /> Novo gráfico
        </button>
        <button
            type="button"
            class="editor-select"
            @click="emit('openChartUpdater')"
        >
            <Maximize2 class="size-3" /> Atualizar gráficos
        </button>
        <label class="editor-select text-foreground">
            <input v-model="hideData" type="checkbox" /> Ocultar dados
        </label>
        <label class="editor-select text-foreground">
            <input v-model="isStopped" type="checkbox" /> Sistema parado
        </label>
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
    border-radius: 0.25rem;
    background: var(--background);
}
.editor-select {
    display: inline-flex;
    height: 2rem;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid var(--border);
    border-radius: 0.25rem;
    background: var(--background);
    padding: 0 0.5rem;
    font-size: 0.75rem;
}
.editor-action:hover,
.editor-select:hover {
    background: var(--muted);
}
</style>
