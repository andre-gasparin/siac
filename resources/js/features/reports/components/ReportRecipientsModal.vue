<script setup lang="ts">
import { Check, Copy, ExternalLink, X } from '@lucide/vue';
import { ref } from 'vue';

defineProps<{
    show: boolean;
    intendedRecipients: Array<{
        user_id: number;
        name: string;
        email: string;
        recipient_id: number | null;
        last_sent_at: string | null;
        send_count: number;
    }>;
    recipients: Array<{
        id: number;
        user_id: number | null;
        name: string;
        email: string;
        access_token?: string;
        public_url?: string;
        revoked_at: string | null;
        last_sent_at: string | null;
        send_count: number;
    }>;
}>();

const emit = defineEmits<{
    close: [];
    revoke: [recipientId: number];
}>();

const copiedRecipientId = ref<number | null>(null);

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
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/60 p-4"
        @click.self="emit('close')"
    >
        <section
            class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-card p-5 shadow-2xl"
        >
            <header class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-foreground">Destinatários</h2>
                    <p class="text-xs text-muted-foreground">
                        Próximo envio e histórico de acessos individuais
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
                class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/20"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3
                            class="text-sm font-semibold text-emerald-900 dark:text-emerald-200"
                        >
                            Receberão no próximo envio
                        </h3>
                        <p
                            class="text-xs text-emerald-800/70 dark:text-emerald-300/70"
                        >
                            Membros atuais desta unidade
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white"
                    >
                        {{ intendedRecipients.length }}
                    </span>
                </div>
                <div class="grid gap-2">
                    <div
                        v-for="recipient in intendedRecipients"
                        :key="recipient.user_id"
                        class="flex items-center justify-between gap-3 rounded-xl bg-card px-3 py-2.5 text-sm"
                    >
                        <div class="min-w-0">
                            <strong class="block truncate text-foreground">{{
                                recipient.name
                            }}</strong>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{ recipient.email }}</span
                            >
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold"
                            :class="
                                recipient.last_sent_at
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'
                            "
                        >
                            {{
                                recipient.last_sent_at
                                    ? 'Receberá novamente'
                                    : 'Primeiro envio'
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <h3
                class="mt-5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Histórico deste relatório
            </h3>
            <div
                v-for="recipient in recipients"
                :key="recipient.id"
                class="flex items-center justify-between gap-3 border-b py-3 text-sm"
            >
                <div>
                    <strong class="text-foreground">{{
                        recipient.name
                    }}</strong>
                    <p class="text-muted-foreground">
                        {{ recipient.email }} ·
                        {{ recipient.send_count }} envio(s)
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="recipient.public_url && !recipient.revoked_at"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1 text-xs font-medium text-foreground shadow-2xs transition hover:bg-muted"
                        title="Copiar link individual de acesso"
                        @click="copyLink(recipient)"
                    >
                        <Check
                            v-if="copiedRecipientId === recipient.id"
                            class="size-3 text-emerald-600"
                        />
                        <Copy v-else class="size-3 text-muted-foreground" />
                        <span>{{
                            copiedRecipientId === recipient.id
                                ? 'Copiado!'
                                : 'Copiar link'
                        }}</span>
                    </button>
                    <a
                        v-if="recipient.public_url && !recipient.revoked_at"
                        :href="recipient.public_url"
                        target="_blank"
                        class="inline-flex size-7 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground shadow-2xs transition hover:bg-muted hover:text-foreground"
                        title="Abrir relatório deste destinatário em nova aba"
                    >
                        <ExternalLink class="size-3.5" />
                    </a>
                    <button
                        v-if="!recipient.revoked_at"
                        type="button"
                        class="rounded border border-red-300 px-3 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30"
                        @click="emit('revoke', recipient.id)"
                    >
                        Revogar
                    </button>
                    <span v-else class="text-xs text-red-600">Revogado</span>
                </div>
            </div>
            <p
                v-if="recipients.length === 0"
                class="mt-2 rounded-xl border border-dashed p-4 text-center text-xs text-muted-foreground"
            >
                Nenhum envio foi realizado ainda.
            </p>
        </section>
    </div>
</template>
