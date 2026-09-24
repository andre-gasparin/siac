<script setup lang="ts">
import {
    Bot,
    CircleHelp,
    Database,
    Loader2,
    Minus,
    Move,
    RotateCcw,
    Send,
    Sparkles,
    X,
} from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { store as assistantRoute } from '@/routes/reports/assistant';

interface AgentActivity {
    tool: string;
    label: string;
}

interface AgentMessage {
    role: 'user' | 'assistant';
    content: string;
    suggestionDocument?: Record<string, unknown> | null;
    suggestionPreview?: string | null;
    activity?: AgentActivity[];
}

const props = defineProps<{
    open: boolean;
    teamSlug: string;
    date: string;
    systemId: number | null;
    systemName?: string;
    currentText: string;
}>();

const emit = defineEmits<{
    close: [];
    insert: [document: Record<string, unknown>, replace: boolean];
}>();

const messages = ref<AgentMessage[]>([]);
const prompt = ref('');
const isSending = ref(false);
const isMinimized = ref(false);
const showHelp = ref(false);
const position = ref({ x: Math.max(16, window.innerWidth - 470), y: 96 });
const promptSuggestions = [
    'Faça o comentário do relatório',
    'Melhorar comentário sem perder o sentido',
    'Correção ortográfica',
    'Quais suas funções?',
    'Analise os parâmetros e destaque possíveis alertas',
    'Compare os dados atuais com os comentários anteriores',
    'Resuma o comportamento dos últimos 7 dias',
];

function resetConversation() {
    messages.value = [];
    prompt.value = '';
    showHelp.value = false;
}

const agentCapabilities = [
    'Criar um comentário completo seguindo o padrão dos relatórios anteriores.',
    'Consultar medições, médias, mínimos, máximos e alertas dos parâmetros.',
    'Pesquisar sistemas e parâmetros disponíveis na empresa.',
    'Consultar comentários e relatórios anteriores.',
    'Preparar sugestões com ícone, parâmetros em negrito e gráfico editável.',
    'Adicionar a sugestão ao conteúdo atual ou sobrescrever o editor.',
];

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function send() {
    const content = prompt.value.trim();

    if (!content || !props.systemId || isSending.value) {
        return;
    }

    messages.value.push({ role: 'user', content });
    prompt.value = '';
    isSending.value = true;

    try {
        const response = await fetch(
            assistantRoute.url({ current_team: props.teamSlug }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    date_reference: props.date,
                    system_id: props.systemId,
                    current_text: props.currentText,
                    messages: messages.value.map(({ role, content: text }) => ({
                        role,
                        content: text,
                    })),
                }),
            },
        );
        const data = response.ok
            ? await response.json()
            : {
                  message: 'O agente está indisponível no momento.',
                  suggestion_document: null,
                  suggestion_preview: null,
                  activity: [],
              };
        messages.value.push({
            role: 'assistant',
            content: data.message,
            suggestionDocument: data.suggestion_document,
            suggestionPreview: data.suggestion_preview,
            activity: data.activity,
        });
    } finally {
        isSending.value = false;
    }
}

function formatBrazilianDate(date: string): string {
    const [year, month, day] = date.split('-');

    return year && month && day ? `${day}/${month}/${year}` : date;
}

function usePromptSuggestion(suggestion: string) {
    prompt.value = suggestion;
}

function startDrag(event: PointerEvent) {
    if ((event.target as HTMLElement).closest('button, textarea')) {
        return;
    }

    const origin = { ...position.value };
    const startX = event.clientX;
    const startY = event.clientY;
    const move = (moveEvent: PointerEvent) => {
        position.value.x = Math.max(
            8,
            Math.min(
                window.innerWidth - 420,
                origin.x + moveEvent.clientX - startX,
            ),
        );
        position.value.y = Math.max(
            8,
            Math.min(
                window.innerHeight - 80,
                origin.y + moveEvent.clientY - startY,
            ),
        );
    };
    const stop = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', stop);
        localStorage.setItem(
            'reports-agent-position',
            JSON.stringify(position.value),
        );
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', stop);
}

