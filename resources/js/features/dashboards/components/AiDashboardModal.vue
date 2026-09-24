<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    Check,
    FileText,
    Loader2,
    Sparkles,
    X,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import dashboardsRoutes from '@/routes/dashboards';

interface ComponentSpec {
    type: 'indicator' | 'text' | 'chart';
    title: string;
    grid_config: { x: number; y: number; w: number; h: number };
    settings: Record<string, any>;
}

interface DashboardSuggestion {
    id: string;
    title: string;
    description: string;
    component_count_label: string;
    components: ComponentSpec[];
}

const props = defineProps<{
    show: boolean;
    currentTeamSlug: string;
}>();

const emit = defineEmits(['close']);

const step = ref<1 | 2>(1);
const objective = ref('');
const loading = ref(false);
const creatingId = ref<string | null>(null);
const error = ref<string | null>(null);

const suggestions = ref<DashboardSuggestion[]>([]);

async function fetchSuggestions() {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(
            dashboardsRoutes.aiSuggest.url({
                current_team: props.currentTeamSlug,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    objective: objective.value,
                }),
            },
        );

        if (!response.ok) {
            throw new Error('Falha ao gerar sugestões da IA.');
        }

        const data = await response.json();
        suggestions.value = data.suggestions || [];
        step.value = 2;
    } catch (e: any) {
        error.value = e.message || 'Ocorreu um erro ao conectar à IA.';
    } finally {
        loading.value = false;
    }
}

async function useDashboard(sug: DashboardSuggestion) {
    creatingId.value = sug.id;
    error.value = null;

    try {
        const response = await fetch(
            dashboardsRoutes.aiCreate.url({
                current_team: props.currentTeamSlug,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    title: sug.title,
                    is_public: false,
                    components: sug.components,
                }),
            },
        );

        if (response.ok) {
            const res = await response.json();
            emit('close');

            if (res.redirect_url) {
                router.visit(res.redirect_url, {
                    preserveState: true,
                    preserveScroll: true,
                });
            } else {
                throw new Error('Dashboard criado sem URL de destino.');
            }
        } else {
            throw new Error('Erro ao criar dashboard.');
        }
    } catch (e: any) {
        error.value = e.message || 'Erro ao instanciar o modelo.';
    } finally {
        creatingId.value = null;
    }
}

