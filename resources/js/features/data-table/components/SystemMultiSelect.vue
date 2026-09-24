<script setup lang="ts">
import { ChevronDown, Search, X, Check } from '@lucide/vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';

interface SystemItem {
    id: number;
    name: string;
}

const props = defineProps<{
    systems: SystemItem[];
    modelValue: number[];
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number[]): void;
}>();

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref<HTMLDivElement | null>(null);

const filteredSystems = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.systems;
    }

    const q = searchQuery.value.toLowerCase().trim();

    return props.systems.filter((sys) => sys.name.toLowerCase().includes(q));
});

const isAllSelected = computed(() => {
    return (
        props.systems.length > 0 &&
        props.modelValue.length === props.systems.length
    );
});

const selectedDisplay = computed(() => {
    if (props.modelValue.length === 0) {
        return props.placeholder || 'Selecione o(s) sistema(s)...';
    }

    if (props.modelValue.length === 1) {
        const found = props.systems.find((s) => s.id === props.modelValue[0]);

        return found ? found.name : '1 sistema selecionado';
    }

    if (props.modelValue.length === props.systems.length) {
        return `Todos os sistemas (${props.systems.length})`;
    }

    return `${props.modelValue.length} sistemas selecionados`;
});

function toggleSystem(id: number) {
    let updated: number[];

    if (props.modelValue.includes(id)) {
        updated = props.modelValue.filter((sId) => sId !== id);
    } else {
        updated = [...props.modelValue, id];
    }

    emit('update:modelValue', updated);
}

function toggleSelectAll() {
    if (isAllSelected.value) {
        emit('update:modelValue', []);
    } else {
        emit(
            'update:modelValue',
            props.systems.map((s) => s.id),
        );
    }
}

function clearAll(event: Event) {
    event.stopPropagation();
    emit('update:modelValue', []);
}

function handleClickOutside(event: MouseEvent) {
    if (
        dropdownRef.value &&
        !dropdownRef.value.contains(event.target as Node)
    ) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="dropdownRef" class="relative w-full max-w-md">
        <!-- Input Trigger Button -->
        <button
            type="button"
            @click="isOpen = !isOpen"
            class="flex h-9 w-full items-center rounded-md border border-input bg-background px-2.5 py-1 pr-14 text-xs ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-1 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        >
            <div
                class="flex items-center gap-2 overflow-hidden text-ellipsis whitespace-nowrap"
            >
                <span
                    :class="{
                        'text-muted-foreground': modelValue.length === 0,
                    }"
                >
                    {{ selectedDisplay }}
                </span>
            </div>
        </button>

        <button
            v-if="modelValue.length > 0"
            type="button"
            @click="clearAll"
            class="absolute top-1/2 right-7 z-10 -translate-y-1/2 rounded-full p-0.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            title="Limpar seleção"
            aria-label="Limpar seleção"
        >
            <X class="h-3 w-3" />
        </button>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground transition-transform duration-200"
            :class="{ 'rotate-180': isOpen }"
        />

        <!-- Dropdown Content -->
        <div
            v-if="isOpen"
            class="absolute top-full right-0 left-0 z-50 mt-1 max-h-72 animate-in overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md transition-all fade-in-80"
        >
            <!-- Search Box -->
            <div class="flex items-center border-b px-3 py-2">
                <Search class="mr-2 h-4 w-4 shrink-0 opacity-50" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Buscar sistema..."
                    class="flex h-7 w-full rounded-md bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    @keydown.escape="isOpen = false"
                />
            </div>

            <!-- Header Option: Select All / Clear -->
            <div
                class="flex items-center justify-between border-b bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground"
            >
                <button
                    type="button"
                    @click="toggleSelectAll"
                    class="font-medium transition-colors hover:text-foreground hover:underline"
                >
                    {{ isAllSelected ? 'Desmarcar todos' : 'Selecionar todos' }}
                </button>
                <span>{{ modelValue.length }} de {{ systems.length }}</span>
            </div>

            <!-- Systems List -->
            <div class="max-h-48 overflow-y-auto p-1">
                <div
                    v-for="sys in filteredSystems"
                    :key="sys.id"
                    @click="toggleSystem(sys.id)"
                    class="flex cursor-pointer items-center justify-between rounded-sm px-2.5 py-1.5 text-sm transition-colors select-none hover:bg-accent hover:text-accent-foreground"
                >
                    <div class="flex items-center gap-2 overflow-hidden">
                        <div
                            class="flex h-4 w-4 items-center justify-center rounded border border-primary transition-colors"
                            :class="{
                                'bg-primary text-primary-foreground':
                                    modelValue.includes(sys.id),
                            }"
                        >
                            <Check
                                v-if="modelValue.includes(sys.id)"
                                class="h-3 w-3"
                            />
                        </div>
                        <span class="truncate">{{ sys.name }}</span>
                    </div>
                </div>

                <div
                    v-if="filteredSystems.length === 0"
                    class="p-3 text-center text-xs text-muted-foreground"
                >
                    Nenhum sistema encontrado.
                </div>
            </div>
        </div>
    </div>
</template>
