<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/features/auth/components/PasskeyVerify.vue';
import { store } from '@/routes/password/confirm';
import InputError from '@/shared/components/InputError.vue';
import PasswordInput from '@/shared/components/PasswordInput.vue';
import { Button } from '@/shared/components/ui/button';
import { Label } from '@/shared/components/ui/label';
import { Spinner } from '@/shared/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Confirmar senha',
        description:
            'Esta é uma área segura da aplicação. Confirme sua senha antes de continuar.',
    },
});
</script>

<template>
    <Head title="Confirmar senha" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirmar com passkey"
        loading-label="Confirmando..."
        separator="Ou confirme com senha"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">Senha</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    Confirmar senha
                </Button>
            </div>
        </div>
    </Form>
</template>
