<script setup lang="ts">
import { Head, useForm, setLayoutProps } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ChevronDown,
    GripVertical,
    Save,
    Sliders,
} from '@lucide/vue';
import { ref } from 'vue';
import { edit, index } from '@/routes/teams';
import {
    edit as editSystems,
    update as updateSystems,
} from '@/routes/teams/systems';
import Heading from '@/shared/components/Heading.vue';
import { Button } from '@/shared/components/ui/button';
import { Label } from '@/shared/components/ui/label';

type ParameterData = {
    id: number;
    name: string;
    code: string | null;
    tag: string | null;
    unit: string | null;
    decimals: number;
    sort_order: number;
    is_active: boolean;
    alert_1_min: number | null;
    alert_1_max: number | null;
    alert_2_min: number | null;
    alert_2_max: number | null;
    alert_3_min: number | null;
    alert_3_max: number | null;
    alert_4_min: number | null;
    alert_4_max: number | null;
};

type SystemData = {
    id: number;
    name: string;
    is_active: boolean;
    sort_order: number;
    parameters: ParameterData[];
};

type Props = {
    team: {
        id: number;
        name: string;
        slug: string;
    };
    monitoredSystems: SystemData[];
};

const props = defineProps<Props>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Unidades',
            href: index(),
        },
        {
            title: props.team.name,
            href: edit(props.team.slug),
        },
        {
            title: 'Sistemas e Parâmetros',
            href: editSystems(props.team.slug),
        },
    ],
});

const sanitizeSystems = (systems: SystemData[]): SystemData[] => {
    return systems.map((sys) => ({
        ...sys,
        is_active: Boolean(sys.is_active),
        parameters: sys.parameters.map((param) => ({
            ...param,
            is_active: Boolean(param.is_active),
        })),
    }));
};

// Setup form with cloned data
const form = useForm({
    systems: sanitizeSystems(
        JSON.parse(JSON.stringify(props.monitoredSystems)),
    ),
});

// Accordion open/close state
const expandedSystems = ref<number[]>([]);

const toggleSystem = (id: number) => {
    if (expandedSystems.value.includes(id)) {
        expandedSystems.value = expandedSystems.value.filter(
            (sysId) => sysId !== id,
        );
    } else {
        expandedSystems.value.push(id);
    }
};

// Parameter alerts expansion state
const expandedAlerts = ref<number[]>([]);

const toggleAlerts = (paramId: number) => {
    if (expandedAlerts.value.includes(paramId)) {
        expandedAlerts.value = expandedAlerts.value.filter(
            (id) => id !== paramId,
        );
    } else {
        expandedAlerts.value.push(paramId);
    }
};

// Drag and drop state for systems
const systemDragAllowed = ref(false);
const dragSystemIndex = ref<number | null>(null);
const draggedOverSystemIndex = ref<number | null>(null);

const onSystemDragStart = (index: number) => {
    dragSystemIndex.value = index;
};

const onSystemDragOver = (e: DragEvent, index: number) => {
    e.preventDefault();
    draggedOverSystemIndex.value = index;
};

const onSystemDragLeave = () => {
    draggedOverSystemIndex.value = null;
};

const onSystemDrop = (index: number) => {
    draggedOverSystemIndex.value = null;

    if (dragSystemIndex.value === null || dragSystemIndex.value === index) {
        return;
    }

    // Reorder systems array
    const movedSystem = form.systems[dragSystemIndex.value];
    form.systems.splice(dragSystemIndex.value, 1);
    form.systems.splice(index, 0, movedSystem);

    // Update sort_order based on new index
    form.systems.forEach((sys, i) => {
        sys.sort_order = i + 1;
    });

    dragSystemIndex.value = null;
};

// Drag and drop state for parameters
const dragAllowed = ref(false);
const dragParamIndex = ref<number | null>(null);
const dragParamSystemId = ref<number | null>(null);
const draggedOverParamIndex = ref<number | null>(null);
const draggedOverParamSystemId = ref<number | null>(null);

