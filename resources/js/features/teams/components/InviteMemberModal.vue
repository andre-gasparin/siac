<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { store as storeInvitation } from '@/routes/teams/invitations';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/shared/components/ui/select';
import type { RoleOption, Team } from '@/shared/types';

type Props = {
    team: Team;
    availableRoles: RoleOption[];
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const inviteRole = ref('member');
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    emit('update:open', value);

    if (!value) {
        inviteRole.value = 'member';
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="storeInvitation.form(props.team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <DialogHeader>
                    <DialogTitle>Convidar membro para a unidade</DialogTitle>
                    <DialogDescription>
                        Envie um convite por e-mail para adicionar um novo
                        membro a esta unidade.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="email">Endereço de e-mail</Label>
                        <Input
                            id="email"
                            name="email"
                            data-test="invite-email"
                            type="email"
                            placeholder="colega@exemplo.com"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">Função / Permissão</Label>
                        <Select
                            v-model="inviteRole"
                            name="role"
                            data-test="invite-role"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue
                                    placeholder="Selecione uma função"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in props.availableRoles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.role" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancelar </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="invite-submit"
                        :disabled="processing"
                    >
                        Enviar convite
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
