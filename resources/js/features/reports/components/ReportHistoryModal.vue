<script setup lang="ts">
import { Clock3, X } from '@lucide/vue';

defineProps<{
    show: boolean;
    activities: Array<{
        id: number;
        action: string;
        actor: string;
        metadata: Record<string, unknown> | null;
        created_at: string;
    }>;
}>();

const emit = defineEmits<{
    close: [];
}>();

function formatDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
}

function formatAction(action: string): string {
    const map: Record<string, string> = {
        'public.accessed': 'Acessou o relatório público',
        'report.created': 'Criou o relatório',
        'report.finalized': 'Finalizou o relatório',
        'delivery.queued': 'Disparou envio por e-mail',
        'delivery.sent': 'E-mail entregue com sucesso',
        'delivery.failed': 'Falha no envio de e-mail',
        'recipient.added': 'Adicionou destinatário',
        'recipient.revoked': 'Removeu destinatário',
        'recipient.deleted': 'Excluiu destinatário',
        'comment.created': 'Publicou comentário',
        'suggestion.applied': 'Aplicou sugestão da IA',
        'suggestion.dismissed': 'Rejeitou sugestão da IA',
    };

    return map[action] ?? action;
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/60 p-4"
        @click.self="emit('close')"
    >
        <section
            class="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-card p-5 shadow-2xl"
        >
            <header class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-foreground">
                        Histórico do relatório
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Alterações, acessos, envios e comentários
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </header>
            <div
                v-for="activity in activities"
                :key="activity.id"
                class="flex gap-3 border-b py-3 text-sm"
            >
                <Clock3 class="mt-0.5 size-4 text-muted-foreground" />
                <div>
                    <strong class="text-foreground">{{
                        activity.actor
                    }}</strong>
                    <p class="text-foreground/80">
                        {{ formatAction(activity.action) }}
                    </p>
                    <time class="text-xs text-muted-foreground">{{
                        formatDate(activity.created_at)
                    }}</time>
                </div>
            </div>
        </section>
    </div>
</template>
