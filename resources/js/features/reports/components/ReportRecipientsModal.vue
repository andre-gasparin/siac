<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    ExternalLink,
    Loader2,
    Plus,
    RotateCw,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroy as destroyRecipient,
    resend as resendRecipient,
    store as storeRecipient,
} from '@/routes/reports/recipients';

interface Recipient {
    id: number;
    user_id: number | null;
    name: string;
    email: string;
    access_token?: string;
    public_url?: string;
    revoked_at: string | null;
    last_accessed_at?: string | null;
    last_sent_at: string | null;
    send_count: number;
}

const props = defineProps<{
    show: boolean;
    recipients: Recipient[];
    teamSlug: string;
    reportId: number;
    reportStatus?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const newEmail = ref('');
const newName = ref('');
const isAdding = ref(false);
const errorMessage = ref('');
const copiedRecipientId = ref<number | null>(null);
const resendingRecipientId = ref<number | null>(null);
const removingRecipientId = ref<number | null>(null);

const activeRecipients = computed(() =>
    props.recipients.filter((r) => !r.revoked_at),
);

const revokedRecipients = computed(() =>
    props.recipients.filter((r) => Boolean(r.revoked_at)),
);

function copyLink(recipient: { id: number; public_url?: string }) {
    if (!recipient.public_url) {
        return;
    }

    void navigator.clipboard.writeText(recipient.public_url);
    copiedRecipientId.value = recipient.id;
    setTimeout(() => {
        if (copiedRecipientId.value === recipient.id) {
            copiedRecipientId.value = null;
        }
    }, 1500);
}

function handleAddRecipient() {
    const email = newEmail.value.trim();

    if (!email || isAdding.value) {
        return;
    }

    isAdding.value = true;
    errorMessage.value = '';

    router.post(
        storeRecipient.url({
            current_team: props.teamSlug,
            report: props.reportId,
        }),
        {
            email,
            name: newName.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newEmail.value = '';
                newName.value = '';
                errorMessage.value = '';
            },
            onError: (errors) => {
                errorMessage.value =
                    errors.email ||
                    errors.name ||
                    'Erro ao adicionar destinatário.';
            },
            onFinish: () => {
                isAdding.value = false;
            },
        },
    );
}

function handleRemoveRecipient(recipientId: number) {
    if (removingRecipientId.value !== null) {
        return;
    }

    removingRecipientId.value = recipientId;
    router.delete(
        destroyRecipient.url({
            current_team: props.teamSlug,
            report: props.reportId,
            recipient: recipientId,
        }),
        {
            preserveScroll: true,
            onFinish: () => {
                removingRecipientId.value = null;
            },
        },
    );
}

