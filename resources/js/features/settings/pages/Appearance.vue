<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/features/settings/components/AppearanceTabs.vue';
import { edit } from '@/routes/appearance';
import Heading from '@/shared/components/Heading.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configurações de aparência',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Configurações de aparência" />

    <h1 class="sr-only">Configurações de aparência</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Configurações de aparência"
            description="Atualize a aparência da sua conta"
        />
        <AppearanceTabs />
    </div>
</template>
