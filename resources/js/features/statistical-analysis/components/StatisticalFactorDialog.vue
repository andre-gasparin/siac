<script setup lang="ts">
import { RotateCcw, Sliders, X } from '@lucide/vue';

const isOpen = defineModel<boolean>('isOpen', { required: true });

const numAlertLevels = defineModel<number>('numAlertLevels', {
    required: true,
});
const ewmaLambda = defineModel<number>('ewmaLambda', { required: true });
const ewmaLevels = defineModel<number[]>('ewmaLevels', { required: true });
const cusumK = defineModel<number>('cusumK', { required: true });
const cusumLevels = defineModel<number[]>('cusumLevels', { required: true });

function resetToDefaults() {
    numAlertLevels.value = 3;
    ewmaLambda.value = 0.2;
    ewmaLevels.value = [1.5, 2.0, 3.0];
    cusumK.value = 0.5;
    cusumLevels.value = [3.5, 4.5, 5.5];
}

function updateNumLevels(val: number) {
    numAlertLevels.value = val;

    if (val === 1) {
        ewmaLevels.value = [3.0];
        cusumLevels.value = [5.0];
    } else if (val === 2) {
        ewmaLevels.value = [2.0, 3.0];
        cusumLevels.value = [4.0, 5.0];
    } else if (val === 3) {
        ewmaLevels.value = [1.5, 2.0, 3.0];
        cusumLevels.value = [3.5, 4.5, 5.5];
    }
}
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 backdrop-blur-xs fade-in"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border bg-card shadow-2xl"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b bg-muted/20 px-6 py-4"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Sliders class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-foreground">
                            Fatores e Limites Estatísticos
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Ajuste os parâmetros dos algoritmos EWMA e CUSUM
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

            <!-- Body -->
            <div class="flex-1 space-y-6 overflow-y-auto p-6 text-sm">
                <!-- Seletor de Níveis de Alerta -->
                <div class="space-y-3 rounded-lg border bg-background/50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-sm font-semibold text-foreground"
                                >Quantidade de Níveis de Alerta</span
                            >
                            <p class="text-xs text-muted-foreground">
                                Define quantos patamares de controle (Atenção,
                                Ação, Crítico) serão avaliados.
                            </p>
                        </div>
                        <div class="inline-flex rounded-lg border bg-muted p-1">
                            <button
                                v-for="lvl in [1, 2, 3]"
                                :key="lvl"
                                type="button"
                                @click="updateNumLevels(lvl)"
                                :class="[
                                    'rounded-md px-3 py-1 text-xs font-semibold transition-all',
                                    numAlertLevels === lvl
                                        ? 'bg-card text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                            >
                                {{ lvl }} {{ lvl === 1 ? 'Nível' : 'Níveis' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- EWMA Configuration -->
                <div class="space-y-4 rounded-lg border bg-background/50 p-4">
                    <div class="border-b pb-2">
                        <h4
                            class="flex items-center gap-2 font-semibold text-primary"
                        >
                            <span
                                >EWMA (Média Móvel Ponderada
                                Exponencialmente)</span
                            >
                        </h4>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Fórmula: Z_i = λ · X_i + (1 - λ) · Z_{i-1} |
                            Limites: μ ± L · σ_{Z_i}
                        </p>
                    </div>

                    <!-- Lambda -->
                    <div
                        class="grid grid-cols-1 items-center gap-4 sm:grid-cols-2"
                    >
                        <div>
                            <label
                                class="block text-xs font-medium text-foreground"
                            >
                                Peso Exponencial (λ - Lambda)
                            </label>
                            <span class="text-[11px] text-muted-foreground">
                                Valores menores (ex: 0.1 a 0.2) suavizam mais e
                                detectam pequenos desvios lentos.
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <input
                                type="range"
                                min="0.05"
                                max="1.0"
                                step="0.05"
                                v-model.number="ewmaLambda"
                                class="w-full cursor-pointer accent-primary"
                            />
                            <span
                                class="w-12 text-right font-mono text-xs font-semibold text-foreground"
                            >
                                {{ ewmaLambda.toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Multipliers L_j -->
                    <div class="space-y-2 border-t pt-2">
                        <label
                            class="block text-xs font-medium text-foreground"
                        >
                            Multiplicadores de Limite (L em desvios σ)
                        </label>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div
                                v-for="idx in numAlertLevels"
                                :key="idx"
                                class="space-y-1 rounded-md border bg-muted/20 p-2.5"
                            >
                                <span
                                    class="block text-[11px] font-semibold text-muted-foreground"
                                >
                                    Nível {{ idx }} ({{
                                        idx === 1
                                            ? 'Atenção'
                                            : idx === 2
                                              ? 'Ação'
                                              : 'Crítico'
                                    }})
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <input
                                        type="number"
                                        step="0.1"
                                        min="0.5"
                                        max="6.0"
                                        v-model.number="ewmaLevels[idx - 1]"
                                        class="h-8 w-full rounded border bg-background px-2 font-mono text-xs font-semibold"
                                    />
                                    <span
                                        class="font-mono text-xs text-muted-foreground"
                                        >σ</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CUSUM Configuration -->
                <div class="space-y-4 rounded-lg border bg-background/50 p-4">
                    <div class="border-b pb-2">
                        <h4
                            class="flex items-center gap-2 font-semibold text-primary"
                        >
                            <span>CUSUM (Soma Acumulada Tabular)</span>
                        </h4>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Fórmula: C_i^+ = max(0, X_i - (μ + K) + C_{i-1}^+) |
                            Alarme: C > H
                        </p>
                    </div>

                    <!-- Slack K -->
                    <div
                        class="grid grid-cols-1 items-center gap-4 sm:grid-cols-2"
                    >
                        <div>
                            <label
                                class="block text-xs font-medium text-foreground"
                            >
                                Fator de Folga (k - Referência)
                            </label>
                            <span class="text-[11px] text-muted-foreground">
                                K = k · σ. Padrão da literatura é 0.5σ (detecta
                                deslocamentos de 1.0σ na média).
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <input
                                type="range"
                                min="0.1"
                                max="1.5"
                                step="0.05"
                                v-model.number="cusumK"
                                class="w-full cursor-pointer accent-primary"
                            />
                            <span
                                class="w-12 text-right font-mono text-xs font-semibold text-foreground"
                            >
                                {{ cusumK.toFixed(2) }}σ
                            </span>
                        </div>
                    </div>

                    <!-- Decision Intervals H_j -->
                    <div class="space-y-2 border-t pt-2">
                        <label
                            class="block text-xs font-medium text-foreground"
                        >
                            Intervalos de Decisão (h em desvios σ)
                        </label>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div
                                v-for="idx in numAlertLevels"
                                :key="idx"
                                class="space-y-1 rounded-md border bg-muted/20 p-2.5"
                            >
                                <span
                                    class="block text-[11px] font-semibold text-muted-foreground"
                                >
                                    Patamar H{{ idx }} ({{
                                        idx === 1
                                            ? 'Atenção'
                                            : idx === 2
                                              ? 'Ação'
                                              : 'Crítico'
                                    }})
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="1.0"
                                        max="10.0"
                                        v-model.number="cusumLevels[idx - 1]"
                                        class="h-8 w-full rounded border bg-background px-2 font-mono text-xs font-semibold"
                                    />
                                    <span
                                        class="font-mono text-xs text-muted-foreground"
                                        >σ</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-between border-t bg-muted/20 px-6 py-3"
            >
                <button
                    type="button"
                    @click="resetToDefaults"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    <span>Restaurar Padrões Recomendados</span>
                </button>

                <button
                    type="button"
                    @click="isOpen = false"
                    class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-xs font-semibold text-primary-foreground shadow transition-colors hover:bg-primary/90"
                >
                    Aplicar Configurações
                </button>
            </div>
        </div>
    </div>
</template>
