<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    store as storeColumn,
    update as updateColumn,
} from '@/routes/tasks/columns';
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
import type { Column } from '../types';

const props = defineProps<{
    open: boolean;
    boardId: number;
    columnToEdit?: Column | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved', column: Column): void;
}>();

const isEditing = computed(() => Boolean(props.columnToEdit));

const colorOptions = [
    { label: 'Cinza', value: '#64748b' },
    { label: 'Azul', value: '#3b82f6' },
    { label: 'Índigo', value: '#6366f1' },
    { label: 'Violeta', value: '#8b5cf6' },
    { label: 'Rosa', value: '#f43f5e' },
    { label: 'Laranja', value: '#f97316' },
    { label: 'Âmbar', value: '#f59e0b' },
    { label: 'Esmeralda', value: '#10b981' },
    { label: 'Ciano', value: '#06b6d4' },
];

const form = useForm({
    name: '',
    color: '#3b82f6',
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.name = props.columnToEdit?.name ?? '';
            form.color = props.columnToEdit?.color ?? '#3b82f6';
            form.clearErrors();
        }
    },
);

function closeModal() {
    emit('update:open', false);
    form.reset();
}

function submit() {
    if (isEditing.value && props.columnToEdit) {
        form.put(updateColumn([props.boardId, props.columnToEdit.id]).url, {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(storeColumn(props.boardId).url, {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    isEditing ? 'Editar Coluna' : 'Nova Coluna'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Altere o nome e a cor de destaque da coluna.'
                            : 'Adicione uma nova etapa ao fluxo de trabalho deste quadro.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="column-name">Nome da Coluna</Label>
                    <Input
                        id="column-name"
                        v-model="form.name"
                        placeholder="Ex: Em Revisão, Impedimentos, Testes..."
                        required
                        autofocus
                    />
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Cor de Destaque</Label>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button
                            v-for="color in colorOptions"
                            :key="color.value"
                            type="button"
                            @click="form.color = color.value"
                            class="relative flex h-7 w-7 items-center justify-center rounded-full transition-transform hover:scale-110 focus:outline-none"
                            :style="{ backgroundColor: color.value }"
                            :title="color.label"
                        >
                            <span
                                v-if="form.color === color.value"
                                class="h-2.5 w-2.5 rounded-full bg-white shadow-xs"
                            ></span>
                        </button>
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeModal">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Salvar' : 'Adicionar Coluna' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
