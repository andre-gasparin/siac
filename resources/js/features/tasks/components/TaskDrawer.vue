<script setup lang="ts">
import {
    Archive,
    Calendar,
    Check,
    CheckSquare,
    Download,
    File,
    FileSpreadsheet,
    FileText,
    Image,
    Paperclip,
    Plus,
    Trash2,
    UploadCloud,
    UserCheck,
    X,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyAttachmentRoute,
    download as downloadAttachmentRoute,
    store as storeAttachmentRoute,
} from '@/routes/tasks/attachments';
import {
    destroy as destroyCardRoute,
    toggle as toggleCardRoute,
    update as updateCardRoute,
} from '@/routes/tasks/cards';
import {
    destroy as destroyChecklistRoute,
    store as storeChecklistRoute,
    update as updateChecklistRoute,
} from '@/routes/tasks/checklists';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/shared/components/ui/sheet';
import type {
    Task,
    TaskAttachment,
    TaskChecklistItem,
    TaskUser,
} from '../types';

const props = defineProps<{
    open: boolean;
    boardId: number;
    columnName?: string;
    task: Task | null;
    administrators: TaskUser[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'task-updated', task: Task): void;
    (e: 'task-deleted', taskId: number): void;
}>();

// Local editable state for current task
const localTask = ref<Task | null>(null);
const title = ref('');
const description = ref('');
const dueDate = ref('');
const assignedTo = ref<number | null>(null);
const isCompleted = ref(false);

const newChecklistTitle = ref('');
const isUploading = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.task,
    (newTask) => {
        if (newTask) {
            localTask.value = JSON.parse(JSON.stringify(newTask));
            title.value = newTask.title;
            description.value = newTask.description ?? '';
            dueDate.value = newTask.due_date ?? '';
            assignedTo.value = newTask.assigned_to_user_id;
            isCompleted.value = newTask.is_completed;
        } else {
            localTask.value = null;
        }
    },
    { immediate: true, deep: true },
);

function getXsrfToken(): string {
    const match = document.cookie.match(
        new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'),
    );

    return match ? decodeURIComponent(match[2]) : '';
}

async function saveTaskDetails() {
    if (!props.task) {
        return;
    }

    try {
        const url = updateCardRoute([props.boardId, props.task.id]).url;
        const res = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                title: title.value,
                description: description.value || null,
                due_date: dueDate.value || null,
                assigned_to_user_id: assignedTo.value
                    ? Number(assignedTo.value)
                    : null,
            }),
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                localTask.value = data.task;
                emit('task-updated', data.task);
            }
        }
    } catch {
        toast.error('Erro ao salvar alterações da tarefa.');
    }
}

async function handleToggleCompleted() {
    if (!props.task) {
        return;
    }

    try {
        const url = toggleCardRoute([props.boardId, props.task.id]).url;
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                localTask.value = data.task;
                isCompleted.value = data.task.is_completed;
                emit('task-updated', data.task);
            }
        }
    } catch {
        toast.error('Erro ao alterar status da tarefa.');
    }
}

async function handleAddChecklist() {
    const text = newChecklistTitle.value.trim();

    if (!text || !props.task) {
        return;
    }

    try {
        const url = storeChecklistRoute([props.boardId, props.task.id]).url;
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({ title: text }),
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                localTask.value = data.task;
                emit('task-updated', data.task);
            }

            newChecklistTitle.value = '';
        }
    } catch {
        toast.error('Erro ao adicionar item ao checklist.');
    }
}

async function handleToggleChecklistItem(item: TaskChecklistItem) {
    if (!props.task) {
        return;
    }

    try {
        const url = updateChecklistRoute([
            props.boardId,
            props.task.id,
            item.id,
        ]).url;
        const res = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({ is_completed: !item.is_completed }),
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                localTask.value = data.task;
                emit('task-updated', data.task);
            }
        }
    } catch {
        toast.error('Erro ao atualizar checklist.');
    }
}

async function handleDeleteChecklistItem(item: TaskChecklistItem) {
    if (!props.task) {
        return;
    }

    try {
        const url = destroyChecklistRoute([
            props.boardId,
            props.task.id,
            item.id,
        ]).url;
        const res = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                localTask.value = data.task;
                emit('task-updated', data.task);
            }
        }
    } catch {
        toast.error('Erro ao remover item do checklist.');
    }
}

async function handleFileUpload(e: Event) {
    const target = e.target as HTMLInputElement;

    if (!target.files || target.files.length === 0 || !props.task) {
        return;
    }

    const file = target.files[0];

    if (file.size > 25 * 1024 * 1024) {
        toast.error('O arquivo excede o limite máximo de 25MB.');

        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    isUploading.value = true;

    try {
        const url = storeAttachmentRoute([props.boardId, props.task.id]).url;
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: formData,
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                localTask.value = data.task;
                emit('task-updated', data.task);
                toast.success('Anexo adicionado.');
            }
        } else {
            const err = await res.json();
            toast.error(err.message || 'Erro ao enviar anexo.');
        }
    } catch {
        toast.error('Erro ao conectar com o servidor para upload.');
    } finally {
        isUploading.value = false;

        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
    }
}

