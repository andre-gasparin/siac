<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import dashboardsRoutes from '@/routes/dashboards';

const props = defineProps<{
    show: boolean;
    currentTeamSlug: string;
}>();

const emit = defineEmits(['close']);

const form = useForm({
    title: '',
    is_public: false,
});

function submit() {
    form.post(
        dashboardsRoutes.store.url({
            current_team: props.currentTeamSlug,
        }),
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                emit('close');
            },
        },
    );
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-xl dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div
                class="flex items-center justify-between border-b pb-3 dark:border-neutral-800"
            >
                <h3
                    class="text-lg font-semibold text-slate-900 dark:text-white"
                >
                    Novo Dashboard
                </h3>
                <button
                    @click="emit('close')"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-neutral-800"
                >
                    <X class="size-5" />
                </button>
            </div>

            <form @submit.prevent="submit" class="mt-4 space-y-4">
                <div>
                    <label
                        class="block text-xs font-medium text-slate-700 dark:text-neutral-300"
                    >
                        Título do Dashboard
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        placeholder="Ex: Operações Diárias"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                    />
                    <div
                        v-if="form.errors.title"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.title }}
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input
                        v-model="form.is_public"
                        type="checkbox"
                        id="is_public"
                        class="size-4 rounded border-slate-300 text-primary focus:ring-primary"
                    />
                    <label
                        for="is_public"
                        class="text-xs text-slate-700 dark:text-neutral-300"
                    >
                        Compartilhar com todo o time (`public`)
                    </label>
                </div>

                <div
                    class="mt-6 flex items-center justify-end gap-2 border-t pt-3 dark:border-neutral-800"
                >
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-primary px-4 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Criando...' : 'Criar Dashboard' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