function resetModal() {
    step.value = 1;
    objective.value = '';
    suggestions.value = [];
    error.value = null;
}

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            resetModal();
        }
    },
);
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md transition-all select-none"
    >
        <!-- Modal Container (Dark sleek design matched to UI screenshot) -->
        <div
            class="relative max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-slate-800 bg-[#090d16] p-6 text-white shadow-2xl md:p-8"
        >
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/20"
                    >
                        <Sparkles class="size-5" />
                    </div>
                    <div>
                        <h2
                            class="text-xl font-bold tracking-tight text-white md:text-2xl"
                        >
                            {{
                                step === 1
                                    ? 'Criar Dashboard com IA'
                                    : 'Sugestões da IA'
                            }}
                        </h2>
                        <p class="text-xs text-slate-400 md:text-sm">
                            {{
                                step === 1
                                    ? 'Descreva o foco do dashboard ou deixe em branco para sugestões baseadas no seu time.'
                                    : 'Cada opção abre no editor com seus componentes configuráveis.'
                            }}
                        </p>
                    </div>
                </div>

                <button
                    @click="emit('close')"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-800 hover:text-white"
                >
                    <X class="size-5" />
                </button>
            </div>

            <!-- Error Banner -->
            <div
                v-if="error"
                class="mt-4 rounded-xl border border-red-500/30 bg-red-950/40 p-3 text-xs text-red-300"
            >
                {{ error }}
            </div>

            <!-- STEP 1: Prompt Input -->
            <div v-if="step === 1" class="mt-6 space-y-5">
                <div>
                    <label
                        class="block text-xs font-semibold tracking-wider text-slate-300 uppercase"
                    >
                        Objetivo do Dashboard
                    </label>
                    <p class="mt-1 text-xs text-slate-400">
                        Informe o que você deseja analisar (ex: "Análise de
                        caldeiras", "Entrada de água", "Eficiência diária").
                    </p>
                    <textarea
                        v-model="objective"
                        rows="3"
                        placeholder="Ex: Análise de caldeiras (para analisar todas as caldeiras), controle de vazão de água e indicadores operacionais..."
                        class="mt-2.5 w-full rounded-xl border border-slate-800 bg-slate-900/90 p-3.5 text-sm text-white placeholder-slate-500 shadow-inner focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                    ></textarea>
                </div>

                <div
                    class="flex items-center justify-end gap-3 border-t border-slate-800/80 pt-4"
                >
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-2 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="fetchSuggestions"
                        :disabled="loading"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-2.5 text-xs font-bold text-slate-950 shadow-lg shadow-amber-500/25 transition hover:from-amber-400 hover:to-orange-400 disabled:opacity-50"
                    >
                        <Loader2 v-if="loading" class="size-4 animate-spin" />
                        <Sparkles v-else class="size-4" />
                        {{ loading ? 'Gerando sugestões...' : 'Gerar com IA' }}
                    </button>
                </div>
            </div>

            <!-- STEP 2: Loading State Skeleton -->
            <div
                v-else-if="loading"
                class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3"
            >
                <div
                    v-for="i in 3"
                    :key="i"
                    class="animate-pulse space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-6"
                >
                    <div class="size-8 rounded-lg bg-slate-800"></div>
                    <div class="h-6 w-3/4 rounded bg-slate-800"></div>
                    <div class="h-4 w-1/2 rounded bg-slate-800/60"></div>
                    <div class="space-y-2 pt-4">
                        <div
                            v-for="j in 5"
                            :key="j"
                            class="h-3 w-full rounded bg-slate-800/40"
                        ></div>
                    </div>
                    <div class="h-10 w-full rounded-xl bg-slate-800 pt-2"></div>
                </div>
            </div>

            <!-- STEP 2: Suggestions Grid (Matches attached user image) -->
            <div v-else-if="step === 2" class="mt-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div
                        v-for="sug in suggestions"
                        :key="sug.id"
                        class="flex flex-col justify-between rounded-2xl border border-slate-800 bg-[#0d1322] p-5 shadow-xl transition-all hover:border-amber-500/50"
                    >
                        <div>
                            <!-- Sparkles icon header -->
                            <div class="mb-3 text-amber-400">
                                <Sparkles class="size-6" />
                            </div>

                            <!-- Card Title & Subtitle -->
                            <h3
                                class="text-base leading-snug font-bold text-white"
                            >
                                {{ sug.title }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ sug.component_count_label }}
                            </p>

                            <!-- Components List -->
                            <ul
                                class="mt-4 max-h-64 scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent space-y-2.5 overflow-y-auto border-t border-slate-800/60 pt-4 pr-1 md:max-h-72"
                            >
                                <li
                                    v-for="(comp, cIdx) in sug.components"
                                    :key="cIdx"
                                    class="flex items-center gap-2 text-xs text-slate-200"
                                >
                                    <span class="shrink-0 text-amber-400">
                                        <Activity
                                            v-if="comp.type === 'indicator'"
                                            class="size-3.5"
                                        />
                                        <BarChart3
                                            v-else-if="comp.type === 'chart'"
                                            class="size-3.5 text-blue-400"
                                        />
                                        <FileText
                                            v-else
                                            class="size-3.5 text-slate-400"
                                        />
                                    </span>
                                    <span class="truncate">{{
                                        comp.title
                                    }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Card Action Button -->
                        <div class="mt-6 pt-2">
                            <button
                                type="button"
                                @click="useDashboard(sug)"
                                :disabled="creatingId !== null"
                                class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-bold text-slate-950 shadow-md shadow-amber-500/20 transition-colors hover:bg-amber-400 disabled:opacity-50"
                            >
                                <Loader2
                                    v-if="creatingId === sug.id"
                                    class="size-4 animate-spin"
                                />
                                <template v-else>
                                    Usar painel
                                    <Check class="size-4 stroke-[3]" />
                                </template>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer Action: Alterar instrução -->
                <div
                    class="mt-6 flex items-center justify-between border-t border-slate-800/80 pt-4"
                >
                    <button
                        type="button"
                        @click="step = 1"
                        class="text-xs font-medium text-slate-400 transition hover:text-white"
                    >
                        Alterar instrução
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
