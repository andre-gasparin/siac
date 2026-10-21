<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { store } from '@/routes/reports';
import DatePicker from '@/shared/components/DatePicker.vue';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';

const open = defineModel<boolean>('open', { required: true });
defineProps<{ teamSlug: string }>();
const now = new Date();
const form = useForm({
    date_reference: [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-'),
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader
                ><DialogTitle>Novo relatório</DialogTitle
                ><DialogDescription
                    >Selecione a data de referência do
                    relatório.</DialogDescription
                ></DialogHeader
            >
            <form
                class="space-y-4"
                @submit.prevent="
                    form.post(store.url({ current_team: teamSlug }))
                "
            >
                <DatePicker
                    v-model="form.date_reference"
                    placeholder="Data de referência"
                />
                <p
                    v-if="form.errors.date_reference"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.date_reference }}
                </p>
                <DialogFooter
                    ><Button type="submit" :disabled="form.processing"
                        >Criar relatório</Button
                    ></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>
</template>