async function handleDeleteAttachment(attachment: TaskAttachment) {
    if (!props.task) {
        return;
    }

    try {
        const url = destroyAttachmentRoute([
            props.boardId,
            props.task.id,
            attachment.id,
        ]).url;
        const res = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                localTask.value = data.task;
                emit('task-updated', data.task);
                toast.success('Anexo removido.');
            }
        }
    } catch {
        toast.error('Erro ao remover anexo.');
    }
}

function handleDownloadAttachment(attachment: TaskAttachment) {
    if (!props.task) {
        return;
    }

    const url = downloadAttachmentRoute([
        props.boardId,
        props.task.id,
        attachment.id,
    ]).url;
    window.open(url, '_blank');
}

async function handleDeleteTask() {
    if (!props.task) {
        return;
    }

    if (
        !confirm(
            'Tem certeza de que deseja excluir permanentemente esta tarefa?',
        )
    ) {
        return;
    }

    try {
        const url = destroyCardRoute([props.boardId, props.task.id]).url;
        const res = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        });

        if (res.ok) {
            emit('task-deleted', props.task.id);
            emit('update:open', false);
            toast.success('Tarefa excluída com sucesso.');
        }
    } catch {
        toast.error('Erro ao excluir tarefa.');
    }
}

function formatBytes(bytes: number): string {
    if (!+bytes) {
        return '0 B';
    }

    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

function getFileIcon(mime: string) {
    if (mime.startsWith('image/')) {
        return Image;
    }

    if (mime.includes('pdf')) {
        return FileText;
    }

    if (
        mime.includes('sheet') ||
        mime.includes('excel') ||
        mime.includes('csv')
    ) {
        return FileSpreadsheet;
    }

    if (mime.includes('zip') || mime.includes('tar') || mime.includes('rar')) {
        return Archive;
    }

    return File;
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            class="flex h-full w-full flex-col overflow-hidden p-0 sm:max-w-xl"
        >
            <!-- Header Section -->
            <div class="border-b px-6 py-4">
                <SheetHeader class="space-y-1">
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">Coluna:</span>
                        <span
                            class="rounded-md bg-muted px-2 py-0.5 font-semibold text-primary"
                        >
                            {{ columnName || 'Geral' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="button"
                            @click="handleToggleCompleted"
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border transition-all"
                            :class="{
                                'border-emerald-600 bg-emerald-600 text-white':
                                    isCompleted,
                                'border-muted-foreground/50 hover:border-emerald-500':
                                    !isCompleted,
                            }"
                            :title="
                                isCompleted
                                    ? 'Marcar como não concluída'
                                    : 'Marcar como concluída'
                            "
                        >
                            <Check
                                v-if="isCompleted"
                                class="h-4 w-4 stroke-[3]"
                            />
                        </button>

                        <SheetTitle class="flex-1">
                            <Input
                                v-model="title"
                                placeholder="Título da tarefa..."
                                class="h-9 border-transparent text-base font-semibold transition-colors hover:border-input focus:border-ring"
                                :class="{
                                    'text-muted-foreground line-through':
                                        isCompleted,
                                }"
                                @blur="saveTaskDetails"
                                @keydown.enter="saveTaskDetails"
                            />
                        </SheetTitle>
                    </div>
                </SheetHeader>
            </div>

            <!-- Scrollable Body -->
            <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
                <!-- Meta Row: Responsible & Due Date -->
                <div
                    class="grid grid-cols-1 gap-4 rounded-lg border bg-muted/20 p-3.5 sm:grid-cols-2"
                >
                    <!-- Responsible Admin -->
                    <div class="space-y-1.5">
                        <Label
                            class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                        >
                            <UserCheck class="h-3.5 w-3.5" />
                            Responsável
                        </Label>
                        <select
                            v-model="assignedTo"
                            @change="saveTaskDetails"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option :value="null">
                                Sem responsável atribuído
                            </option>
                            <option
                                v-for="admin in administrators"
                                :key="admin.id"
                                :value="admin.id"
                            >
                                {{ admin.name }} ({{ admin.email }})
                            </option>
                        </select>
                    </div>

                    <!-- Due Date -->
                    <div class="space-y-1.5">
                        <Label
                            class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                        >
                            <Calendar class="h-3.5 w-3.5" />
                            Prazo de Entrega
                        </Label>
                        <Input
                            type="date"
                            v-model="dueDate"
                            class="h-9 bg-background text-sm shadow-xs"
                            @change="saveTaskDetails"
                        />
                    </div>
                </div>

                <!-- Description Area -->
                <div class="space-y-2">
                    <Label
                        class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                    >
                        <FileText class="h-3.5 w-3.5" />
                        Descrição
                    </Label>
                    <textarea
                        v-model="description"
                        rows="4"
                        placeholder="Adicione mais detalhes ou instruções sobre esta tarefa..."
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-relaxed shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20 focus-visible:outline-none"
                        @blur="saveTaskDetails"
                    ></textarea>
                </div>

                <!-- Checklist Section -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label
                            class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                        >
                            <CheckSquare class="h-3.5 w-3.5" />
                            Checklist
                            <span
                                v-if="localTask?.checklists?.length"
                                class="text-xs font-normal"
                            >
                                ({{ localTask.completed_checklists_count }}/{{
                                    localTask.checklists_count
                                }})
                            </span>
                        </Label>
                    </div>

                    <!-- Progress bar -->
                    <div
                        v-if="localTask && localTask.checklists_count > 0"
                        class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full bg-emerald-500 transition-all duration-300"
                            :style="{
                                width: `${Math.round(
                                    (localTask.completed_checklists_count /
                                        localTask.checklists_count) *
                                        100,
                                )}%`,
                            }"
                        ></div>
                    </div>

                    <!-- Checklist items list -->
                    <div class="space-y-1.5">
                        <div
                            v-for="item in localTask?.checklists || []"
                            :key="item.id"
                            class="group flex items-center justify-between gap-2.5 rounded-md border border-transparent px-2.5 py-1.5 transition-colors hover:border-border hover:bg-muted/40"
                        >
                            <div
                                class="flex min-w-0 flex-1 items-center gap-2.5"
                            >
                                <button
                                    type="button"
                                    @click="handleToggleChecklistItem(item)"
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors"
                                    :class="{
                                        'border-emerald-600 bg-emerald-600 text-white':
                                            item.is_completed,
                                        'border-muted-foreground/50 hover:border-emerald-500':
                                            !item.is_completed,
                                    }"
                                >
                                    <Check
                                        v-if="item.is_completed"
                                        class="h-3 w-3 stroke-[3]"
                                    />
                                </button>
                                <span
                                    class="truncate text-sm transition-all"
                                    :class="{
                                        'text-muted-foreground line-through':
                                            item.is_completed,
                                        'text-foreground': !item.is_completed,
                                    }"
                                >
                                    {{ item.title }}
                                </span>
                            </div>

                            <button
                                type="button"
                                @click="handleDeleteChecklistItem(item)"
                                class="p-1 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive"
                                title="Excluir item"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Add checklist item input -->
                        <div class="flex items-center gap-2 pt-1">
                            <Input
                                v-model="newChecklistTitle"
                                placeholder="Adicionar um item ao checklist..."
                                class="h-8 bg-background text-xs"
                                @keydown.enter.prevent="handleAddChecklist"
                            />
                            <Button
                                size="sm"
                                class="h-8 px-3 text-xs"
                                :disabled="!newChecklistTitle.trim()"
                                @click="handleAddChecklist"
                            >
                                <Plus class="mr-1 h-3.5 w-3.5" />
                                Adicionar
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Attachments Section -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label
                            class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                        >
                            <Paperclip class="h-3.5 w-3.5" />
                            Anexos (Privados para Administradores)
                        </Label>
                    </div>

                    <!-- Upload Button / Dropzone -->
                    <div
                        @click="fileInputRef?.click()"
                        class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-border/80 bg-muted/20 px-4 py-4 text-center transition-colors hover:border-primary/60 hover:bg-muted/40"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            class="hidden"
                            @change="handleFileUpload"
                        />
                        <UploadCloud
                            class="mb-1 h-6 w-6 text-muted-foreground/80"
                        />
                        <p class="text-xs font-medium text-foreground">
                            {{
                                isUploading
                                    ? 'Enviando anexo...'
                                    : 'Clique para adicionar um anexo'
                            }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            PDF, Imagens, Documentos ou Planilhas (até 25MB)
                        </p>
                    </div>

                    <!-- Attachments List -->
                    <div
                        v-if="localTask?.attachments?.length"
                        class="space-y-2 pt-1"
                    >
                        <div
                            v-for="attachment in localTask.attachments"
                            :key="attachment.id"
                            class="flex items-center justify-between gap-3 rounded-lg border bg-card p-2.5 shadow-xs"
                        >
                            <div
                                class="flex items-center gap-2.5 overflow-hidden"
                            >
                                <component
                                    :is="getFileIcon(attachment.mime_type)"
                                    class="h-5 w-5 shrink-0 text-primary/80"
                                />
                                <div class="overflow-hidden">
                                    <p
                                        class="truncate text-xs font-medium text-foreground"
                                        :title="attachment.original_name"
                                    >
                                        {{ attachment.original_name }}
                                    </p>
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{ formatBytes(attachment.size_bytes) }}
                                        <span v-if="attachment.user">
                                            • por
                                            {{ attachment.user.name }}</span
                                        >
                                    </p>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                    title="Baixar anexo"
                                    @click="
                                        handleDownloadAttachment(attachment)
                                    "
                                >
                                    <Download class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-muted-foreground hover:text-destructive"
                                    title="Excluir anexo"
                                    @click="handleDeleteAttachment(attachment)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer: Delete Action -->
            <div
                class="flex items-center justify-between border-t bg-muted/10 px-6 py-3"
            >
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                    @click="handleDeleteTask"
                >
                    <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                    Excluir Tarefa
                </Button>

                <Button
                    size="sm"
                    class="text-xs"
                    @click="emit('update:open', false)"
                >
                    Concluído
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
