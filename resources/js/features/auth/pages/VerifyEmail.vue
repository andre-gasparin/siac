<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import TextLink from '@/shared/components/TextLink.vue';
import { Button } from '@/shared/components/ui/button';
import { Spinner } from '@/shared/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Verificação de e-mail',
        description:
            'Verifique seu endereço de e-mail clicando no link que acabamos de enviar para você.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Verificação de e-mail" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Um novo link de verificação foi enviado para o endereço de e-mail
        informado no cadastro.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Reenviar e-mail de verificação
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Sair
        </TextLink>
    </Form>
</template>
