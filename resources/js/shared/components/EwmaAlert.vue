<script setup lang="ts">
import { Activity, AlertTriangle, Info } from '@lucide/vue';
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';
import type { EwmaResult } from '@/shared/types/ewma';

defineProps<{ result: EwmaResult; parameterName: string }>();
const open = ref(false);
function format(value?: number) {
    return value?.toLocaleString('pt-BR', { maximumFractionDigits: 6 }) ?? '—';
}
</script>

<template>
    <span @click.stop>
        <button
            type="button"
            :aria-label="'Ver cálculo EWMA de ' + parameterName"
            :title="
                result.is_alert
                    ? 'Alerta EWMA: clique para ver o cálculo'
                    : 'Ver cálculo EWMA'
            "
            class="inline-flex shrink-0 items-center rounded p-0.5 focus-visible:ring-2 focus-visible:ring-ring"
            :class="
                result.is_alert
                    ? 'text-amber-600 dark:text-amber-400'
                    : 'text-muted-foreground'
            "
            @click="open = true"
        >
            <AlertTriangle v-if="result.is_alert" class="size-3.5" />
            <Activity v-else-if="result.available" class="size-3.5" />
            <Info v-else class="size-3.5" />
        </button>
        <Dialog v-model:open="open">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>EWMA · {{ parameterName }}</DialogTitle>
                    <DialogDescription
                        >Uma faixa de controle com as
                        {{ result.history_count }} leituras anteriores (até
                        {{ result.lookback }}).</DialogDescription
                    >
                </DialogHeader>
                <p v-if="!result.available" class="text-sm">
                    {{ result.reason }}
                </p>
                <div v-else class="space-y-4 text-sm">
                    <p
                        class="rounded border p-3 font-medium"
                        :class="
                            result.is_alert
                                ? 'border-amber-500 text-amber-700 dark:text-amber-400'
                                : 'text-emerald-700 dark:text-emerald-400'
                        "
                    >
                        <template v-if="result.is_alert"
                            >A média ponderada {{ format(result.ewma) }} está
                            {{
                                result.side === 'upper'
                                    ? 'acima do limite superior'
                                    : 'abaixo do limite inferior'
                            }}
                            de
                            {{
                                format(
                                    result.side === 'upper'
                                        ? result.upper_limit
                                        : result.lower_limit,
                                )
                            }}.</template
                        >
                        <template v-else
                            >A média ponderada está dentro dos limites
                            EWMA.</template
                        >
                    </p>
                    <dl class="grid grid-cols-2 gap-2">
                        <dt>EWMA - λ</dt>
                        <dd>{{ format(result.lambda) }}</dd>
                        <dt>EWMA - Nível (L)</dt>
                        <dd>{{ format(result.level) }}</dd>
                        <dt>Valor informado</dt>
                        <dd>{{ format(result.value) }}</dd>
                        <dt>Média dos anteriores (μ)</dt>
                        <dd>{{ format(result.mean) }}</dd>
                        <dt>Desvio padrão amostral (σ)</dt>
                        <dd>{{ format(result.sigma) }}</dd>
                        <dt>EWMA anterior (Z anterior)</dt>
                        <dd>{{ format(result.previous_ewma) }}</dd>
                        <dt>EWMA atual (Z)</dt>
                        <dd>{{ format(result.ewma) }}</dd>
                        <dt>Limites inferior / superior</dt>
                        <dd>
                            {{ format(result.lower_limit) }} /
                            {{ format(result.upper_limit) }}
                        </dd>
                    </dl>
                    <div
                        class="space-y-2 rounded bg-muted p-3 text-xs break-words"
                    >
                        <p>Z = λ × valor + (1 − λ) × Z anterior</p>
                        <p>{{ result.calculation }}</p>
                        <p>
                            σZ = σ × √[(λ / (2 − λ)) × (1 − (1 − λ)^(2t))], t =
                            {{ result.step }}
                        </p>
                        <p>Limites = μ ± L × σZ</p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        A referência usa somente leituras anteriores à coleta. A
                        sequência é iniciada na média desses dados e recalculada
                        em ordem cronológica. O valor atual e leituras futuras
                        não entram na média nem no desvio padrão da referência.
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    </span>
</template>
