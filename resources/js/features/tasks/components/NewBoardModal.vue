<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    store as storeBoard,
    update as updateBoard,
} from '@/routes/tasks/boards';
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
import type { Board } from '../types';

const props = defineProps<{
    open: boolean;
    boardToEdit?: Board | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const isEditing = computed(() => Boolean(props.boardToEdit));

const form = useForm({
    name: '',
    description: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.name = props.boardToEdit?.name ?? '';
            form.description = props.boardToEdit?.description ?? '';
            form.clearErrors();
        }
    },
);

function closeModal() {
    emit('update:open', false);
    form.reset();
}

function submit() {
    if (isEditing.value && props.boardToEdit) {
        form.put(updateBoard(props.boardToEdit.id).url, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(storeBoard.url(), {
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
                    isEditing ? 'Editar Quadro' : 'Novo Quadro'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Altere o nome e a descrição do quadro de tarefas.'
                            : 'Crie um novo quadro kanban com colunas padrão para gerenciar tarefas.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="board-name">Nome do Quadro</Label>
                    <Input
                        id="board-name"
                        v-model="form.name"
                        placeholder="Ex: Projetos de TI, Rotinas Administrativas..."
                        required
                        autofocus
                    />
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="board-desc">Descrição (Opcional)</Label>
                    <textarea
                        id="board-desc"
                        v-model="form.description"
                        rows="3"
                        placeholder="Breve descrição do objetivo deste quadro..."
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    ></textarea>
                    <p
                        v-if="form.errors.description"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeModal">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Salvar Alterações' : 'Criar Quadro' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
