<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Check,
    ChevronRight,
    Copy,
    ExternalLink,
    Loader2,
    Mail,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { formatDateBr } from '@/features/reports/reportMetrics';
import {
    destroy as destroyRecipient,
    resend as resendRecipient,
    store as storeRecipient,
} from '@/routes/reports/recipients';

export interface ReportRecipientItem {
    id: number;
    user_id: number | null;
    name: string;
    email: string;
    access_token?: string;
    public_url?: string;
    revoked_at: string | null;
    first_accessed_at?: string | null;
    first_accessed_ip?: string | null;
    last_accessed_at?: string | null;
    last_accessed_ip?: string | null;
    access_count?: number;
    last_sent_at: string | null;
    last_failed_at?: string | null;
    send_count: number;
}

export interface DeliveryHistoryEntry {
    id: number;
    action: string;
    status: 'queued' | 'sent' | 'failed';
    delivery_type: string;
    recipient_name: string | null;
    recipient_email: string | null;
    recipient_count: number | null;
    actor: string;
    error: string | null;
    batch: string | null;
    created_at: string | null;
}

const props = withDefaults(
    defineProps<{
        show: boolean;
        recipients: ReportRecipientItem[];
        deliveryHistory?: DeliveryHistoryEntry[];
        teamSlug: string;
        reportId: number;
        reportStatus?: string;
        reportDateReference?: string | null;
    }>(),
    {
        deliveryHistory: () => [],
        reportStatus: undefined,
        reportDateReference: null,
    },
);

const emit = defineEmits<{
    close: [];
}>();

const showAddForm = ref(false);
const showHistory = ref(false);
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

const sentCount = computed(
    () => activeRecipients.value.filter((r) => r.send_count > 0).length,
);

const accessedCount = computed(
    () =>
        activeRecipients.value.filter((r) =>
            Boolean(r.first_accessed_at || r.last_accessed_at),
        ).length,
);

const pendingSendCount = computed(
    () => activeRecipients.value.filter((r) => r.send_count === 0).length,
);