const onParamDragStart = (systemId: number, index: number) => {
    dragParamSystemId.value = systemId;
    dragParamIndex.value = index;
};

const onParamDragOver = (e: DragEvent, systemId: number, index: number) => {
    e.preventDefault();
    draggedOverParamSystemId.value = systemId;
    draggedOverParamIndex.value = index;
};

const onParamDragLeave = () => {
    draggedOverParamIndex.value = null;
    draggedOverParamSystemId.value = null;
};

const onParamDrop = (systemId: number, index: number) => {
    draggedOverParamIndex.value = null;
    draggedOverParamSystemId.value = null;

    if (
        dragParamSystemId.value !== systemId ||
        dragParamIndex.value === null ||
        dragParamIndex.value === index
    ) {
        return;
    }

    const system = form.systems.find((sys) => sys.id === systemId);

    if (!system) {
        return;
    }

    // Reorder parameters array
    const movedParam = system.parameters[dragParamIndex.value];
    system.parameters.splice(dragParamIndex.value, 1);
    system.parameters.splice(index, 0, movedParam);

    // Update sort_order based on new index
    system.parameters.forEach((param, i) => {
        param.sort_order = i + 1;
    });

    dragParamIndex.value = null;
    dragParamSystemId.value = null;
};

const save = () => {
    form.put(updateSystems.url(props.team.slug), {
        preserveScroll: true,
        onSuccess: () => {
            // Re-sync form defaults so it's no longer dirty
            form.defaults({
                systems: JSON.parse(JSON.stringify(form.systems)),
            });
            form.reset();
        },
    });
};
</script>

