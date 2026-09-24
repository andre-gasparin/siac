<script setup lang="ts">
import { Check, Copy, Download, RefreshCw, Upload } from '@lucide/vue';
import { ref, watch } from 'vue';
import type { TemplateConfig } from '@/features/spreadsheet-imports/types';
import { Button } from '@/shared/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/shared/components/ui/sheet';

const props = defineProps<{
    open: boolean;
    config: TemplateConfig;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'apply', config: TemplateConfig): void;
}>();

const rawJson = ref<string>('');
const jsonError = ref<string | null>(null);
const copied = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            rawJson.value = JSON.stringify(props.config, null, 2);
            jsonError.value = null;
        }
    },
    { immediate: true },
);

function validateAndFormat() {
    try {
        const parsed = JSON.parse(rawJson.value);

        if (!parsed.sheets || !Array.isArray(parsed.sheets)) {
            throw new Error(
                "O JSON precisa conter uma chave 'sheets' do tipo array.",
            );
        }

        rawJson.value = JSON.stringify(parsed, null, 2);
        jsonError.value = null;

        return parsed;
    } catch (e: any) {
        jsonError.value = e.message || 'JSON inválido';

        return null;
    }
}

function handleApply() {
    const parsed = validateAndFormat();

    if (parsed) {
        emit('apply', parsed);
        emit('update:open', false);
    }
}

async function copyToClipboard() {
    await navigator.clipboard.writeText(rawJson.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function handleDownloadFile() {
    const jsonStr = rawJson.value || JSON.stringify(props.config, null, 2);
    const blob = new Blob([jsonStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'config_upload_modelo.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function triggerFileUpload() {
    fileInputRef.value?.click();
}

function handleFileUpload(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const content = e.target?.result as string;
            const parsed = JSON.parse(content);

            if (!parsed.sheets || !Array.isArray(parsed.sheets)) {
                throw new Error(
                    "O arquivo JSON precisa conter a chave 'sheets' do tipo array.",
                );
            }

            rawJson.value = JSON.stringify(parsed, null, 2);
            jsonError.value = null;
        } catch (err: any) {
            jsonError.value =
                'Erro ao ler arquivo JSON: ' +
                (err.message || 'Arquivo inválido');
        } finally {
            target.value = '';
        }
    };
    reader.readAsText(file);
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            side="right"
            class="flex w-full flex-col p-6 sm:max-w-2xl"
        >
            <SheetHeader>
                <SheetTitle class="flex items-center justify-between">
                    <span>Editor de Configuração JSON</span>
                    <div class="flex items-center gap-1.5">
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".json,application/json"
                            class="hidden"
                            @change="handleFileUpload"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            title="Carregar arquivo .json do computador"
                            @click="triggerFileUpload"
                        >
                            <Upload class="mr-1 h-3.5 w-3.5" />
                            Carregar
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            title="Baixar arquivo de configuração como .json"
                            @click="handleDownloadFile"
                        >
                            <Download class="mr-1 h-3.5 w-3.5" />
                            Baixar
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="copyToClipboard"
                        >
                            <Check
                                v-if="copied"
                                class="mr-1 h-3.5 w-3.5 text-green-500"
                            />
                            <Copy v-else class="mr-1 h-3.5 w-3.5" />
                            {{ copied ? 'Copiado!' : 'Copiar' }}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="validateAndFormat"
                        >
                            <RefreshCw class="mr-1 h-3.5 w-3.5" />
                            Formatar
                        </Button>
                    </div>
                </SheetTitle>
                <SheetDescription>
                    Edição direta do schema JSON de mapeamento. As alterações
                    sincronizam bidirecionalmente com a grade Excel.
                </SheetDescription>
            </SheetHeader>

            <div class="flex min-h-0 flex-1 flex-col py-4">
                <textarea
                    v-model="rawJson"
                    class="w-full flex-1 resize-none overflow-auto rounded-md border border-input bg-zinc-950 p-4 font-mono text-xs text-zinc-100 shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    spellcheck="false"
                    @input="jsonError = null"
                />

                <div
                    v-if="jsonError"
                    class="mt-2 rounded-md border border-destructive/20 bg-destructive/10 p-2 font-mono text-xs text-destructive"
                >
                    ⚠️ {{ jsonError }}
                </div>
            </div>

            <SheetFooter class="flex gap-2 sm:justify-between">
                <Button
                    variant="outline"
                    type="button"
                    @click="emit('update:open', false)"
                >
                    Fechar
                </Button>
                <Button type="button" @click="handleApply">
                    Aplicar na Grade
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
