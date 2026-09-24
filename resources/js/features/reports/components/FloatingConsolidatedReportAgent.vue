<script setup lang="ts">
import {
    BarChart3,
    Bot,
    Calendar,
    CheckCheck,
    CircleHelp,
    Database,
    History,
    Loader2,
    Minus,
    RotateCcw,
    Search,
    Send,
    Sparkles,
    Wand2,
    X,
} from '@lucide/vue';
import { nextTick, ref } from 'vue';
import type { Component } from 'vue';
import { store as storeSuggestions } from '@/routes/reports/suggestions';

export interface ConsolidatedSuggestion {
    id: number;
    report_item_id: number;
    system_id: number;
    system_name: string | null;
    mode: 'normal' | 'proofread';
    original_text: string;
    replacement_text: string;
    reason: string | null;
}

export interface AgentActivity {
    tool: string;
    label: string;
}

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    suggestions?: ConsolidatedSuggestion[];
    activity?: AgentActivity[];
}

interface QuickAction {
    title: string;
    subtitle: string;
    prompt: string;
    icon: Component;
    featured?: boolean;
}

const props = defineProps<{
    open: boolean;
    teamSlug: string;
    reportId: number;
    reportTitle: string;
}>();

const emit = defineEmits<{
    close: [];
    open: [];
    suggestions: [suggestions: ConsolidatedSuggestion[]];
    focus: [systemId: number];
}>();

const messages = ref<ChatMessage[]>([]);
const prompt = ref('');
const isSending = ref(false);
const isMinimized = ref(false);
const showHelp = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);

const quickActions: QuickAction[] = [
    {
        title: 'Faça o comentário das considerações',
        subtitle: 'Considera a data do relatório e apenas sistemas habilitados',
        prompt: 'Faça o comentário das considerações do relatório',
        icon: Sparkles,
        featured: true,
    },
    {
        title: 'Melhorar comentário sem perder o sentido',
        subtitle: 'Aprimora a redação seguindo o estilo histórico da empresa',
        prompt: 'Melhorar comentário sem perder o sentido',
        icon: Wand2,
        featured: true,
    },
    {
        title: 'Correção ortográfica',
        subtitle: 'Corrige ortografia, acentuação e concordância',
        prompt: 'Correção ortográfica nos comentários',
        icon: CheckCheck,
        featured: true,
    },
    {
        title: 'Quais suas funções?',
        subtitle: 'Lista detalhada de todas as capacidades do robô',
        prompt: 'Quais suas funções?',
        icon: CircleHelp,
        featured: true,
    },
    {
        title: 'Analisar parâmetros e alertas',
        subtitle: 'Destaque de medições fora do limite e médias',
        prompt: 'Analise os parâmetros de qualquer sistema e destaque possíveis alertas',
        icon: BarChart3,
    },
    {
        title: 'Comparar dados com comentários anteriores',
        subtitle: 'Cruzamento com histórico recente do sistema',
        prompt: 'Compare os dados atuais com os comentários anteriores',
        icon: History,
    },
    {
        title: 'Resumo dos últimos 7 dias',
        subtitle: 'Visão consolidada da semana para a empresa',
        prompt: 'Resuma o comportamento dos últimos 7 dias da empresa',
        icon: Calendar,
    },
    {
        title: 'Consultar catálogo de sistemas e parâmetros',
        subtitle: 'Listar parâmetros ativos e tags cadastradas',
        prompt: 'Pesquise o catálogo de sistemas e parâmetros ativos da empresa',
        icon: Search,
    },
];

const agentCapabilities = [
    'Elaborar comentários de considerações para sistemas habilitados considerando a data do relatório.',
    'Melhorar comentários existentes sem perder o sentido, mantendo o padrão e estilo histórico da empresa.',
    'Realizar correção ortográfica, acentuação e concordância nos comentários.',
    'Consultar medições, médias, mínimos e máximos de qualquer sistema no relatório.',
    'Pesquisar o catálogo de sistemas e parâmetros ativos da empresa.',
    'Buscar comentários históricos e relatórios anteriores.',
    'Exibir as consultas realizadas (atividades) em tempo real.',
    'Propor sugestões de revisão com prévia nos cartões dos sistemas.',
];

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function inferMode(content: string): 'normal' | 'proofread' {
    return /portugu|acentua|concord|ortograf|corre[cç][aã]o/i.test(content)
        ? 'proofread'
        : 'normal';
}

async function scrollMessages(): Promise<void> {
    await nextTick();
    messagesContainer.value?.scrollTo({
        top: messagesContainer.value.scrollHeight,
        behavior: 'smooth',
    });
}

function handleOpen(): void {
    isMinimized.value = false;
    emit('open');
}

