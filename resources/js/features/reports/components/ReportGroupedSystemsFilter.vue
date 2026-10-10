<script setup lang="ts">
import { Layers, X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface SystemItem {
    id: number;
    name: string;
    count: number;
}

interface GroupedChild {
    id: number;
    name: string;
    [key: string]: any;
}

const props = withDefaults(
    defineProps<{
        parameters: Array<{
            monitored_system_id?: number | null;
            system_name?: string | null;
        }>;
        disabledSystemIds: number[];
        groupedChildren?: GroupedChild[];
        bordered?: boolean;
    }>(),
    {
        groupedChildren: () => [],
        bordered: false,
    },
);

const emit = defineEmits<{
    'update:disabledSystemIds': [ids: number[]];
    ungroupChild: [child: GroupedChild];
}>();

const confirmingChild = ref<GroupedChild | null>(null);

const uniqueSystems = computed<SystemItem[]>(() => {
    const map = new Map<number, SystemItem>();

    for (const p of props.parameters) {
        if (p.monitored_system_id) {
            const existing = map.get(p.monitored_system_id);

            if (existing) {
                existing.count++;
            } else {
                map.set(p.monitored_system_id, {
                    id: p.monitored_system_id,
                    name: p.system_name || `Sistema #${p.monitored_system_id}`,
                    count: 1,
                });
            }
        }
    }

    return Array.from(map.values());
});

const isGroupedTable = computed(() => uniqueSystems.value.length > 1);

function isSystemActive(systemId: number): boolean {
    return !props.disabledSystemIds.includes(systemId);
}

function toggleSystem(systemId: number): void {
    if (props.disabledSystemIds.includes(systemId)) {
        emit(
            'update:disabledSystemIds',
            props.disabledSystemIds.filter((id) => id !== systemId),
        );
    } else {
        emit('update:disabledSystemIds', [
            ...props.disabledSystemIds,
            systemId,
        ]);
    }
}

function enableAll(): void {
    emit('update:disabledSystemIds', []);
}

function invertSelection(): void {
    const allIds = uniqueSystems.value.map((s) => s.id);
    const newDisabled = allIds.filter(
        (id) => !props.disabledSystemIds.includes(id),
    );
    emit('update:disabledSystemIds', newDisabled);
}

function getGroupedChild(systemId: number): GroupedChild | undefined {
    return props.groupedChildren.find((child) => child.id === systemId);
}

function promptUngroup(child: GroupedChild): void {
    if (confirmingChild.value?.id === child.id) {
        confirmingChild.value = null;
    } else {
        confirmingChild.value = child;
    }
}

function confirmUngroup(child: GroupedChild): void {
    confirmingChild.value = null;
    emit('ungroupChild', child);
}

function cancelUngroup(): void {
    confirmingChild.value = null;
}

function handleKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape' && confirmingChild.value) {
        confirmingChild.value = null;
    }
}

function handleWindowClick(): void {
    if (confirmingChild.value) {
        confirmingChild.value = null;
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('click', handleWindowClick);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('click', handleWindowClick);
});
</script>

<template>
    <div
        v-if="isGroupedTable"
        class="flex flex-wrap items-center justify-between gap-2.5 text-xs"
        :class="
            bordered
                ? 'rounded-lg border border-border/70 bg-muted/20 px-3 py-2'
                : ''
        "
    >
        <div class="flex flex-wrap items-center gap-2">
            <div
                class="flex items-center gap-1.5 font-semibold text-muted-foreground"
            >
                <Layers class="size-3.5 text-primary" />
                <span
                    class="text-[11px] font-semibold tracking-wider uppercase"
                >
                    Agrupamento:
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <div
                    v-for="sys in uniqueSystems"
                    :key="sys.id"
                    class="relative inline-flex items-center"
                >
                    <button
                        type="button"
                        class="group inline-flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-1 text-xs font-medium transition-all duration-200 select-none"
                        :class="
                            isSystemActive(sys.id)
                                ? 'border-border/80 bg-background text-foreground shadow-2xs hover:bg-muted/50 dark:border-border/60 dark:bg-card dark:hover:bg-muted/30'
                                : 'border-border/40 bg-muted/20 text-muted-foreground opacity-60 hover:bg-muted hover:opacity-100'
                        "
                        :title="
                            isSystemActive(sys.id)
                                ? `Clique para ocultar ${sys.name}`
                                : `Clique para exibir ${sys.name}`
                        "
                        @click="toggleSystem(sys.id)"
                    >
                        <span class="max-w-[220px] truncate">
                            {{ sys.name }}
                        </span>

                        <!-- Botão X para desagrupar sistema filho -->
                        <span
                            v-if="getGroupedChild(sys.id)"
                            class="relative inline-flex items-center"
                        >
                            <span
                                role="button"
                                tabindex="0"
                                class="inline-flex size-4 items-center justify-center rounded-full text-muted-foreground/70 transition hover:bg-destructive/15 hover:text-destructive focus:outline-hidden"
                                title="Desagrupar sistema"
                                @click.stop="
                                    promptUngroup(getGroupedChild(sys.id)!)
                                "
                            >
                                <X class="size-2.5" />
                            </span>
                        </span>

                        <span
                            class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground transition-colors"
                        >
                            {{ sys.count }}
                        </span>

                        <!-- Switch slider visual -->
                        <span
                            role="switch"
                            :aria-checked="isSystemActive(sys.id)"
                            class="relative inline-flex h-4 w-7 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                            :class="
                                isSystemActive(sys.id)
                                    ? 'bg-primary'
                                    : 'bg-muted-foreground/30'
                            "
                        >
                            <span
                                aria-hidden="true"
                                class="pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                :class="
                                    isSystemActive(sys.id)
                                        ? 'translate-x-3'
                                        : 'translate-x-0'
                                "
                            />
                        </span>
                    </button>

                    <!-- Tooltip de confirmação para desagrupar -->
                    <div
                        v-if="confirmingChild?.id === sys.id"
                        class="absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 animate-in rounded-lg border border-border bg-popover p-2.5 text-xs whitespace-nowrap text-popover-foreground shadow-lg ring-1 ring-black/5 duration-150 zoom-in-95 fade-in dark:ring-white/10"
                        @click.stop
                    >
                        <p class="font-medium text-foreground">
                            Desagrupar {{ confirmingChild.name }}?
                        </p>
                        <div class="mt-2 flex items-center justify-end gap-1.5">
                            <button
                                type="button"
                                class="rounded-md border border-border px-2 py-0.5 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                @click.stop="cancelUngroup"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-destructive px-2 py-0.5 text-[11px] font-medium text-destructive-foreground shadow-2xs transition hover:bg-destructive/90"
                                @click.stop="confirmUngroup(confirmingChild)"
                            >
                                Desagrupar
                            </button>
                        </div>
                        <!-- Seta do Tooltip -->
                        <div
                            class="absolute -bottom-1 left-1/2 size-2 -translate-x-1/2 rotate-45 border-r border-b border-border bg-popover"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de Ação Rápida -->
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-border/80 bg-background px-2.5 py-1 text-[11px] font-medium text-foreground shadow-2xs transition hover:bg-muted"
                title="Ativar visualização de todos os sistemas"
                @click="enableAll"
            >
                Todos
            </button>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-border/80 bg-background px-2.5 py-1 text-[11px] font-medium text-muted-foreground shadow-2xs transition hover:bg-muted hover:text-foreground"
                title="Inverter seleção atual"
                @click="invertSelection"
            >
                Inverter
            </button>
        </div>
    </div>
</template>
