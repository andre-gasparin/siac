<script setup lang="ts">
import { Layers } from '@lucide/vue';
import { computed } from 'vue';

interface SystemItem {
    id: number;
    name: string;
    count: number;
}

const props = defineProps<{
    parameters: Array<{
        monitored_system_id?: number | null;
        system_name?: string | null;
    }>;
    disabledSystemIds: number[];
}>();

const emit = defineEmits<{
    'update:disabledSystemIds': [ids: number[]];
}>();

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
</script>

<template>
    <div
        v-if="isGroupedTable"
        class="flex flex-wrap items-center justify-between gap-2.5 rounded-xl border border-border/70 bg-muted/30 px-3 py-2 text-xs"
    >
        <div class="flex flex-wrap items-center gap-2">
            <div
                class="flex items-center gap-1.5 font-semibold text-muted-foreground"
            >
                <Layers class="size-3.5 text-primary" />
                <span
                    class="text-[11px] font-semibold tracking-wider uppercase"
                >
                    Sistemas no agrupamento:
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    v-for="sys in uniqueSystems"
                    :key="sys.id"
                    type="button"
                    class="group inline-flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-1 text-xs font-medium transition-all duration-200 select-none"
                    :class="
                        isSystemActive(sys.id)
                            ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-950 shadow-2xs hover:bg-emerald-500/15 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-200 dark:hover:bg-emerald-500/20'
                            : 'border-border/60 bg-muted/40 text-muted-foreground opacity-60 hover:bg-muted hover:opacity-100'
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
                    <span
                        class="py-0.2 rounded-full px-1.5 text-[10px] font-semibold transition-colors"
                        :class="
                            isSystemActive(sys.id)
                                ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300'
                                : 'bg-muted text-muted-foreground'
                        "
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
                                ? 'bg-emerald-600'
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
