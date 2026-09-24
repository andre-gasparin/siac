<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { destroy } from '@/routes/teams';
import InputError from '@/shared/components/InputError.vue';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import type { Team } from '@/shared/types';

type Props = {
    team: Team;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const confirmationName = ref('');
const formKey = ref(0);

const canDeleteTeam = computed(() => {
    return confirmationName.value === props.team.name;
});

const handleOpenChange = (nextOpen: boolean) => {
    emit('update:open', nextOpen);

    if (!nextOpen) {
        confirmationName.value = '';
        formKey.value++;
    }
};
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="destroy.form(props.team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="handleOpenChange(false)"
            >
                <DialogHeader>
                    <DialogTitle>Excluir unidade?</DialogTitle>
                    <DialogDescription>
                        Esta ação não pode ser desfeita. Isso excluirá a unidade
                        <strong>"{{ props.team.name }}"</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="grid gap-2">
                        <Label for="confirmation-name">
                            Digite
                            <strong>"{{ props.team.name }}"</strong> para
                            confirmar
                        </Label>
                        <Input
                            id="confirmation-name"
                            name="name"
                            data-test="delete-team-name"
                            v-model="confirmationName"
                            placeholder="Nome da unidade"
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancelar </Button>
                    </DialogClose>

                    <Button
                        data-test="delete-team-confirm"
                        variant="destructive"
                        type="submit"
                        :disabled="!canDeleteTeam || processing"
                    >
                        Excluir unidade
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
