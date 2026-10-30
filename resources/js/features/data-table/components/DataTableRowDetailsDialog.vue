<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import type { DataRow } from '@/features/data-table/types';
import { comment as updateComment, details } from '@/routes/data-table/rows';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';

interface RowComment {
    system_id: number;
    system_name: string;
    comment: string;
    author: string | null;
}
interface HistoryItem {
    id: string;
    system_id: number;
    action: string;
    user: string | null;
    created_at: string;
    changes: {
        parameter?: string;
        before?: string | number | null;
        after?: string | number | null;
        count?: number;
        responsible?: string;
        status?: string;
    };
}
interface Reading {
    parameter: string;
    value: number | null;
    creator: string | null;
    updater: string | null;
    created_at: string | null;
    updated_at: string | null;
}
interface Details {
    comments: RowComment[];
    history: HistoryItem[];
    readings: Reading[];
}
const props = defineProps<{
    teamSlug: string;
    row: DataRow;
    systemIds: number[];
    tab: 'history' | 'comments';
}>();
const emit = defineEmits<{ close: [] }>();
const tab = ref(props.tab);
const data = ref<Details>({ comments: [], history: [], readings: [] });
const savedComments = ref<Record<number, string>>({});
const isInitialLoading = ref(true);
const error = ref('');
const reader = useHttp({
    timestamp: props.row.timestamp,
    system_ids: props.systemIds,
});
const writer = useHttp({
    timestamp: props.row.timestamp,
    monitored_system_id: 0,
    comment: '',
});
async function load(silent = false) {
    error.value = '';

    if (!silent) {
        isInitialLoading.value = true;
    }

    try {
        const result = (await reader.get(
            details.url({ current_team: props.teamSlug }),
        )) as Details;
        savedComments.value = Object.fromEntries(
            result.comments.map((item) => [item.system_id, item.comment]),
        );
        data.value = {
            ...result,
            comments: result.comments.map((item) => ({
                ...item,
                comment: '',
            })),
        };
    } catch {
        error.value = 'Não foi possível carregar o histórico e os comentários.';
    } finally {
        isInitialLoading.value = false;
    }
}
async function save(item: RowComment) {
    writer.monitored_system_id = item.system_id;
    writer.comment = item.comment;

    try {
        await writer.put(updateComment.url({ current_team: props.teamSlug }));
        toast.success('Comentário salvo.');
        const trimmed = item.comment.trim();
        savedComments.value[item.system_id] = trimmed;
        item.comment = '';
        await load(true);
        // eslint-disable-next-line vue/no-mutating-props
        props.row.has_comments =
            Object.values(savedComments.value).some(
                (text) => text.trim() !== '',
            ) ||
            data.value.history.some((entry) => entry.action === 'comentário');
    } catch {
        error.value =
            Object.values(writer.errors).join(' ') ||
            'Não foi possível salvar o comentário.';
    }
}
function display(value: string | number | null | undefined) {
    return value == null || value === '' ? '(vazio)' : String(value);
}
function formatDateTime(dateStr: string | null | undefined): string {
    if (!dateStr) {
        return 'data não registrada';
    }

    const normalized = dateStr.includes('T')
        ? dateStr
        : dateStr.replace(' ', 'T');
    const parsed = new Date(normalized);

    if (Number.isNaN(parsed.getTime())) {
        return dateStr;
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
        .format(parsed)
        .replace(',', '');
}
onMounted(() => load());
</script>

<template>
    <Dialog :open="true" @update:open="!$event && emit('close')">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader
                ><DialogTitle>{{
                    tab === 'history'
                        ? 'Histórico de alterações'
                        : 'Comentários'
                }}</DialogTitle
                ><DialogDescription
                    >Coleta de {{ row.date }} às
                    {{ row.time }}</DialogDescription
                ></DialogHeader
            >
            <div class="flex gap-2">
                <button
                    v-for="item in ['history', 'comments'] as const"
                    :key="item"
                    type="button"
                    class="rounded border px-3 py-1 text-sm"
                    :class="
                        tab === item && 'bg-primary text-primary-foreground'
                    "
                    @click="tab = item"
                >
                    {{ item === 'history' ? 'Histórico' : 'Comentários' }}
                </button>
            </div>
            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
                <button type="button" class="underline" @click="load(false)">
                    Tentar novamente
                </button>
            </p>
            <div v-if="isInitialLoading" class="flex justify-center p-8">
                <Loader2 class="size-6 animate-spin" />
            </div>
            <div v-else-if="tab === 'comments'" class="space-y-4">
                <div
                    v-for="item in data.comments"
                    :key="item.system_id"
                    class="space-y-2 rounded border p-3"
                >
                    <label
                        :for="'row-comment-' + item.system_id"
                        class="text-sm font-semibold"
                        >{{ item.system_name }}</label
                    >
                    <div
                        v-if="savedComments[item.system_id]"
                        class="rounded border bg-muted/40 p-2.5 text-xs"
                    >
                        <div class="mb-1 font-medium text-muted-foreground">
                            Comentário registrado{{
                                item.author ? ' por ' + item.author : ''
                            }}:
                        </div>
                        <p
                            class="break-words whitespace-pre-wrap text-foreground"
                        >
                            {{ savedComments[item.system_id] }}
                        </p>
                    </div>
                    <textarea
                        :id="'row-comment-' + item.system_id"
                        v-model="item.comment"
                        :disabled="writer.processing"
                        rows="4"
                        maxlength="10000"
                        class="w-full rounded border bg-background p-2 text-sm"
                        placeholder="Observações desta coleta"
                    />
                    <div
                        class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                    >
                        <span>{{
                            item.author
                                ? 'Último autor: ' + item.author
                                : 'Sem comentário registrado'
                        }}</span
                        ><button
                            type="button"
                            class="rounded bg-primary px-3 py-2 text-primary-foreground disabled:opacity-50"
                            :disabled="writer.processing"
                            @click="save(item)"
                        >
                            Salvar comentário
                        </button>
                    </div>
                </div>
                <div
                    v-if="
                        data.history.some(
                            (entry) => entry.action === 'comentário',
                        )
                    "
                    class="space-y-2 pt-2 text-sm"
                >
                    <h4
                        class="text-xs font-semibold text-muted-foreground uppercase"
                    >
                        Histórico de comentários
                    </h4>
                    <article
                        v-for="item in data.history.filter(
                            (entry) => entry.action === 'comentário',
                        )"
                        :key="item.id"
                        class="space-y-1 rounded border p-3"
                    >
                        <div class="flex flex-wrap justify-between gap-2">
                            <strong>{{ item.action }}</strong
                            ><span class="text-xs text-muted-foreground">{{
                                formatDateTime(item.created_at)
                            }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ item.user || 'Autor não registrado' }} ·
                            {{
                                data.comments.find(
                                    (c) => c.system_id === item.system_id,
                                )?.system_name
                            }}
                        </p>
                        <p class="break-words whitespace-pre-wrap">
                            {{ display(item.changes.before) }} →
                            {{ display(item.changes.after) }}
                        </p>
                    </article>
                </div>
            </div>
            <div v-else class="space-y-3 text-sm">
                <article
                    v-for="item in data.history"
                    :key="item.id"
                    class="space-y-1 rounded border p-3"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <strong
                            >{{ item.action
                            }}{{
                                item.changes.parameter
                                    ? ' · ' + item.changes.parameter
                                    : ''
                            }}</strong
                        ><span class="text-xs text-muted-foreground">{{
                            formatDateTime(item.created_at)
                        }}</span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ item.user || 'Autor não registrado' }} ·
                        {{
                            data.comments.find(
                                (c) => c.system_id === item.system_id,
                            )?.system_name
                        }}
                    </p>
                    <p v-if="item.action === 'envio'">
                        {{ item.changes.count }} valor(es) enviados{{
                            item.changes.responsible
                                ? ' por ' + item.changes.responsible
                                : ''
                        }}
                        ·
                        {{
                            item.changes.status === 'reverted'
                                ? 'Revertido'
                                : 'Concluído'
                        }}
                    </p>
                    <p v-else class="break-words whitespace-pre-wrap">
                        {{ display(item.changes.before) }} →
                        {{ display(item.changes.after) }}
                    </p>
                </article>
                <p v-if="!data.history.length" class="text-muted-foreground">
                    Sem alterações registradas para esta coleta.
                </p>
                <details v-if="data.readings.length" class="rounded border p-3">
                    <summary class="cursor-pointer font-medium">
                        Informações dos registros atuais
                    </summary>
                    <div
                        v-for="reading in data.readings"
                        :key="reading.parameter"
                        class="mt-3 text-xs"
                    >
                        <strong
                            >{{ reading.parameter }}:
                            {{ reading.value }}</strong
                        >
                        <p>
                            Registrado por
                            {{ reading.creator || 'autor não registrado' }} em
                            {{ formatDateTime(reading.created_at) }}.
                        </p>
                        <p>
                            Última atualização por
                            {{ reading.updater || 'autor não registrado' }} em
                            {{ formatDateTime(reading.updated_at) }}.
                        </p>
                    </div>
                </details>
                <p class="text-xs text-muted-foreground">
                    Envios existentes e alterações feitas pela tabela são
                    exibidos aqui. Valores anteriores a esse registro de
                    histórico não podem ser recuperados.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