<template>
    <div class="space-y-6 px-4 py-6 md:px-6">
        <Head :title="`Sistemas e Parâmetros - ${team.name}`" />

        <div class="flex items-center justify-between">
            <Heading
                title="Sistemas e Parâmetros"
                :description="`Gerencie os sistemas monitorados e os parâmetros da unidade ${team.name}`"
            />
        </div>

        <!-- Accordion Section -->
        <div class="space-y-4">
            <div
                v-for="(system, sysIndex) in form.systems"
                :key="system.id"
                :draggable="systemDragAllowed"
                @dragstart="onSystemDragStart(sysIndex)"
                @dragover="onSystemDragOver($event, sysIndex)"
                @dragleave="onSystemDragLeave"
                @drop="onSystemDrop(sysIndex)"
                @dragend="systemDragAllowed = false"
                class="overflow-hidden rounded-lg border border-border bg-card shadow-xs transition-all duration-200"
                :class="{
                    'border-primary/40': expandedSystems.includes(system.id),
                    'border-dashed border-primary bg-primary/5':
                        draggedOverSystemIndex === sysIndex,
                }"
            >
                <!-- Accordion Header -->
                <div
                    class="flex cursor-pointer flex-wrap items-center justify-between gap-4 bg-muted/30 px-4 py-3 transition-colors select-none hover:bg-muted/50 md:px-6"
                    @click="toggleSystem(system.id)"
                >
                    <div class="flex min-w-[280px] flex-1 items-center gap-3">
                        <!-- Drag Grip -->
                        <div
                            class="flex size-7 cursor-grab items-center justify-center rounded-md border bg-background text-muted-foreground hover:text-foreground active:cursor-grabbing"
                            @mousedown="systemDragAllowed = true"
                            @mouseleave="systemDragAllowed = false"
                            @click.stop
                        >
                            <GripVertical class="size-4" />
                        </div>

                        <!-- Chevron toggle -->
                        <div
                            class="flex size-7 items-center justify-center rounded-md border bg-background text-muted-foreground hover:text-foreground"
                        >
                            <ChevronDown
                                class="size-4 transition-transform duration-200"
                                :class="{
                                    'rotate-180': expandedSystems.includes(
                                        system.id,
                                    ),
                                }"
                            />
                        </div>

                        <!-- Editable System Name (stops click propagation so it doesn't close accordion when editing) -->
                        <div class="max-w-md flex-1" @click.stop>
                            <input
                                v-model="system.name"
                                type="text"
                                class="w-full rounded border-none bg-transparent px-2 py-1 text-sm font-semibold transition-all hover:bg-muted/60 focus:bg-background focus:ring-1 focus:ring-ring focus:outline-none"
                                placeholder="Nome do sistema"
                                required
                            />
                        </div>
                    </div>

                    <!-- System active status & sort order (stops click propagation) -->
                    <div class="flex items-center gap-6" @click.stop>
                        <div class="flex items-center gap-2">
                            <button
                                :id="`sys-active-${system.id}`"
                                type="button"
                                role="switch"
                                :aria-checked="system.is_active"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                :class="
                                    system.is_active ? 'bg-primary' : 'bg-muted'
                                "
                                @click="system.is_active = !system.is_active"
                            >
                                <span
                                    aria-hidden="true"
                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out"
                                    :class="
                                        system.is_active
                                            ? 'translate-x-4'
                                            : 'translate-x-0'
                                    "
                                />
                            </button>
                            <Label
                                :for="`sys-active-${system.id}`"
                                class="cursor-pointer text-xs font-medium"
                                @click="system.is_active = !system.is_active"
                            >
                                Ativo
                            </Label>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full bg-muted/80 px-2 py-0.5 text-xs font-semibold text-muted-foreground select-none"
                            >
                                Pos. {{ sysIndex + 1 }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Accordion Content (Parameters list) -->
                <div
                    v-show="expandedSystems.includes(system.id)"
                    class="border-t border-border bg-background"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr
                                    class="border-b border-border bg-muted/10 text-xs font-semibold text-muted-foreground"
                                >
                                    <th class="w-[40px] px-2 py-2"></th>
                                    <th class="w-[60px] px-4 py-2 text-center">
                                        Ativo
                                    </th>
                                    <th class="min-w-[200px] px-4 py-2">
                                        Nome do Parâmetro
                                    </th>
                                    <th class="w-[120px] px-4 py-2">Código</th>
                                    <th class="w-[160px] px-4 py-2">Tag</th>
                                    <th class="w-[120px] px-4 py-2">Unidade</th>
                                    <th class="w-[90px] px-4 py-2 text-center">
                                        Decimais
                                    </th>
                                    <th class="w-[80px] px-4 py-2 text-center">
                                        Posição
                                    </th>
                                    <th class="w-[120px] px-4 py-2 text-center">
                                        Limites
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <template
                                    v-for="(
                                        parameter, paramIndex
                                    ) in system.parameters"
                                    :key="parameter.id"
                                >
                                    <!-- Main Parameter Row -->
                                    <tr
                                        :draggable="dragAllowed"
                                        @dragstart="
                                            onParamDragStart(
                                                system.id,
                                                paramIndex,
                                            )
                                        "
                                        @dragover="
                                            onParamDragOver(
                                                $event,
                                                system.id,
                                                paramIndex,
                                            )
                                        "
                                        @dragleave="onParamDragLeave"
                                        @drop="
                                            onParamDrop(system.id, paramIndex)
                                        "
                                        @dragend="dragAllowed = false"
                                        class="group transition-colors hover:bg-muted/10"
                                        :class="{
                                            'border-t-2 border-dashed border-primary bg-muted/20 opacity-40':
                                                draggedOverParamSystemId ===
                                                    system.id &&
                                                draggedOverParamIndex ===
                                                    paramIndex,
                                        }"
                                    >
                                        <!-- Drag Handle -->
                                        <td
                                            class="px-2 py-2 text-center align-middle"
                                        >
                                            <div
                                                class="mx-auto flex size-7 cursor-grab items-center justify-center rounded-md border bg-background text-muted-foreground hover:text-foreground active:cursor-grabbing"
                                                @mousedown="dragAllowed = true"
                                                @mouseleave="
                                                    dragAllowed = false
                                                "
                                            >
                                                <GripVertical class="size-4" />
                                            </div>
                                        </td>

                                        <!-- Active Status -->
                                        <td
                                            class="px-4 py-2 text-center align-middle"
                                        >
                                            <div
                                                class="flex items-center justify-center"
                                            >
                                                <button
                                                    type="button"
                                                    role="switch"
                                                    :aria-checked="
                                                        parameter.is_active
                                                    "
                                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                                    :class="
                                                        parameter.is_active
                                                            ? 'bg-primary'
                                                            : 'bg-muted'
                                                    "
                                                    @click="
                                                        parameter.is_active =
                                                            !parameter.is_active
                                                    "
                                                >
                                                    <span
                                                        aria-hidden="true"
                                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out"
                                                        :class="
                                                            parameter.is_active
                                                                ? 'translate-x-4'
                                                                : 'translate-x-0'
                                                        "
                                                    />
                                                </button>
                                            </div>
                                        </td>

                                        <!-- Name -->
                                        <td class="px-4 py-2 align-middle">
                                            <input
                                                v-model="parameter.name"
                                                type="text"
                                                class="w-full rounded border-none bg-transparent px-2 py-1 text-sm transition-all hover:bg-muted/50 focus:bg-background focus:ring-1 focus:ring-ring focus:outline-none"
                                                placeholder="Nome do parâmetro"
                                                required
                                            />
                                        </td>

                                        <!-- Code -->
                                        <td class="px-4 py-2 align-middle">
                                            <input
                                                v-model="parameter.code"
                                                type="text"
                                                class="w-full rounded border-none bg-transparent px-2 py-1 text-sm transition-all hover:bg-muted/50 focus:bg-background focus:ring-1 focus:ring-ring focus:outline-none"
                                                placeholder="Ex: temp"
                                            />
                                        </td>

                                        <!-- Tag -->
                                        <td class="px-4 py-2 align-middle">
                                            <input
                                                v-model="parameter.tag"
                                                type="text"
                                                class="w-full rounded border-none bg-transparent px-2 py-1 text-sm transition-all hover:bg-muted/50 focus:bg-background focus:ring-1 focus:ring-ring focus:outline-none"
                                                placeholder="Tag"
                                            />
                                        </td>

                                        <!-- Unit -->
                                        <td class="px-4 py-2 align-middle">
                                            <input
                                                v-model="parameter.unit"
                                                type="text"
                                                class="w-full rounded border-none bg-transparent px-2 py-1 text-sm transition-all hover:bg-muted/50 focus:bg-background focus:ring-1 focus:ring-ring focus:outline-none"
                                                placeholder="Ex: °C"
                                            />
                                        </td>

                                        <!-- Decimals -->
                                        <td
                                            class="px-4 py-2 text-center align-middle"
                                        >
                                            <input
                                                v-model.number="
                                                    parameter.decimals
                                                "
                                                type="number"
                                                min="0"
                                                max="10"
                                                class="mx-auto h-8 w-16 rounded border border-input bg-transparent px-2 py-0.5 text-center text-sm transition-all focus:bg-background focus:ring-1 focus:ring-ring"
                                            />
                                        </td>

                                        <!-- Position -->
                                        <td
                                            class="px-4 py-2 text-center align-middle text-sm font-semibold text-muted-foreground select-none"
                                        >
                                            #{{ paramIndex + 1 }}
                                        </td>

                                        <!-- Alerts Toggle -->
                                        <td
                                            class="px-4 py-2 text-center align-middle"
                                        >
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-7 px-2.5 text-xs"
                                                :class="{
                                                    'border-primary/40 bg-primary/10 text-primary hover:bg-primary/20':
                                                        expandedAlerts.includes(
                                                            parameter.id,
                                                        ),
                                                }"
                                                @click="
                                                    toggleAlerts(parameter.id)
                                                "
                                            >
                                                <span>Limites</span>
                                                <ChevronDown
                                                    class="ml-1 size-3.5 transition-transform duration-200"
                                                    :class="{
                                                        'rotate-180':
                                                            expandedAlerts.includes(
                                                                parameter.id,
                                                            ),
                                                    }"
                                                />
                                            </Button>
                                        </td>
                                    </tr>

                                    <!-- Collapsible Alerts/Limits Row -->
                                    <tr
                                        v-show="
                                            expandedAlerts.includes(
                                                parameter.id,
                                            )
                                        "
                                    >
                                        <td
                                            colspan="9"
                                            class="border-b border-border/80 bg-muted/15 px-6 py-4"
                                        >
                                            <div
                                                class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4"
                                            >
                                                <div
                                                    v-for="level in [
                                                        1, 2, 3, 4,
                                                    ]"
                                                    :key="level"
                                                    class="space-y-3 rounded-lg border border-border/60 bg-background p-3"
                                                >
                                                    <div
                                                        class="flex items-center gap-1.5 text-xs font-semibold text-foreground/80"
                                                    >
                                                        <AlertTriangle
                                                            class="size-3.5 text-amber-500"
                                                        />
                                                        Nível de Alerta
                                                        {{ level }}
                                                    </div>
                                                    <div
                                                        class="grid grid-cols-2 gap-2"
                                                    >
                                                        <div class="space-y-1">
                                                            <Label
                                                                :for="`param-${parameter.id}-l${level}-min`"
                                                                class="text-[10px] font-semibold text-muted-foreground uppercase"
                                                                >Mínimo</Label
                                                            >
                                                            <input
                                                                :id="`param-${parameter.id}-l${level}-min`"
                                                                v-model.number="
                                                                    parameter[
                                                                        `alert_${level}_min` as keyof ParameterData
                                                                    ]
                                                                "
                                                                type="number"
                                                                step="any"
                                                                class="h-8 w-full rounded border border-input bg-transparent px-2 py-1 text-xs transition-all focus:bg-background focus:ring-1 focus:ring-ring"
                                                                placeholder="Nenhum"
                                                            />
                                                        </div>
                                                        <div class="space-y-1">
                                                            <Label
                                                                :for="`param-${parameter.id}-l${level}-max`"
                                                                class="text-[10px] font-semibold text-muted-foreground uppercase"
                                                                >Máximo</Label
                                                            >
                                                            <input
                                                                :id="`param-${parameter.id}-l${level}-max`"
                                                                v-model.number="
                                                                    parameter[
                                                                        `alert_${level}_max` as keyof ParameterData
                                                                    ]
                                                                "
                                                                type="number"
                                                                step="any"
                                                                class="h-8 w-full rounded border border-input bg-transparent px-2 py-1 text-xs transition-all focus:bg-background focus:ring-1 focus:ring-ring"
                                                                placeholder="Nenhum"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty state inside accordion -->
                    <div
                        v-if="system.parameters.length === 0"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        Nenhum parâmetro cadastrado para este sistema.
                    </div>
                </div>
            </div>

            <!-- Empty state for systems -->
            <div
                v-if="form.systems.length === 0"
                class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card p-12 text-center"
            >
                <Sliders class="mb-2 size-10 text-muted-foreground/60" />
                <p class="text-sm font-medium">
                    Nenhum sistema monitorado cadastrado para esta unidade.
                </p>
            </div>
        </div>

        <!-- Floating Action Button for Saving Changes -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-10 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-10 opacity-0 scale-95"
        >
            <div v-if="form.isDirty" class="fixed right-6 bottom-6 z-50">
                <Button
                    type="button"
                    size="lg"
                    class="flex items-center gap-2 rounded-full bg-primary px-6 py-5 font-semibold text-primary-foreground shadow-xl transition-transform duration-200 hover:scale-105 hover:bg-primary/95"
                    :disabled="form.processing"
                    @click="save"
                >
                    <Save v-if="!form.processing" class="size-5" />
                    <svg
                        v-else
                        class="mr-3 -ml-1 h-5 w-5 animate-spin text-white"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    <span>Salvar Alterações</span>
                </Button>
            </div>
        </Transition>
    </div>
</template>
