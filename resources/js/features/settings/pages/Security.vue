<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import type { Props as ManagePasskeysProps } from '@/features/settings/components/ManagePasskeys.vue';
import ManagePasskeys from '@/features/settings/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/features/settings/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/features/settings/components/ManageTwoFactor.vue';
import { edit } from '@/routes/security';
import { update } from '@/routes/user-password';
import Heading from '@/shared/components/Heading.vue';
import InputError from '@/shared/components/InputError.vue';
import PasswordInput from '@/shared/components/PasswordInput.vue';
import { Button } from '@/shared/components/ui/button';
import { Label } from '@/shared/components/ui/label';

type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configurações de segurança',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Configurações de segurança" />

    <h1 class="sr-only">Configurações de segurança</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Atualizar senha"
            description="Use uma senha longa e aleatória para manter sua conta segura"
        />

        <Form
            v-bind="update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Senha atual</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Senha atual"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Nova senha</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Nova senha"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirmar senha"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Salvar
                </Button>
            </div>
        </Form>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