function handleClose(): void {
    showHelp.value = false;
    isMinimized.value = true;
    emit('close');
}

function resetConversation(): void {
    messages.value = [];
    prompt.value = '';
    showHelp.value = false;
}

async function send(): Promise<void> {
    const content = prompt.value.trim();

    if (!content || isSending.value) {
        return;
    }

    messages.value.push({ role: 'user', content });
    prompt.value = '';
    isSending.value = true;
    await scrollMessages();

    try {
        const response = await fetch(
            storeSuggestions.url({
                current_team: props.teamSlug,
                report: props.reportId,
            }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    mode: inferMode(content),
                    instruction: content,
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
                  message:
                      'Não consegui analisar o relatório agora. Tente novamente.',
                  suggestions: [],
                  activity: [],
              };
        const suggestions = (data.suggestions ??
            []) as ConsolidatedSuggestion[];
        const activity = (data.activity ?? []) as AgentActivity[];

        messages.value.push({
            role: 'assistant',
            content: data.message,
            suggestions,
            activity,
        });

        if (suggestions.length > 0) {
            emit('suggestions', suggestions);
            emit('focus', suggestions[0].system_id);
        }
    } finally {
        isSending.value = false;
        await scrollMessages();
    }
}

async function useStarter(starterPrompt: string): Promise<void> {
    if (isSending.value) {
        return;
    }

    prompt.value = starterPrompt;
    await send();
}
</script>

<template>
    <!-- Botão Flutuante (Círculo no Canto Inferior Direito) -->
    <button
        v-if="!open || isMinimized"
        type="button"
        class="fixed right-6 bottom-6 z-[120] flex size-14 items-center justify-center rounded-full bg-linear-to-r from-violet-700 to-indigo-600 text-white shadow-2xl ring-4 ring-white/70 transition hover:scale-110 hover:shadow-violet-500/40 focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-hidden dark:ring-slate-950/80"
        title="Abrir agente do relatório"
        aria-label="Abrir agente do relatório"
        data-test="open-consolidated-report-agent"
        @click="handleOpen"
    >
        <Bot class="size-7" />
        <span class="absolute -top-0.5 -right-0.5 flex size-3.5">
            <span
                class="absolute inline-flex size-full animate-ping rounded-full bg-violet-400 opacity-75"
            />
            <span
                class="relative inline-flex size-3.5 rounded-full border-2 border-white bg-violet-500 dark:border-slate-950"
            />
        </span>
    </button>

    <!-- Chat Maximizado Flutuante -->
    <aside
        v-if="open && !isMinimized"
        class="fixed right-6 bottom-6 z-[120] flex max-h-[min(720px,calc(100vh-32px))] w-[min(460px,calc(100vw-32px))] flex-col overflow-hidden rounded-3xl border border-violet-300/60 bg-card shadow-2xl shadow-violet-950/25 dark:border-violet-800"
        data-test="consolidated-report-agent"
    >
        <header
            class="flex items-center justify-between bg-linear-to-r from-violet-700 to-indigo-600 px-4 py-3 text-white select-none"
        >
            <div class="flex min-w-0 items-center gap-2.5">
                <span
                    class="grid size-8 shrink-0 place-items-center rounded-xl bg-white/15"
                >
                    <Bot class="size-4.5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-xs leading-tight font-semibold sm:text-sm">
                        Agente do relatório
                    </h2>
                    <p class="truncate text-[11px] text-violet-100/90">
                        {{ reportTitle }}
                    </p>
                </div>
            </div>
            <div class="relative flex items-center gap-1">
                <!-- Botão Nova Conversa -->
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-lg bg-white/10 px-2 py-1 text-[11px] font-medium text-white transition hover:bg-white/20"
                    title="Iniciar nova conversa do zero"
                    aria-label="Nova conversa"
                    data-test="new-conversation-button"
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
                    @click="showHelp = !showHelp"
                >
                    <CircleHelp class="size-4" />
                </button>
                <button
                    type="button"
                    class="rounded-lg p-1.5 hover:bg-white/15"
                    title="Minimizar"
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
                    aria-label="Fechar agente"
                    @click="handleClose"
                >
                    <X class="size-4" />
                </button>

                <!-- Popover de Funções do Agente -->
                <div
                    v-if="showHelp"
                    class="absolute top-full right-0 z-30 mt-2 w-80 rounded-2xl border border-violet-200 bg-popover p-4 text-foreground shadow-2xl dark:border-violet-900"
                    data-test="consolidated-agent-help"
                >
                    <div class="mb-3 flex items-start gap-2.5">
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-lg bg-violet-500/15 text-violet-600 dark:text-violet-300"
                        >
                            <CircleHelp class="size-4" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold">
                                Funções do agente no relatório
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Ele pesquisa sistemas, parâmetros e histórico da
                                empresa.
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
                                class="mt-1.5 size-1.5 shrink-0 rounded-full bg-violet-600 dark:bg-violet-400"
                            />
                            {{ capability }}
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <div
            ref="messagesContainer"
            class="min-h-72 flex-1 space-y-3 overflow-y-auto bg-muted/10 p-3.5"
        >
            <!-- Tela Inicial com Cards Compactos e Scroll -->
            <div
                v-if="messages.length === 0"
                class="flex flex-col gap-2 py-1"
                data-test="agent-starters-container"
            >
                <div
                    v-for="action in quickActions"
                    :key="action.title"
                    class="group"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border p-2.5 text-left transition disabled:pointer-events-none disabled:opacity-60"
                        :class="
                            action.featured
                                ? 'border-violet-300/80 bg-linear-to-r from-violet-500/10 via-indigo-500/5 to-transparent hover:border-violet-500 hover:bg-violet-500/15 dark:border-violet-800/80 dark:from-violet-950/40 dark:hover:border-violet-600 dark:hover:bg-violet-950/60'
                                : 'border-border/70 bg-card/80 hover:border-violet-300 hover:bg-muted/50 dark:hover:border-violet-800'
                        "
                        :disabled="isSending"
                        @click="useStarter(action.prompt)"
                    >
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-xl transition"
                            :class="
                                action.featured
                                    ? 'bg-violet-600 text-white shadow-xs group-hover:scale-105'
                                    : 'bg-muted text-muted-foreground group-hover:bg-violet-100 group-hover:text-violet-700 dark:group-hover:bg-violet-950 dark:group-hover:text-violet-300'
                            "
                        >
                            <component :is="action.icon" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-semibold text-foreground group-hover:text-violet-700 dark:group-hover:text-violet-300"
                            >
                                {{ action.title }}
                            </p>
                            <p
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                {{ action.subtitle }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Lista de Mensagens do Chat -->
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
                            ? 'rounded-br-sm bg-violet-700 text-white'
                            : 'rounded-bl-sm border bg-card'
                    "
                >
                    <!-- Badges de Consultas/Atividades da IA -->
                    <div
                        v-if="message.activity?.length"
                        class="mb-2.5 space-y-1"
                    >
                        <p
                            v-for="(act, actIdx) in message.activity"
                            :key="`${index}-${actIdx}-${act.tool}`"
                            class="flex items-center gap-1.5 rounded-lg bg-violet-100/70 px-2 py-1 text-[11px] font-medium text-violet-800 dark:bg-violet-950/60 dark:text-violet-200"
                        >
                            <Database class="size-3 shrink-0" />
                            {{ act.label }}
                        </p>
                    </div>

                    <p class="whitespace-pre-wrap">{{ message.content }}</p>

                    <!-- Botões de Ação para Sugestões -->
                    <div
                        v-if="message.suggestions?.length"
                        class="mt-3 grid gap-1.5"
                    >
                        <button
                            v-for="suggestion in message.suggestions"
                            :key="suggestion.id"
                            type="button"
                            class="rounded-lg bg-violet-50 px-2.5 py-2 text-left text-[11px] font-medium text-violet-800 transition hover:bg-violet-100 dark:bg-violet-950/40 dark:text-violet-200"
                            @click="emit('focus', suggestion.system_id)"
                        >
                            Ver correção em {{ suggestion.system_name }}
                        </button>
                    </div>
                </div>
            </article>

            <div
                v-if="isSending"
                class="flex items-center gap-2 text-xs text-muted-foreground"
            >
                <Loader2 class="size-4 animate-spin text-violet-600" />
                O agente está pesquisando sistemas e dados…
            </div>
        </div>

        <footer class="border-t bg-card p-3">
            <div class="flex items-end gap-2">
                <textarea
                    v-model="prompt"
                    rows="2"
                    class="min-h-11 flex-1 resize-none rounded-xl border bg-background px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-hidden"
                    placeholder="Pergunte ou comande o agente sobre o relatório…"
                    @keydown.enter.exact.prevent="send"
                />
                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-xl bg-violet-700 text-white transition hover:bg-violet-800 disabled:opacity-50"
                    :disabled="isSending || !prompt.trim()"
                    aria-label="Enviar ao agente"
                    @click="send"
                >
                    <Loader2 v-if="isSending" class="size-4 animate-spin" />
                    <Send v-else class="size-4" />
                </button>
            </div>
            <p
                class="mt-1.5 flex items-center gap-1 text-[10px] text-muted-foreground"
            >
                <Sparkles class="size-3" /> Enter para enviar · 1 clique nas
                opções iniciais
            </p>
        </footer>
    </aside>
</template>
