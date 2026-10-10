<script setup lang="ts">
import {
    CheckCircle2,
    Clock3,
    Eye,
    FileText,
    History,
    Layers,
    Mail,
    MessageSquare,
    Sparkles,
    X,
} from '@lucide/vue';

interface ActivityItem {
    id: number;
    action: string;
    actor: string;
    metadata: Record<string, unknown> | null;
    created_at: string;
}

defineProps<{
    show: boolean;
    activities: ActivityItem[];
}>();

const emit = defineEmits<{
    close: [];
}>();

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('pt-BR', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function formatActor(actor: string): string {
    if (!actor) {
        return 'Sistema';
    }

    if (actor.toLowerCase() === 'administrator') {
        return 'Administrador';
    }

    if (actor.toLowerCase() === 'system') {
        return 'Sistema';
    }

    return actor;
}

function formatAction(activity: ActivityItem): string {
    const meta = activity.metadata ?? {};
    const systemName =
        typeof meta.system_name === 'string' ? meta.system_name : null;
    const email = typeof meta.email === 'string' ? meta.email : null;

    const map: Record<string, string> = {
        'system.grouped': systemName
            ? `Agrupou o sistema "${systemName}"`
            : 'Agrupou sistema',
        'system.ungrouped': systemName
            ? `Desagrupou o sistema "${systemName}"`
            : 'Desagrupou sistema',
        'system.enabled': systemName
            ? `Habilitou o sistema "${systemName}"`
            : 'Habilitou sistema',
        'system.disabled': systemName
            ? `Desabilitou o sistema "${systemName}"`
            : 'Desabilitou sistema',
        'consideration.updated': systemName
            ? `Atualizou as considerações do sistema "${systemName}"`
            : 'Atualizou as considerações do sistema',
        'suggestions.created': 'Gerou sugestões automáticas com IA',
        'suggestion.accepted': 'Aplicou sugestão da IA',
        'suggestion.applied': 'Aplicou sugestão da IA',
        'suggestion.rejected': 'Rejeitou sugestão da IA',
        'suggestion.dismissed': 'Rejeitou sugestão da IA',
        'public.accessed': 'Acessou o relatório público via link',
        'report.created': 'Criou o relatório',
        'report.finalized': 'Finalizou o relatório',
        'delivery.queued': email
            ? `Disparou envio por e-mail para ${email}`
            : 'Disparou envio por e-mail',
        'delivery.sent': email
            ? `E-mail entregue com sucesso para ${email}`
            : 'E-mail entregue com sucesso',
        'delivery.failed': email
            ? `Falha no envio de e-mail para ${email}`
            : 'Falha no envio de e-mail',
        'recipient.added': email
            ? `Adicionou destinatário (${email})`
            : 'Adicionou destinatário',
        'recipient.revoked': email
            ? `Removeu destinatário (${email})`
            : 'Removeu destinatário',
        'recipient.deleted': email
            ? `Excluiu destinatário (${email})`
            : 'Excluiu destinatário',
        'comment.created': 'Publicou um comentário',
    };

    if (map[activity.action]) {
        return map[activity.action];
    }

    // Fallback humanizado para ações não mapeadas
    return activity.action
        .replace(/[._]/g, ' ')
        .replace(/^./, (c) => c.toUpperCase());
}

function getActionIcon(action: string) {
    if (action.startsWith('system.')) {
        return Layers;
    }

    if (action.startsWith('suggestion')) {
        return Sparkles;
    }

    if (action.startsWith('consideration')) {
        return FileText;
    }

    if (action.startsWith('delivery.') || action.startsWith('recipient.')) {
        return Mail;
    }

    if (action === 'public.accessed') {
        return Eye;
    }

    if (action === 'comment.created') {
        return MessageSquare;
    }

    if (action === 'report.finalized') {
        return CheckCircle2;
    }

    return Clock3;
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[110] flex items-center justify-center bg-black/75 p-4 backdrop-blur-xs"
        @click.self="emit('close')"
    >
        <section
            class="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-2xl"
        >
            <!-- Cabeçalho da Modal -->
            <header
                class="mb-3.5 flex items-center justify-between border-b border-border/60 pb-3"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-muted text-foreground"
                    >
                        <History class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Histórico do Relatório
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Acessos, edições, agrupamentos, envios e comentários
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    title="Fechar"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </header>

            <!-- Lista de Atividades -->
            <div
                v-if="activities.length > 0"
                class="flex-1 space-y-1 divide-y divide-border/50 overflow-y-auto pr-1"
            >
                <div
                    v-for="activity in activities"
                    :key="activity.id"
                    class="-mx-1 flex items-start gap-3 rounded-lg px-1 py-2.5 transition hover:bg-muted/30"
                >
                    <div
                        class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-muted/60 text-muted-foreground"
                    >
                        <component
                            :is="getActionIcon(activity.action)"
                            class="size-3.5"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="text-xs font-semibold text-foreground">
                                {{ formatActor(activity.actor) }}
                            </span>
                            <time class="text-[11px] text-muted-foreground">
                                {{ formatDate(activity.created_at) }}
                            </time>
                        </div>
                        <p class="mt-0.5 text-xs text-foreground/90">
                            {{ formatAction(activity) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Estado Vazio -->
            <div
                v-else
                class="flex flex-col items-center justify-center py-10 text-center"
            >
                <Clock3 class="size-8 text-muted-foreground/60" />
                <p class="mt-2 text-xs font-medium text-foreground">
                    Nenhuma atividade registrada até o momento.
                </p>
                <p class="text-[11px] text-muted-foreground">
                    Novas alterações e acessos aparecerão aqui automaticamente.
                </p>
            </div>
        </section>
    </div>
</template>
