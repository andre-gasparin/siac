<script setup lang="ts">
import {
    Head,
    router,
    setLayoutProps,
    useForm,
    usePoll,
} from '@inertiajs/vue3';
import { Calculator, Plus, RefreshCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CalculationFormulaPreview from '@/features/calculations/components/CalculationFormulaPreview.vue';
import CalculationOperandFields from '@/features/calculations/components/CalculationOperandFields.vue';
import type {
    CalculationRule,
    CalculationRun,
    CalculationSystem,
} from '@/features/calculations/types';
import {
    destroy,
    index,
    recalculate,
    store,
    update,
} from '@/routes/calculations';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import type { Team } from '@/shared/types';

const props = defineProps<{
    currentTeam: Team;
    systems: CalculationSystem[];
    rules: CalculationRule[];
    runs: CalculationRun[];
    types: Record<string, { label: string; formula: string }>;
}>();
const team = computed(() => props.currentTeam.slug);
setLayoutProps({
    breadcrumbs: [
        { title: 'Cálculos', href: index.url({ current_team: team.value }) },
    ],
});
const editing = ref<CalculationRule | null>(null);
const formOpen = ref(false);
const deleting = ref<CalculationRule | null>(null);
const recalculating = ref<CalculationRule | null>(null);
const activeRun = computed(() =>
    props.runs.some((run) => ['pending', 'running'].includes(run.status)),
);
const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['runs'] },
    { autoStart: false },
);
watch(activeRun, (active) => (active ? startPolling() : stopPolling()), {
    immediate: true,
});

const form = useForm({
    name: '',
    type: 'cycle',
    primary_system_id: null as number | null,
    primary_parameter_id: null as number | null,
    secondary_system_id: null as number | null,
    secondary_parameter_id: null as number | null,
    destination_system_id: null as number | null,
    destination_parameter_id: null as number | null,
    primary_multiplier: null as number | null,
    secondary_multiplier: null as number | null,
    result_multiplier: null as number | null,
});
const recalcForm = useForm({ start_date: '', end_date: '' });
const deleteForm = useForm({});
const statusLabels: Record<string, string> = {
    pending: 'Na fila',
    running: 'Em andamento',
    completed: 'Concluído',
    failed: 'Falhou',
};

function parameterName(id: number) {
    for (const system of props.systems) {
        const parameter = system.parameters.find((item) => item.id === id);

        if (parameter) {
            return `${system.name} | ${parameter.name}`;
        }
    }

    return `Parâmetro #${id}`;
}

function formatFormulaWithoutNames(
    type: string,
    primaryMultiplier: number | null,
    secondaryMultiplier: number | null,
    resultMultiplier: number | null,
): string {
    const p = primaryMultiplier !== null ? `(P × ${primaryMultiplier})` : 'P';
    const s =
        secondaryMultiplier !== null ? `(S × ${secondaryMultiplier})` : 'S';

    let formula = '';

    switch (type) {
        case 'cycle':
            formula = `${p} ÷ ${s}`;
            break;
        case 'ratio':
            formula = `${s} ÷ ${p}`;
            break;
        case 'percentage':
            formula = `(${s} ÷ ${p}) × 100`;
            break;
        case 'efficiency':
            formula = `(1 − (${s} ÷ ${p})) × 100`;
            break;
        case 'net_loss':
            formula = `${p} ÷ (${s} − 1)`;
            break;
        case 'ph':
            formula = `log10((${p} − (${s} ÷ 3)) ÷ 273) + 11`;
            break;
        default:
            formula = (props.types[type]?.formula || `${p} / ${s}`)
                .replaceAll('P', p)
                .replaceAll('S', s)
                .replaceAll('/', '÷');
            break;
    }

    if (resultMultiplier !== null) {
        formula = `(${formula}) × ${resultMultiplier}`;
    }

    return `Destino = ${formula}`;
}