function handleResendIndividual(recipientId: number) {
    if (resendingRecipientId.value !== null) {
        return;
    }

    resendingRecipientId.value = recipientId;
    router.post(
        resendRecipient.url({
            current_team: props.teamSlug,
            report: props.reportId,
            recipient: recipientId,
        }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                resendingRecipientId.value = null;
            },
        },
    );
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    } catch {
        return value;
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        @click.self="emit('close')"
    >
        <section
            class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-2xl"
        >
            <!-- Cabeçalho -->
            <header
                class="mb-3.5 flex items-center justify-between border-b border-border/60 pb-3"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Users class="size-4" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold text-foreground">
                                Destinatários do Relatório
                            </h2>
                            <span
                                class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                            >
                                {{ activeRecipients.length }} ativo{{
                                    activeRecipients.length === 1 ? '' : 's'
                                }}
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Gerencie os e-mails que recebem e têm acesso
                            individual a este relatório
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    title="Fechar"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </header>

            <!-- Formulário Adicionar Novo Destinatário -->
            <form
                class="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-border/80 bg-muted/30 p-2.5"
                @submit.prevent="handleAddRecipient"
            >
                <input
                    v-model="newEmail"
                    type="email"
                    placeholder="E-mail do destinatário..."
                    required
                    class="h-8 min-w-[200px] flex-1 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-hidden"
                />
                <input
                    v-model="newName"
                    type="text"
                    placeholder="Nome (opcional)"
                    class="h-8 w-36 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-hidden"
                />
                <button
                    type="submit"
                    :disabled="isAdding || !newEmail.trim()"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90 disabled:opacity-50"
                >
                    <Loader2 v-if="isAdding" class="size-3.5 animate-spin" />
                    <Plus v-else class="size-3.5" />
                    <span>Adicionar</span>
                </button>
            </form>

            <p
                v-if="errorMessage"
                class="mb-2 text-xs font-medium text-destructive"
            >
                {{ errorMessage }}
            </p>

            <!-- Lista de Destinatários Ativos -->
            <div class="max-h-[46vh] flex-1 space-y-1.5 overflow-y-auto pr-1">
                <div
                    v-for="rec in activeRecipients"
                    :key="rec.id"
                    class="flex items-center justify-between gap-3 rounded-xl border border-border/70 bg-card px-3 py-2 text-xs shadow-2xs transition hover:bg-muted/30"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <strong
                                class="truncate font-medium text-foreground"
                            >
                                {{ rec.name }}
                            </strong>
                            <span
                                v-if="rec.send_count > 0"
                                class="inline-flex shrink-0 items-center rounded-full bg-blue-500/10 px-1.5 py-0.5 text-[10px] font-medium text-blue-600 dark:text-blue-400"
                                :title="
                                    rec.last_sent_at
                                        ? `Último envio: ${formatDate(rec.last_sent_at)}`
                                        : ''
                                "
                            >
                                {{ rec.send_count }} envio{{
                                    rec.send_count > 1 ? 's' : ''
                                }}
                            </span>
                            <span
                                v-else
                                class="inline-flex shrink-0 items-center rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-600 dark:text-amber-400"
                            >
                                Não enviado
                            </span>
                            <span
                                v-if="rec.last_accessed_at"
                                class="inline-flex shrink-0 items-center rounded-full bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                :title="`Acessou em ${formatDate(rec.last_accessed_at)}`"
                            >
                                Acessou
                            </span>
                        </div>
                        <span
                            class="block truncate text-[11px] text-muted-foreground"
                        >
                            {{ rec.email }}
                        </span>
                    </div>

                    <!-- Ações Individuais -->
                    <div class="flex shrink-0 items-center gap-1">
                        <!-- Reenviar Individual -->
                        <button
                            type="button"
                            :disabled="
                                reportStatus !== 'completed' ||
                                resendingRecipientId === rec.id
                            "
                            class="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground shadow-2xs transition hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                            :title="
                                reportStatus === 'completed'
                                    ? 'Reenviar para este e-mail'
                                    : 'Reenvio individual disponível após finalizar o relatório'
                            "
                            @click="handleResendIndividual(rec.id)"
                        >
                            <Loader2
                                v-if="resendingRecipientId === rec.id"
                                class="size-3.5 animate-spin"
                            />
                            <RotateCw v-else class="size-3.5" />
                        </button>

                        <!-- Copiar Link -->
                        <button
                            v-if="rec.public_url"
                            type="button"
                            class="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground shadow-2xs transition hover:bg-muted hover:text-foreground"
                            :title="
                                copiedRecipientId === rec.id
                                    ? 'Link copiado!'
                                    : 'Copiar link individual de acesso'
                            "
                            @click="copyLink(rec)"
                        >
                            <Check
                                v-if="copiedRecipientId === rec.id"
                                class="size-3.5 text-emerald-600"
                            />
                            <Copy v-else class="size-3.5" />
                        </button>

                        <!-- Abrir em nova aba -->
                        <a
                            v-if="rec.public_url"
                            :href="rec.public_url"
                            target="_blank"
                            class="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground shadow-2xs transition hover:bg-muted hover:text-foreground"
                            title="Abrir relatório deste destinatário em nova aba"
                        >
                            <ExternalLink class="size-3.5" />
                        </a>

                        <!-- Remover -->
                        <button
                            type="button"
                            :disabled="removingRecipientId === rec.id"
                            class="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground shadow-2xs transition hover:bg-destructive/10 hover:text-destructive"
                            title="Remover destinatário"
                            @click="handleRemoveRecipient(rec.id)"
                        >
                            <Loader2
                                v-if="removingRecipientId === rec.id"
                                class="size-3.5 animate-spin"
                            />
                            <Trash2 v-else class="size-3.5" />
                        </button>
                    </div>
                </div>

                <div
                    v-if="activeRecipients.length === 0"
                    class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                >
                    Nenhum destinatário ativo no momento. Adicione um e-mail
                    acima.
                </div>
            </div>

            <!-- Destinatários Removidos/Revogados (se houver) -->
            <details
                v-if="revokedRecipients.length > 0"
                class="mt-2.5 rounded-xl border border-border/60 bg-muted/15 p-2 text-xs"
            >
                <summary
                    class="cursor-pointer font-medium text-muted-foreground hover:text-foreground"
                >
                    Destinatários removidos ({{ revokedRecipients.length }})
                </summary>
                <div class="mt-2 space-y-1">
                    <div
                        v-for="rec in revokedRecipients"
                        :key="rec.id"
                        class="flex items-center justify-between rounded-lg px-2 py-1 text-muted-foreground"
                    >
                        <span class="truncate"
                            >{{ rec.name }} ({{ rec.email }})</span
                        >
                        <span class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                            >Removido</span
                        >
                    </div>
                </div>
            </details>

            <!-- Rodapé Informativo -->
            <footer
                class="mt-3.5 flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-3 text-[11px] text-muted-foreground"
            >
                <span
                    >Novos relatórios gerados herdarão automaticamente os
                    destinatários ativos desta lista.</span
                >
                <button
                    type="button"
                    class="rounded-lg border border-border bg-muted/40 px-3 py-1 font-medium text-foreground transition hover:bg-muted"
                    @click="emit('close')"
                >
                    Concluir
                </button>
            </footer>
        </section>
    </div>
</template>
