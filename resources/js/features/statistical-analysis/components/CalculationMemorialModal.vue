<script setup lang="ts">
import {
    Activity,
    AlertTriangle,
    CheckCircle,
    Calculator,
    TrendingUp,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import type { AnalysisRow } from '@/features/statistical-analysis/types';

const isOpen = defineModel<boolean>('isOpen', { required: true });

defineProps<{
    row: AnalysisRow | null;
}>();

const activeTab = ref<'ewma' | 'cusum'>('ewma');
</script>

<template>
    <div
        v-if="isOpen && row"
        class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 backdrop-blur-xs fade-in"
    >
        <div
            class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border bg-card shadow-2xl"
        >
            <!-- Modal Header -->
            <div
                class="flex items-center justify-between border-b bg-muted/20 px-6 py-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Calculator class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-foreground">
                                Memorial de Cálculo Passo a Passo
                            </h3>
                            <span
                                class="rounded bg-primary/10 px-2 py-0.5 font-mono text-xs font-semibold text-primary"
                            >
                                Ponto #{{ row.index }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Data/Hora da Medição:
                            <strong class="font-mono text-foreground"
                                >{{ row.date }} {{ row.time }}</strong
                            >
                            • Valor Medido:
                            <strong class="font-mono text-sm text-primary">{{
                                row.raw_value
                            }}</strong>
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="isOpen = false"
                    class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Tabs Navigation -->
            <div class="flex gap-2 border-b bg-muted/30 px-6 pt-2">
                <button
                    type="button"
                    @click="activeTab = 'ewma'"
                    :class="[
                        'flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2 text-xs font-semibold transition-all',
                        activeTab === 'ewma'
                            ? 'rounded-t-lg border-primary bg-card/60 text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    <TrendingUp class="h-4 w-4" />
                    <span>EWMA (Média Ponderada Exponencialmente)</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'cusum'"
                    :class="[
                        'flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2 text-xs font-semibold transition-all',
                        activeTab === 'cusum'
                            ? 'rounded-t-lg border-primary bg-card/60 text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    <Activity class="h-4 w-4" />
                    <span>CUSUM (Soma Acumulada Tabular)</span>
                </button>
            </div>

            <!-- Modal Content Body -->
            <div class="flex-1 space-y-5 overflow-y-auto p-6 text-xs">
                <!-- ==================== EWMA TAB ==================== -->
                <div v-if="activeTab === 'ewma'" class="space-y-5">
                    <!-- Status Alert Banner -->
                    <div
                        :class="[
                            'flex items-center justify-between rounded-lg border p-3.5',
                            row.ewma.alert_level === 0
                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                : row.ewma.alert_level === 1
                                  ? 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                  : 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
                        ]"
                    >
                        <div class="flex items-center gap-2.5">
                            <CheckCircle
                                v-if="row.ewma.alert_level === 0"
                                class="h-5 w-5 shrink-0 text-emerald-500"
                            />
                            <AlertTriangle
                                v-else
                                class="h-5 w-5 shrink-0 text-amber-500"
                            />
                            <div>
                                <span class="block text-sm font-bold">
                                    {{
                                        row.ewma.alert_level === 0
                                            ? 'Ponto Sob Controle Estatístico'
                                            : `Alerta Disparado: Nível ${row.ewma.alert_level}`
                                    }}
                                </span>
                                <span class="text-[11px] opacity-90">
                                    {{
                                        row.ewma.alert_level === 0
                                            ? 'A média ponderada exponencial está dentro dos limites de controle especificados.'
                                            : `Limite ${row.ewma.violated_side === 'upper' ? 'superior (UCL)' : 'inferior (LCL)'} excedido.`
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Proximity to closest limit -->
                        <div class="text-right">
                            <span
                                class="block text-[10px] font-bold tracking-wider uppercase opacity-75"
                            >
                                Próximo Limite:
                                {{ row.ewma.closest_limit.target_name }}
                            </span>
                            <span class="font-mono text-sm font-bold">
                                {{
                                    row.ewma.closest_limit.margin >= 0
                                        ? `+${row.ewma.closest_limit.margin.toFixed(3)}`
                                        : row.ewma.closest_limit.margin.toFixed(
                                              3,
                                          )
                                }}
                                ({{
                                    row.ewma.closest_limit.margin >= 0
                                        ? 'margem'
                                        : 'excesso'
                                }})
                            </span>
                        </div>
                    </div>

                    <!-- Formula & Arithmetic Substitution -->
                    <div class="space-y-3 rounded-lg border bg-muted/20 p-4">
                        <div
                            class="flex items-center justify-between border-b pb-2"
                        >
                            <span class="font-semibold text-foreground"
                                >Fórmula de Recorrência Teórica</span
                            >
                            <code
                                class="rounded bg-muted px-2 py-0.5 font-mono text-[11px] font-bold text-primary"
                            >
                                {{ row.ewma.memorial.formula_str }}
                            </code>
                        </div>

                        <div class="space-y-1.5">
                            <span
                                class="block text-[11px] font-semibold text-muted-foreground"
                            >
                                Substituição Aritmética Etapa por Etapa:
                            </span>
                            <div
                                class="overflow-x-auto rounded-md border bg-background p-3 font-mono text-xs font-semibold text-foreground shadow-2xs"
                            >
                                {{ row.ewma.memorial.substitution_str }}
                            </div>
                        </div>
                    </div>

                    <!-- Step Variables Grid -->
                    <div class="space-y-3 rounded-lg border bg-card p-4">
                        <span class="block font-semibold text-foreground">
                            Valores das Variáveis Utilizadas no Ponto #{{
                                row.index
                            }}
                        </span>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Valor Medido Atual (Xᵢ)</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-foreground"
                                    >{{ row.raw_value }}</span
                                >
                            </div>
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Valor EWMA Anterior (Zᵢ₋₁)</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-foreground"
                                    >{{
                                        row.ewma.memorial.previous_z.toFixed(4)
                                    }}</span
                                >
                            </div>
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Fator Lambda (λ)</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-foreground"
                                    >{{ row.ewma.memorial.lambda }}</span
                                >
                            </div>
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Média Alvo Nominal (μ₀)</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-foreground"
                                    >{{
                                        row.ewma.memorial.mu_0.toFixed(4)
                                    }}</span
                                >
                            </div>
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Desvio Linha de Base (σ₀)</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-foreground"
                                    >{{
                                        row.ewma.memorial.sigma_0.toFixed(4)
                                    }}</span
                                >
                            </div>
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Desvio EWMA no Passo i (σ_{Zᵢ})</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-primary"
                                    >{{ row.ewma.sigma_z.toFixed(4) }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Limits Breakdown Table -->
                    <div class="overflow-hidden rounded-lg border bg-card">
                        <div
                            class="border-b bg-muted/40 px-4 py-2 font-semibold text-foreground"
                        >
                            Limites de Controle por Nível de Alerta no Passo #{{
                                row.index
                            }}
                        </div>
                        <table class="w-full text-left">
                            <thead
                                class="border-b bg-muted/20 text-[11px] text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-2">Nível</th>
                                    <th class="px-4 py-2">Multiplicador (L)</th>
                                    <th class="px-4 py-2">
                                        Limite Inferior (LCL)
                                    </th>
                                    <th class="px-4 py-2">
                                        Limite Superior (UCL)
                                    </th>
                                    <th class="px-4 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y font-mono">
                                <tr
                                    v-for="lvl in row.ewma.levels"
                                    :key="lvl.level"
                                    :class="
                                        lvl.level === row.ewma.alert_level
                                            ? 'bg-amber-500/10 font-bold'
                                            : ''
                                    "
                                >
                                    <td
                                        class="px-4 py-2 font-sans text-foreground"
                                    >
                                        Nível {{ lvl.level }}
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">
                                        {{ lvl.multiplier }}σ
                                    </td>
                                    <td class="px-4 py-2 text-foreground">
                                        {{ lvl.lcl?.toFixed(4) }}
                                    </td>
                                    <td class="px-4 py-2 text-foreground">
                                        {{ lvl.ucl?.toFixed(4) }}
                                    </td>
                                    <td class="px-4 py-2 font-sans">
                                        <span
                                            v-if="
                                                row.ewma.z_value >=
                                                    (lvl.lcl ?? 0) &&
                                                row.ewma.z_value <=
                                                    (lvl.ucl ?? 0)
                                            "
                                            class="font-semibold text-emerald-600"
                                        >
                                            Dentro
                                        </span>
                                        <span
                                            v-else
                                            class="font-semibold text-red-600"
                                        >
                                            Violado
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== CUSUM TAB ==================== -->
                <div v-else class="space-y-5">
                    <!-- Status Alert Banner -->
                    <div
                        :class="[
                            'flex items-center justify-between rounded-lg border p-3.5',
                            row.cusum.alert_level === 0
                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                : row.cusum.alert_level === 1
                                  ? 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                  : 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
                        ]"
                    >
                        <div class="flex items-center gap-2.5">
                            <CheckCircle
                                v-if="row.cusum.alert_level === 0"
                                class="h-5 w-5 shrink-0 text-emerald-500"
                            />
                            <AlertTriangle
                                v-else
                                class="h-5 w-5 shrink-0 text-amber-500"
                            />
                            <div>
                                <span class="block text-sm font-bold">
                                    {{
                                        row.cusum.alert_level === 0
                                            ? 'Ponto Sob Controle Estatístico (CUSUM)'
                                            : `Alerta Disparado: Patamar H${row.cusum.alert_level}`
                                    }}
                                </span>
                                <span class="text-[11px] opacity-90">
                                    {{
                                        row.cusum.alert_level === 0
                                            ? 'Somas acumuladas positivas e negativas estão abaixo dos limites de decisão H.'
                                            : `O acúmulo de desvios ultrapassou o patamar de alarme H.`
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Proximity to closest threshold -->
                        <div class="text-right">
                            <span
                                class="block text-[10px] font-bold tracking-wider uppercase opacity-75"
                            >
                                Próximo Patamar:
                                {{ row.cusum.closest_threshold.target_name }}
                            </span>
                            <span class="font-mono text-sm font-bold">
                                {{
                                    row.cusum.closest_threshold.margin >= 0
                                        ? `+${row.cusum.closest_threshold.margin.toFixed(3)}`
                                        : row.cusum.closest_threshold.margin.toFixed(
                                              3,
                                          )
                                }}
                                ({{
                                    row.cusum.closest_threshold.margin >= 0
                                        ? 'margem'
                                        : 'excesso'
                                }})
                            </span>
                        </div>
                    </div>

                    <!-- Formulas & Arithmetic Substitutions -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- C+ Positive -->
                        <div
                            class="space-y-2 rounded-lg border bg-muted/20 p-4"
                        >
                            <div
                                class="flex items-center justify-between border-b pb-1.5"
                            >
                                <span
                                    class="font-semibold text-sky-600 dark:text-sky-400"
                                    >C⁺ (Desvio Positivo)</span
                                >
                                <code
                                    class="rounded bg-muted px-2 py-0.5 font-mono text-[10px] text-muted-foreground"
                                >
                                    C_i^+ = max(0, X_i - (μ + K) + C_{i-1}^+)
                                </code>
                            </div>
                            <div
                                class="overflow-x-auto rounded-md border bg-background p-2.5 font-mono text-[11px] font-semibold text-foreground"
                            >
                                {{ row.cusum.memorial.substitution_pos_str }}
                            </div>
                        </div>

                        <!-- C- Negative -->
                        <div
                            class="space-y-2 rounded-lg border bg-muted/20 p-4"
                        >
                            <div
                                class="flex items-center justify-between border-b pb-1.5"
                            >
                                <span
                                    class="font-semibold text-orange-600 dark:text-orange-400"
                                    >C⁻ (Desvio Negativo)</span
                                >
                                <code
                                    class="rounded bg-muted px-2 py-0.5 font-mono text-[10px] text-muted-foreground"
                                >
                                    C_i^- = max(0, (μ - K) - X_i + C_{i-1}^-)
                                </code>
                            </div>
                            <div
                                class="overflow-x-auto rounded-md border bg-background p-2.5 font-mono text-[11px] font-semibold text-foreground"
                            >
                                {{ row.cusum.memorial.substitution_neg_str }}
                            </div>
                        </div>
                    </div>

                    <!-- Variables Grid -->
                    <div class="space-y-3 rounded-lg border bg-card p-4">
                        <span class="block font-semibold text-foreground">
                            Parâmetros e Variáveis do CUSUM
                        </span>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Fator de Folga (k)</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-foreground"
                                    >{{ row.cusum.memorial.k_factor }}σ</span
                                >
                            </div>
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Valor K Real (k · σ₀)</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-foreground"
                                    >{{ row.cusum.k_value.toFixed(4) }}</span
                                >
                            </div>
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Acumulado Anterior C⁺</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-sky-600"
                                    >{{
                                        row.cusum.memorial.previous_c_pos.toFixed(
                                            4,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="rounded-md border bg-muted/10 p-2.5">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Acumulado Anterior C⁻</span
                                >
                                <span
                                    class="font-mono text-sm font-bold text-orange-600"
                                    >{{
                                        row.cusum.memorial.previous_c_neg.toFixed(
                                            4,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Decision Thresholds Table -->
                    <div class="overflow-hidden rounded-lg border bg-card">
                        <div
                            class="border-b bg-muted/40 px-4 py-2 font-semibold text-foreground"
                        >
                            Limites de Decisão (Patamares H)
                        </div>
                        <table class="w-full text-left">
                            <thead
                                class="border-b bg-muted/20 text-[11px] text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-2">Patamar</th>
                                    <th class="px-4 py-2">Multiplicador (h)</th>
                                    <th class="px-4 py-2">
                                        Valor de Corte (H)
                                    </th>
                                    <th class="px-4 py-2">
                                        Máximo C Acumulado
                                    </th>
                                    <th class="px-4 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y font-mono">
                                <tr
                                    v-for="lvl in row.cusum.levels"
                                    :key="lvl.level"
                                    :class="
                                        lvl.level === row.cusum.alert_level
                                            ? 'bg-amber-500/10 font-bold'
                                            : ''
                                    "
                                >
                                    <td
                                        class="px-4 py-2 font-sans text-foreground"
                                    >
                                        Patamar H{{ lvl.level }}
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">
                                        {{ lvl.multiplier }}σ
                                    </td>
                                    <td class="px-4 py-2 text-foreground">
                                        {{ lvl.threshold?.toFixed(4) }}
                                    </td>
                                    <td
                                        class="px-4 py-2 font-semibold text-foreground"
                                    >
                                        {{
                                            Math.max(
                                                row.cusum.c_pos,
                                                row.cusum.c_neg,
                                            ).toFixed(4)
                                        }}
                                    </td>
                                    <td class="px-4 py-2 font-sans">
                                        <span
                                            v-if="
                                                Math.max(
                                                    row.cusum.c_pos,
                                                    row.cusum.c_neg,
                                                ) <= (lvl.threshold ?? 0)
                                            "
                                            class="font-semibold text-emerald-600"
                                        >
                                            Dentro
                                        </span>
                                        <span
                                            v-else
                                            class="font-semibold text-red-600"
                                        >
                                            Excedido
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-end border-t bg-muted/20 px-6 py-3"
            >
                <button
                    type="button"
                    @click="isOpen = false"
                    class="inline-flex h-9 items-center justify-center rounded-md bg-muted px-4 text-xs font-semibold text-foreground transition-colors hover:bg-muted/80"
                >
                    Fechar Memorial
                </button>
            </div>
        </div>
    </div>
</template>