function openForm(rule: CalculationRule | null = null) {
    form.reset();
    form.clearErrors();
    editing.value = rule;

    if (rule) {
        Object.assign(form, {
            name: rule.name,
            type: rule.type,
            primary_parameter_id: rule.primary_parameter_id,
            secondary_parameter_id: rule.secondary_parameter_id,
            destination_parameter_id: rule.destination_parameter_id,
            primary_multiplier: rule.primary_multiplier,
            secondary_multiplier: rule.secondary_multiplier,
            result_multiplier: rule.result_multiplier,
        });

        for (const role of ['primary', 'secondary', 'destination'] as const) {
            form[`${role}_system_id`] =
                props.systems.find((system) =>
                    system.parameters.some(
                        (parameter) =>
                            parameter.id === rule[`${role}_parameter_id`],
                    ),
                )?.id ?? null;
        }
    }

    formOpen.value = true;
}

function save() {
    if (form.destination_parameter_id) {
        form.name = parameterName(form.destination_parameter_id);
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
        },
    };

    if (editing.value) {
        form.put(
            update.url({
                current_team: team.value,
                calculation: editing.value.id,
            }),
            options,
        );
    } else {
        form.post(store.url({ current_team: team.value }), options);
    }
}

function toggleResultMultiplier(event: Event) {
    form.result_multiplier = (event.target as HTMLInputElement).checked
        ? 1000
        : null;
}

function startRecalculation() {
    if (!recalculating.value) {
        return;
    }

    recalcForm.post(
        recalculate.url({
            current_team: team.value,
            calculation: recalculating.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                recalculating.value = null;
                router.reload({ only: ['runs'] });
            },
        },
    );
}