onMounted(() => {
    const saved = localStorage.getItem('reports-agent-position');

    if (saved) {
        position.value = { ...position.value, ...JSON.parse(saved) };
    }
});
</script>

<template>
    <button
        v-if="open && isMinimized"
        type="button"
        class="fixed right-5 bottom-5 z-[120] grid size-13 place-items-center rounded-full bg-[#1d6699] text-white shadow-xl ring-4 ring-white/70 transition hover:scale-105 hover:bg-[#18577f] focus-visible:ring-2 focus-visible:ring-[#1d6699]/40 focus-visible:outline-none dark:ring-slate-950/70"
        title="Abrir agente de relatórios"
        aria-label="Abrir agente de relatórios"
        data-test="restore-report-agent"
        @click="isMinimized = false"
    >
        <Bot class="size-6" />
    </button>

    <aside
        v-if="open && !isMinimized"
        class="fixed z-[120] w-[min(420px,calc(100vw-16px))] overflow-hidden rounded-2xl border border-[#1d6699]/25 bg-card shadow-2xl dark:border-[#1d6699]/60"
        :style="{ left: `${position.x}px`, top: `${position.y}px` }"
        data-test="report-agent"
    >
        <header
            class="flex cursor-move items-center justify-between bg-[#1d6699] px-3 py-2 text-white select-none"
            @pointerdown="startDrag"
        >
            <div class="flex items-center gap-2.5">
                <span
                    class="grid size-7 place-items-center rounded-lg bg-white/15"
                >
                    <Bot class="size-4" />
                </span>
                <div>
                    <p class="text-xs font-semibold">Agente de relatórios</p>
                    <p class="text-[10px] text-blue-100">
                        {{ systemName }} · {{ formatBrazilianDate(date) }}
                    </p>
                </div>
            </div>
            <div class="relative flex items-center gap-1">
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-lg bg-white/10 px-2 py-1 text-[10px] font-medium text-white transition hover:bg-white/20"
                    title="Iniciar nova conversa do zero"
                    aria-label="Nova conversa"
                    data-test="report-agent-new-conversation"
                    @pointerdown.stop
                    @click="resetConversation"
                >
                    <RotateCcw class="size-3" />
                    <span>Nova conversa</span>
                </button>
                <button
                    type="button"
                    class="rounded-lg p-1.5 hover:bg-white/15"
                    title="O que o agente pode fazer?"
                    aria-label="Ver funções do agente"
                    :aria-expanded="showHelp"
                    @pointerdown.stop
                    @click="showHelp = !showHelp"
                >
                    <CircleHelp class="size-4" />
                </button>
                <button
                    type="button"
                    class="rounded-lg p-1.5 hover:bg-white/15"
                    title="Minimizar"
                    @pointerdown.stop
                    @click="
                        showHelp = false;
                        isMinimized = true;
                    "
                >
                    <Minus class="size-4" />
                </button>
                <button
                    type="button"
                    class="rounded-lg p-1.5 hover:bg-white/15"
                    title="Fechar"
                    @pointerdown.stop
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
                <div
                    v-if="showHelp"
                    class="absolute top-full right-0 z-20 mt-2 w-80 rounded-xl border border-[#1d6699]/20 bg-popover p-4 text-foreground shadow-2xl"
                    data-test="report-agent-help"
                    @pointerdown.stop
                >
                    <div class="mb-3 flex items-start gap-2">
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-lg bg-[#1d6699]/10 text-[#1d6699]"
                        >
                            <CircleHelp class="size-4" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold">
                                Funções do agente
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Ele pesquisa dados antes de preparar o texto.
                            </p>
                        </div>
                    </div>
                    <ul class="grid gap-2">
                        <li
                            v-for="capability in agentCapabilities"
                            :key="capability"
                            class="flex gap-2 text-[11px] leading-relaxed"
                        >
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#1d6699]"
                            />
                            {{ capability }}
                        </li>
                    </ul>
                    <p
                        class="mt-3 rounded-lg bg-muted/50 px-3 py-2 text-[10px] text-muted-foreground"
                    >
                        A conversa é temporária. Somente o conteúdo inserido no
                        editor é salvo.
                    </p>
                </div>
            </div>
        </header>

        <div class="max-h-[380px] min-h-48 space-y-3 overflow-y-auto p-4">
            <div
                v-if="messages.length === 0"
                class="grid min-h-40 place-items-center"
            >
                <div class="w-full">
                    <div class="text-center">
                        <Sparkles class="mx-auto mb-2 size-8 text-[#1d6699]" />
                        <p class="text-sm font-medium">
                            Posso investigar os dados desta empresa
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Comece por uma destas sugestões:
                        </p>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <button
                            v-for="suggestion in promptSuggestions"
                            :key="suggestion"
                            type="button"
                            class="rounded-xl border border-[#1d6699]/20 bg-[#1d6699]/5 px-3 py-2.5 text-left text-xs font-medium transition hover:border-[#1d6699]/40 hover:bg-[#1d6699]/10"
                            @click="usePromptSuggestion(suggestion)"
                        >
                            {{ suggestion }}
                        </button>
                    </div>
                </div>
            </div>

            <article
                v-for="(message, index) in messages"
                :key="index"
                class="flex"
                :class="
                    message.role === 'user' ? 'justify-end' : 'justify-start'
                "
            >
                <div
                    class="max-w-[90%] rounded-2xl px-3 py-2 text-sm"
                    :class="
                        message.role === 'user'
                            ? 'rounded-br-sm bg-[#1d6699] text-white'
                            : 'rounded-bl-sm border bg-muted/40'
                    "
                >
                    <div v-if="message.activity?.length" class="mb-2 space-y-1">
                        <p
                            v-for="activity in message.activity"
                            :key="`${index}-${activity.tool}`"
                            class="flex items-center gap-1.5 rounded-lg bg-blue-50 px-2 py-1 text-[11px] text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
                        >
                            <Database class="size-3" />
                            {{ activity.label }}
                        </p>
                    </div>
                    <p class="whitespace-pre-wrap">
                        {{ message.content }}
                    </p>
                    <div
                        v-if="
                            message.role === 'assistant' &&
                            message.suggestionDocument
                        "
                        class="mt-3 rounded-xl border bg-card p-2"
                    >
                        <p
                            class="mb-1 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Sugestão para o relatório
                        </p>
                        <p class="text-xs whitespace-pre-wrap">
                            {{ message.suggestionPreview }}
                        </p>
                        <div class="mt-2 flex gap-3 text-xs font-medium">
                            <button
                                class="text-blue-600 hover:underline"
                                @click="
                                    emit(
                                        'insert',
                                        message.suggestionDocument!,
                                        false,
                                    )
                                "
                            >
                                Adicionar
                            </button>
                            <button
                                class="text-red-600 hover:underline"
                                @click="
                                    emit(
                                        'insert',
                                        message.suggestionDocument!,
                                        true,
                                    )
                                "
                            >
                                Sobrescrever
                            </button>
                        </div>
                    </div>
                </div>
            </article>
            <div
                v-if="isSending"
                class="flex items-center gap-2 text-xs text-muted-foreground"
            >
                <Loader2 class="size-4 animate-spin text-[#1d6699]" />
                O agente está pesquisando…
            </div>
        </div>

        <footer class="border-t bg-muted/20 p-3">
            <div class="flex items-end gap-2">
                <textarea
                    v-model="prompt"
                    rows="2"
                    class="min-h-11 flex-1 resize-none rounded-xl border bg-background px-3 py-2 text-sm"
                    placeholder="Pergunte sobre os dados da empresa..."
                    @keydown.enter.exact.prevent="send"
                />
                <button
                    class="grid size-11 place-items-center rounded-xl bg-[#1d6699] text-white shadow-sm hover:bg-[#18577f] disabled:opacity-50"
                    :disabled="isSending || !prompt.trim()"
                    title="Enviar"
                    @click="send"
                >
                    <Loader2 v-if="isSending" class="size-4 animate-spin" />
                    <Send v-else class="size-4" />
                </button>
            </div>
            <p
                class="mt-1 flex items-center gap-1 text-[10px] text-muted-foreground"
            >
                <Move class="size-3" /> Arraste pelo cabeçalho · Enter para
                enviar
            </p>
        </footer>
    </aside>
</template>
