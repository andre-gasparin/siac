<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';

defineProps<{
    open: boolean;
    sourceCell: string;
    sourceDateCell?: string;
    targetCells: string[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (
        e: 'confirm',
        option: 'row_increment' | 'col_increment' | 'fixed_date',
    ): void;
}>();

const selectedOption = ref<'row_increment' | 'col_increment' | 'fixed_date'>(
    'row_increment',
);

function handleConfirm() {
    emit('confirm', selectedOption.value);
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[460px]">
            <DialogHeader>
                <DialogTitle>Colar Mapeamento com Incremento</DialogTitle>
                <DialogDescription>
                    Você está colando o mapeamento de
                    <strong class="font-mono text-foreground">{{
                        sourceCell
                    }}</strong>
                    para
                    <strong class="font-mono text-foreground">{{
                        targetCells.length
                    }}</strong>
                    célula(s). Como deseja tratar a referência de data
                    <span v-if="sourceDateCell"
                        >(atual:
                        <strong class="font-mono text-foreground">{{
                            sourceDateCell
                        }}</strong
                        >)</span
                    >?
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-3 py-3">
                <label
                    :class="[
                        'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors',
                        selectedOption === 'row_increment'
                            ? 'border-primary bg-primary/5'
                            : 'border-border hover:bg-muted/40',
                    ]"
                >
                    <input
                        v-model="selectedOption"
                        type="radio"
                        value="row_increment"
                        class="mt-1 text-primary focus:ring-primary"
                    />
                    <div>
                        <div class="text-sm font-medium text-foreground">
                            Incrementar linha de data
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Avança a linha da data sequencialmente (ex: se era
                            A7, a próxima célula apontará para A8, depois
                            A9...).
                        </div>
                    </div>
                </label>

                <label
                    :class="[
                        'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors',
                        selectedOption === 'col_increment'
                            ? 'border-primary bg-primary/5'
                            : 'border-border hover:bg-muted/40',
                    ]"
                >
                    <input
                        v-model="selectedOption"
                        type="radio"
                        value="col_increment"
                        class="mt-1 text-primary focus:ring-primary"
                    />
                    <div>
                        <div class="text-sm font-medium text-foreground">
                            Incrementar coluna de data
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Avança a coluna da data sequencialmente (ex: se era
                            B6, a próxima apontará para C6, D6...).
                        </div>
                    </div>
                </label>

                <label
                    :class="[
                        'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors',
                        selectedOption === 'fixed_date'
                            ? 'border-primary bg-primary/5'
                            : 'border-border hover:bg-muted/40',
                    ]"
                >
                    <input
                        v-model="selectedOption"
                        type="radio"
                        value="fixed_date"
                        class="mt-1 text-primary focus:ring-primary"
                    />
                    <div>
                        <div class="text-sm font-medium text-foreground">
                            Manter a mesma célula de data fixa
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Todas as células coladas compartilharão a mesma
                            célula de data de origem.
                        </div>
                    </div>
                </label>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    @click="emit('update:open', false)"
                >
                    Cancelar
                </Button>
                <Button size="sm" type="button" @click="handleConfirm">
                    Aplicar Mapeamento
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