function remove() {
    if (!deleting.value) {
        return;
    }

    deleteForm.delete(
        destroy.url({
            current_team: team.value,
            calculation: deleting.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                deleting.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Cálculos" />
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-semibold">
                    <Calculator class="size-6" /> Cálculos
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Combine parâmetros coletados na mesma data e hora.
                </p>
            </div>
            <Button :disabled="activeRun" @click="openForm()"
                ><Plus class="size-4" /> Novo cálculo</Button
            >
        </div>
        <p
            v-if="activeRun"
            role="status"
            class="rounded-lg border bg-muted p-3 text-sm"
        >
            Há um recálculo em andamento. Aguarde para alterar regras ou iniciar
            outro recálculo.
        </p>
        <div
            v-if="!rules.length"
            class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            Nenhum cálculo cadastrado nesta unidade.
        </div>
        <div v-else class="grid gap-4 lg:grid-cols-2">
            <article
                v-for="rule in rules"
                :key="rule.id"
                class="flex flex-col justify-between gap-4 rounded-xl border bg-card p-5"
            >
                <div class="grid gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">
                            {{ types[rule.type]?.label }}
                        </h2>
                    </div>
                    <div
                        class="rounded-md bg-muted/60 p-3 font-mono text-sm break-words text-muted-foreground"
                    >
                        {{
                            formatFormulaWithoutNames(
                                rule.type,
                                rule.primary_multiplier,
                                rule.secondary_multiplier,
                                rule.result_multiplier,
                            )
                        }}
                    </div>
                    <div
                        class="rounded-md border border-border/60 bg-muted/30 p-3"
                    >
                        <CalculationFormulaPreview
                            :type="rule.type"
                            :destination-id="rule.destination_parameter_id"
                            :primary-id="rule.primary_parameter_id"
                            :secondary-id="rule.secondary_parameter_id"
                            :primary-multiplier="rule.primary_multiplier"
                            :secondary-multiplier="rule.secondary_multiplier"
                            :result-multiplier="rule.result_multiplier"
                            :systems="systems"
                            :types="types"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="activeRun"
                        @click="openForm(rule)"
                        >Editar</Button
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="activeRun"
                        @click="
                            recalcForm.clearErrors();
                            recalculating = rule;
                        "
                        ><RefreshCw class="size-4" /> Recalcular</Button
                    >
                    <Button
                        variant="destructive"
                        size="sm"
                        :disabled="activeRun"
                        @click="
                            deleteForm.clearErrors();
                            deleting = rule;
                        "
                        >Excluir</Button
                    >
                </div>
            </article>
        </div>
        <section v-if="runs.length" class="grid gap-3">
            <h2 class="text-lg font-semibold">Recálculos recentes</h2>
            <article
                v-for="run in runs"
                :key="run.id"
                class="grid gap-2 rounded-lg border p-4 text-sm"
                :aria-label="`Recálculo ${run.id}`"
            >
                <div class="flex flex-wrap justify-between gap-2">
                    <strong>{{
                        rules.find(
                            (rule) => rule.id === run.calculation_rule_id,
                        )
                            ? parameterName(
                                  rules.find(
                                      (rule) =>
                                          rule.id === run.calculation_rule_id,
                                  )!.destination_parameter_id,
                              )
                            : `Cálculo #${run.calculation_rule_id}`
                    }}</strong
                    ><span role="status">{{ statusLabels[run.status] }}</span>
                </div>
                <p>
                    {{ run.start_date.slice(0, 10) }} a
                    {{ run.end_date.slice(0, 10) }} ·
                    {{ run.processed_count }} / {{ run.total_count }} horários
                </p>
                <progress
                    v-if="['pending', 'running'].includes(run.status)"
                    class="h-2 w-full"
                    :value="run.processed_count"
                    :max="Math.max(run.total_count, 1)"
                    aria-label="Progresso do recálculo"
                />
                <p class="text-muted-foreground">
                    {{ run.created_count }} criados ·
                    {{ run.updated_count }} atualizados ·
                    {{ run.removed_count }} removidos
                </p>
                <p v-if="run.error" role="alert" class="text-destructive">
                    {{ run.error }}
                </p>
            </article>
        </section>

        <Dialog v-model:open="formOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader
                    ><DialogTitle>{{
                        editing ? 'Editar cálculo' : 'Novo cálculo'
                    }}</DialogTitle
                    ><DialogDescription
                        >Defina o parâmetro de destino onde o resultado será
                        gravado e, em seguida, selecione os parâmetros de
                        entrada (Principal e Secundário). Os valores das
                        entradas podem ser multiplicados antes do
                        cálculo.</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-5" @submit.prevent="save">
                    <div class="grid gap-3">
                        <div class="grid gap-2">
                            <Label for="calculation-type">Tipo</Label>
                            <select
                                id="calculation-type"
                                v-model="form.type"
                                class="h-10 rounded-md border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="(type, key) in types"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>
                        <div
                            class="rounded-md bg-muted/60 p-3 font-mono text-sm break-words text-muted-foreground"
                        >
                            {{
                                formatFormulaWithoutNames(
                                    form.type,
                                    form.primary_multiplier,
                                    form.secondary_multiplier,
                                    form.result_multiplier,
                                )
                            }}
                        </div>
                        <div
                            class="rounded-md border border-border/60 bg-muted/30 p-3"
                        >
                            <CalculationFormulaPreview
                                :type="form.type"
                                :destination-id="form.destination_parameter_id"
                                :primary-id="form.primary_parameter_id"
                                :secondary-id="form.secondary_parameter_id"
                                :primary-multiplier="form.primary_multiplier"
                                :secondary-multiplier="
                                    form.secondary_multiplier
                                "
                                :result-multiplier="form.result_multiplier"
                                :systems="systems"
                                :types="types"
                            />
                        </div>
                    </div>
                    <CalculationOperandFields
                        v-model:system="form.destination_system_id"
                        v-model:parameter="form.destination_parameter_id"
                        role="destination"
                        label="Destino"
                        :systems="systems"
                    />
                    <CalculationOperandFields
                        v-model:system="form.primary_system_id"
                        v-model:parameter="form.primary_parameter_id"
                        v-model:multiplier="form.primary_multiplier"
                        role="primary"
                        label="Principal (P)"
                        :systems="systems"
                        with-multiplier
                    />
                    <CalculationOperandFields
                        v-model:system="form.secondary_system_id"
                        v-model:parameter="form.secondary_parameter_id"
                        v-model:multiplier="form.secondary_multiplier"
                        role="secondary"
                        label="Secundário (S)"
                        :systems="systems"
                        with-multiplier
                    />
                    <div class="grid gap-2">
                        <Label
                            for="result-enabled"
                            class="flex items-center gap-2"
                            ><input
                                id="result-enabled"
                                type="checkbox"
                                :checked="form.result_multiplier !== null"
                                @change="toggleResultMultiplier"
                            />
                            Multiplicar o resultado</Label
                        ><Input
                            :model-value="form.result_multiplier ?? ''"
                            type="number"
                            step="any"
                            :disabled="form.result_multiplier === null"
                            :required="form.result_multiplier !== null"
                            aria-label="Multiplicador do resultado"
                            @update:model-value="
                                (value) => {
                                    form.result_multiplier =
                                        value === '' ? null : Number(value);
                                }
                            "
                        />
                    </div>
                    <div
                        v-if="Object.keys(form.errors).length"
                        role="alert"
                        class="grid gap-1 text-sm text-destructive"
                    >
                        <p v-for="(error, key) in form.errors" :key="key">
                            {{ error }}
                        </p>
                    </div>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="formOpen = false"
                            >Cancelar</Button
                        ><Button type="submit" :disabled="form.processing"
                            >Salvar cálculo</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="recalculating !== null"
            @update:open="
                (open) => {
                    if (!open) recalculating = null;
                }
            "
        >
            <DialogContent
                ><DialogHeader
                    ><DialogTitle
                        >Recalcular
                        {{
                            recalculating
                                ? parameterName(
                                      recalculating.destination_parameter_id,
                                  )
                                : ''
                        }}</DialogTitle
                    ><DialogDescription
                        >Todos os horários do intervalo serão processados,
                        incluindo os cálculos necessários e dependentes. Valores
                        existentes no destino poderão ser
                        substituídos.</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-4" @submit.prevent="startRecalculation">
                    <div class="grid gap-2">
                        <Label for="recalc-start">Data inicial</Label
                        ><Input
                            id="recalc-start"
                            v-model="recalcForm.start_date"
                            type="date"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="recalc-end">Data final</Label
                        ><Input
                            id="recalc-end"
                            v-model="recalcForm.end_date"
                            type="date"
                            required
                            :min="recalcForm.start_date"
                        />
                    </div>
                    <div role="alert" class="text-sm text-destructive">
                        <p v-for="(error, key) in recalcForm.errors" :key="key">
                            {{ error }}
                        </p>
                    </div>
                    <DialogFooter
                        ><Button
                            type="submit"
                            :disabled="recalcForm.processing || activeRun"
                            >Iniciar recálculo</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>
        <Dialog
            :open="deleting !== null"
            @update:open="
                (open) => {
                    if (!open) deleting = null;
                }
            "
        >
            <DialogContent
                ><DialogHeader
                    ><DialogTitle
                        >Excluir
                        {{
                            deleting
                                ? parameterName(
                                      deleting.destination_parameter_id,
                                  )
                                : ''
                        }}?</DialogTitle
                    ><DialogDescription
                        >A regra deixará de executar. Os resultados históricos
                        serão preservados.</DialogDescription
                    ></DialogHeader
                >
                <p
                    v-for="(error, key) in deleteForm.errors"
                    :key="key"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ error }}
                </p>
                <DialogFooter
                    ><Button variant="outline" @click="deleting = null"
                        >Cancelar</Button
                    ><Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="remove"
                        >Excluir cálculo</Button
                    ></DialogFooter
                ></DialogContent
            >
        </Dialog>
    </div>
</template>