const formattedReferenceDate = computed(() =>
    props.reportDateReference ? formatDateBr(props.reportDateReference) : null,
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
                showAddForm.value = false;
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

function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function deliveryTypeLabel(type: string): string {
    switch (type) {
        case 'initial':
            return 'Emissão inicial';
        case 'resend_all':
            return 'Reenvio em lote';
        case 'resend_individual':
            return 'Reenvio individual';
        case 'interactions':
            return 'Notificação de interação';
        default:
            return 'Envio';
    }
}

function deliveryStatusLabel(status: DeliveryHistoryEntry['status']): string {
    switch (status) {
        case 'sent':
            return 'Enviado';
        case 'failed':
            return 'Falhou';
        default:
            return 'Na fila';
    }
}

function deliveryStatusClass(status: DeliveryHistoryEntry['status']): string {
    switch (status) {
        case 'sent':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
        case 'failed':
            return 'bg-destructive/15 text-destructive';
        default:
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
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
            class="flex max-h-[92vh] w-full max-w-5xl flex-col rounded-2xl border border-border bg-card text-card-foreground shadow-2xl"
        >
            <!-- Cabeçalho -->
            <header
                class="flex items-start justify-between gap-4 border-b border-border/70 px-6 py-4"
            >
                <div>
                    <h2
                        class="text-base font-semibold tracking-tight text-foreground"
                    >
                        Destinatários · administração
                    </h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Controle de envio e acesso pelo link individual
                        <template v-if="formattedReferenceDate">
                            · {{ formattedReferenceDate }}
                        </template>
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-lg border border-border/70 bg-background text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    title="Fechar"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </header>

            <!-- Barra de Resumo + Botão Incluir -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-6 py-3"
            >
                <div
                    class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-muted-foreground"
                >
                    <span>
                        <strong class="font-semibold text-foreground">{{
                            activeRecipients.length
                        }}</strong>
                        cadastrado{{ activeRecipients.length === 1 ? '' : 's' }}
                    </span>
                    <span>
                        <strong class="font-semibold text-foreground">{{
                            sentCount
                        }}</strong>
                        com envio registrado
                    </span>
                    <span>
                        <strong class="font-semibold text-foreground">{{
                            accessedCount
                        }}</strong>
                        acesso{{ accessedCount === 1 ? '' : 's' }} registrado{{
                            accessedCount === 1 ? '' : 's'
                        }}
                    </span>
                    <span>
                        <strong class="font-semibold text-foreground">{{
                            pendingSendCount
                        }}</strong>
                        {{
                            reportStatus === 'completed'
                                ? 'aguardando envio'
                                : 'aguardando emissão'
                        }}
                    </span>
                </div>

                <button
                    type="button"
                    data-test="toggle-add-recipient-button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-xs font-medium text-foreground shadow-2xs transition hover:bg-muted"
                    @click="showAddForm = !showAddForm"
                >
                    <Plus class="size-3.5" />
                    <span>Incluir destinatário</span>
                </button>
            </div>

            <!-- Conteúdo rolável -->
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-4">
                <!-- Formulário Inline para Incluir Destinatário -->
                <div
                    v-if="showAddForm"
                    class="rounded-xl border border-border bg-muted/30 p-3"
                >
                    <form
                        class="flex flex-wrap items-center gap-2"
                        @submit.prevent="handleAddRecipient"
                    >
                        <input
                            v-model="newEmail"
                            type="email"
                            placeholder="E-mail do destinatário..."
                            required
                            class="h-8 min-w-[220px] flex-1 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-hidden"
                        />
                        <input
                            v-model="newName"
                            type="text"
                            placeholder="Nome (opcional)"
                            class="h-8 w-48 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-hidden"
                        />
                        <button
                            type="submit"
                            :disabled="isAdding || !newEmail.trim()"
                            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="isAdding"
                                class="size-3.5 animate-spin"
                            />
                            <Plus v-else class="size-3.5" />
                            <span>Adicionar</span>
                        </button>
                    </form>
                    <p
                        v-if="errorMessage"
                        class="mt-2 text-xs font-medium text-destructive"
                    >
                        {{ errorMessage }}
                    </p>
                </div>

                <!-- Tabela de Destinatários -->
                <div
                    class="overflow-x-auto rounded-xl border border-border/80 bg-card"
                >
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b border-border/70 bg-muted/35 text-[11px] font-semibold text-muted-foreground"
                            >
                                <th class="px-3.5 py-2.5">Destinatário</th>
                                <th class="px-3.5 py-2.5">E-mail</th>
                                <th class="px-3.5 py-2.5">Último envio</th>
                                <th class="px-3.5 py-2.5">
                                    Primeiro acesso pelo link
                                </th>
                                <th class="px-3.5 py-2.5 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="rec in activeRecipients"
                                :key="rec.id"
                                class="transition-colors hover:bg-muted/20"
                            >
                                <!-- Destinatário -->
                                <td class="px-3.5 py-3 align-top">
                                    <div class="font-semibold text-foreground">
                                        {{ rec.name }}
                                    </div>
                                </td>

                                <!-- E-mail -->
                                <td
                                    class="px-3.5 py-3 align-top break-all text-muted-foreground"
                                >
                                    {{ rec.email }}
                                </td>

                                <!-- Último envio -->
                                <td class="px-3.5 py-3 align-top">
                                    <template v-if="rec.last_sent_at">
                                        <div
                                            class="font-semibold text-emerald-700 dark:text-emerald-400"
                                        >
                                            {{
                                                formatDateTime(rec.last_sent_at)
                                            }}
                                        </div>
                                        <div
                                            class="mt-0.5 text-[11px] text-muted-foreground"
                                        >
                                            {{ rec.send_count }} envio{{
                                                rec.send_count > 1 ? 's' : ''
                                            }}
                                            registrado{{
                                                rec.send_count > 1 ? 's' : ''
                                            }}
                                        </div>
                                    </template>
                                    <template v-else-if="rec.last_failed_at">
                                        <div
                                            class="font-semibold text-destructive"
                                        >
                                            Falha no envio
                                        </div>
                                        <div
                                            class="mt-0.5 text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                formatDateTime(
                                                    rec.last_failed_at,
                                                )
                                            }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div
                                            class="font-semibold text-amber-700 dark:text-amber-400"
                                        >
                                            Nenhum envio registrado
                                        </div>
                                        <div
                                            class="mt-0.5 text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                reportStatus === 'completed'
                                                    ? 'Aguardando disparo do e-mail'
                                                    : 'O relatório ainda não foi emitido'
                                            }}
                                        </div>
                                    </template>
                                </td>

                                <!-- Primeiro acesso pelo link -->
                                <td class="px-3.5 py-3 align-top">
                                    <template
                                        v-if="
                                            rec.first_accessed_at ||
                                            rec.last_accessed_at
                                        "
                                    >
                                        <div
                                            class="font-semibold text-emerald-700 dark:text-emerald-400"
                                        >
                                            {{
                                                formatDateTime(
                                                    rec.first_accessed_at ||
                                                        rec.last_accessed_at,
                                                )
                                            }}
                                        </div>
                                        <div
                                            class="mt-0.5 text-[11px] text-muted-foreground"
                                        >
                                            <span
                                                v-if="
                                                    rec.first_accessed_ip ||
                                                    rec.last_accessed_ip
                                                "
                                            >
                                                IP:
                                                {{
                                                    rec.first_accessed_ip ||
                                                    rec.last_accessed_ip
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    (rec.access_count ?? 0) > 1
                                                "
                                            >
                                                <template
                                                    v-if="
                                                        rec.first_accessed_ip ||
                                                        rec.last_accessed_ip
                                                    "
                                                >
                                                    ·
                                                </template>
                                                {{ rec.access_count }} acessos
                                            </span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div
                                            class="font-semibold text-amber-700 dark:text-amber-400"
                                        >
                                            Sem acesso registrado
                                        </div>
                                        <div
                                            class="mt-0.5 text-[11px] text-muted-foreground"
                                        >
                                            Disponível após emissão e acesso ao
                                            link
                                        </div>
                                    </template>
                                </td>

                                <!-- Ações -->
                                <td class="px-3.5 py-3 align-top">
                                    <div
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <button
                                            type="button"
                                            :disabled="
                                                reportStatus !== 'completed' ||
                                                resendingRecipientId === rec.id
                                            "
                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-[11.5px] font-medium whitespace-nowrap text-foreground shadow-2xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-45"
                                            :title="
                                                reportStatus === 'completed'
                                                    ? 'Enviar ou reenviar e-mail com link individual'
                                                    : 'Disponível após finalizar o relatório'
                                            "
                                            @click="
                                                handleResendIndividual(rec.id)
                                            "
                                        >
                                            <Loader2
                                                v-if="
                                                    resendingRecipientId ===
                                                    rec.id
                                                "
                                                class="size-3.5 animate-spin"
                                            />
                                            <Mail v-else class="size-3.5" />
                                            <span>Enviar / reenviar</span>
                                        </button>

                                        <button
                                            v-if="rec.public_url"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground shadow-2xs transition hover:bg-muted hover:text-foreground"
                                            :title="
                                                copiedRecipientId === rec.id
                                                    ? 'Link copiado!'
                                                    : 'Copiar link individual de acesso'
                                            "
                                            @click="copyLink(rec)"
                                        >
                                            <Check
                                                v-if="
                                                    copiedRecipientId === rec.id
                                                "
                                                class="size-3.5 text-emerald-600"
                                            />
                                            <Copy v-else class="size-3.5" />
                                        </button>

                                        <a
                                            v-if="rec.public_url"
                                            :href="rec.public_url"
                                            target="_blank"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground shadow-2xs transition hover:bg-muted hover:text-foreground"
                                            title="Abrir relatório deste destinatário em nova aba"
                                        >
                                            <ExternalLink class="size-3.5" />
                                        </a>

                                        <button
                                            type="button"
                                            :disabled="
                                                removingRecipientId === rec.id
                                            "
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground shadow-2xs transition hover:bg-destructive/10 hover:text-destructive"
                                            title="Remover destinatário"
                                            @click="
                                                handleRemoveRecipient(rec.id)
                                            "
                                        >
                                            <Loader2
                                                v-if="
                                                    removingRecipientId ===
                                                    rec.id
                                                "
                                                class="size-3.5 animate-spin"
                                            />
                                            <Trash2 v-else class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="activeRecipients.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-xs text-muted-foreground"
                                >
                                    Nenhum destinatário ativo no momento. Clique
                                    em <strong>Incluir destinatário</strong>
                                    acima.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Histórico de Envios / Reenvios -->
                <div class="rounded-xl border border-border/70 bg-muted/15">
                    <button
                        type="button"
                        data-test="delivery-history-toggle"
                        class="flex w-full items-center gap-1.5 px-3.5 py-2.5 text-left text-xs font-semibold text-primary transition hover:bg-muted/30"
                        @click="showHistory = !showHistory"
                    >
                        <ChevronRight
                            class="size-3.5 transition-transform"
                            :class="showHistory ? 'rotate-90' : ''"
                        />
                        <span>
                            Histórico de envios / reenvios ({{
                                deliveryHistory.length
                            }})
                        </span>
                    </button>

                    <div
                        v-if="showHistory"
                        class="border-t border-border/60 px-3.5 py-2.5"
                    >
                        <div
                            v-if="deliveryHistory.length === 0"
                            class="py-3 text-center text-xs text-muted-foreground"
                        >
                            Nenhum envio ou reenvio registrado para este
                            relatório até o momento.
                        </div>
                        <div
                            v-else
                            class="max-h-56 space-y-1.5 overflow-y-auto"
                        >
                            <div
                                v-for="entry in deliveryHistory"
                                :key="entry.id"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/60 bg-card px-3 py-2 text-xs"
                            >
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                            :class="
                                                deliveryStatusClass(
                                                    entry.status,
                                                )
                                            "
                                        >
                                            {{
                                                deliveryStatusLabel(
                                                    entry.status,
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="font-semibold text-foreground"
                                        >
                                            {{
                                                deliveryTypeLabel(
                                                    entry.delivery_type,
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-if="
                                                entry.recipient_name ||
                                                entry.recipient_email
                                            "
                                            class="text-muted-foreground"
                                        >
                                            ·
                                            {{
                                                entry.recipient_name ||
                                                entry.recipient_email
                                            }}
                                            <template
                                                v-if="
                                                    entry.recipient_name &&
                                                    entry.recipient_email
                                                "
                                            >
                                                ({{ entry.recipient_email }})
                                            </template>
                                        </span>
                                        <span
                                            v-else-if="entry.recipient_count"
                                            class="text-muted-foreground"
                                        >
                                            · {{ entry.recipient_count }}
                                            destinatário(s)
                                        </span>
                                    </div>
                                    <p
                                        v-if="entry.error"
                                        class="mt-1 text-[11px] text-destructive"
                                    >
                                        Erro: {{ entry.error }}
                                    </p>
                                </div>
                                <div
                                    class="shrink-0 text-right text-[11px] text-muted-foreground"
                                >
                                    <div>
                                        {{ formatDateTime(entry.created_at) }}
                                    </div>
                                    <div
                                        v-if="
                                            entry.actor &&
                                            entry.actor !== 'Sistema'
                                        "
                                        class="text-[10px]"
                                    >
                                        por {{ entry.actor }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Destinatários Removidos/Revogados -->
                <details
                    v-if="revokedRecipients.length > 0"
                    class="rounded-xl border border-border/60 bg-muted/15 p-3 text-xs"
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
                            <span
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                >Removido</span
                            >
                        </div>
                    </div>
                </details>
            </div>

            <!-- Rodapé Informativo -->
            <footer
                class="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 px-6 py-3 text-[11px] text-muted-foreground"
            >
                <span>
                    Cada destinatário recebe um link individual. O acesso pelo
                    link registra automaticamente data, hora e IP.
                </span>
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
