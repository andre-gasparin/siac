<script setup lang="ts">
import { Head, Link, useForm, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Mail, Save } from '@lucide/vue';
import type {
    SpreadsheetEmailRuleItem,
    SpreadsheetTemplateItem,
} from '@/features/spreadsheet-imports/types';
import { index as spreadsheetImportsIndex } from '@/routes/spreadsheet-imports';
import {
    index as rulesIndex,
    store as rulesStore,
    update as rulesUpdate,
} from '@/routes/spreadsheet-imports/rules';
import { Button } from '@/shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/shared/components/ui/card';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import type { Team } from '@/shared/types';

const props = defineProps<{
    currentTeam: Team;
    templates: SpreadsheetTemplateItem[];
    rule: SpreadsheetEmailRuleItem | null;
}>();

const isEditing = !!props.rule;

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Importação de Planilhas',
            href: spreadsheetImportsIndex.url({
                current_team: props.currentTeam.slug,
            }),
        },
        {
            title: 'Regras de E-mail',
            href: rulesIndex.url({ current_team: props.currentTeam.slug }),
        },
        {
            title: isEditing ? `Editar: ${props.rule?.name}` : 'Nova Regra',
            href: '#',
        },
    ],
});

const form = useForm({
    name: props.rule?.name ?? '',
    spreadsheet_template_id:
        props.rule?.spreadsheet_template_id ?? props.templates[0]?.id ?? 0,
    priority: props.rule?.priority ?? 0,
    is_active: props.rule?.is_active ?? true,
    sender_operator: props.rule?.sender_operator ?? 'contains',
    sender_value: props.rule?.sender_value ?? '',
    subject_operator: props.rule?.subject_operator ?? 'contains',
    subject_value: props.rule?.subject_value ?? '',
    body_operator: props.rule?.body_operator ?? 'contains',
    body_value: props.rule?.body_value ?? '',
    attachment_name_operator:
        props.rule?.attachment_name_operator ?? 'contains',
    attachment_name_value: props.rule?.attachment_name_value ?? '',
    date_extraction_source: props.rule?.date_extraction_source ?? 'auto',
    date_extraction_pattern: props.rule?.date_extraction_pattern ?? '',
});

function handleSubmit() {
    if (isEditing && props.rule) {
        form.put(
            rulesUpdate.url({
                current_team: props.currentTeam.slug,
                rule: props.rule.id,
            }),
        );
    } else {
        form.post(
            rulesStore.url({
                current_team: props.currentTeam.slug,
            }),
        );
    }
}
</script>

<template>
    <Head
        :title="isEditing ? 'Editar Regra de E-mail' : 'Nova Regra de E-mail'"
    />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 border-b pb-5 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="sm" as-child class="h-8 px-2">
                    <Link
                        :href="
                            rulesIndex.url({ current_team: currentTeam.slug })
                        "
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground"
                    >
                        <Mail class="h-7 w-7 text-primary" />
                        {{
                            isEditing
                                ? 'Editar Regra de E-mail'
                                : 'Nova Regra de Leitura de E-mail'
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Defina as condições para associar mensagens recebidas a
                        um Modelo de Mapeamento em {{ currentTeam.name }}.
                    </p>
                </div>
            </div>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
            <!-- Basic Info Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base"
                        >1. Dados da Regra e Destino</CardTitle
                    >
                    <CardDescription class="text-xs">
                        Defina o nome da regra e o modelo de importação que
                        receberá os dados.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-xs">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <!-- Rule Name -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="rule-name">Nome da Regra *</Label>
                            <Input
                                id="rule-name"
                                v-model="form.name"
                                placeholder="Ex: Relatório Diário de Água - Fornecedor X"
                                required
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Active Toggle (Slider beside Name) -->
                        <div
                            class="flex flex-col justify-between space-y-1.5 rounded-lg border bg-muted/20 p-3"
                        >
                            <div class="flex items-center justify-between">
                                <Label
                                    for="rule-active-toggle"
                                    class="cursor-pointer text-xs font-semibold"
                                >
                                    Regra Ativa
                                </Label>
                                <button
                                    id="rule-active-toggle"
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.is_active"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                    :class="
                                        form.is_active
                                            ? 'bg-primary'
                                            : 'bg-muted-foreground/30'
                                    "
                                    @click="form.is_active = !form.is_active"
                                >
                                    <span
                                        aria-hidden="true"
                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out"
                                        :class="
                                            form.is_active
                                                ? 'translate-x-4'
                                                : 'translate-x-0'
                                        "
                                    />
                                </button>
                            </div>
                            <p
                                class="text-[11px]"
                                :class="
                                    form.is_active
                                        ? 'font-medium text-emerald-600 dark:text-emerald-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    form.is_active
                                        ? '● Ativa (processando)'
                                        : '○ Pausada / Inativa'
                                }}
                            </p>
                        </div>

                        <!-- Target Template -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="rule-template"
                                >Modelo de Mapeamento *</Label
                            >
                            <select
                                id="rule-template"
                                v-model="form.spreadsheet_template_id"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                                required
                            >
                                <option
                                    v-for="t in templates"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.name }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.spreadsheet_template_id"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.spreadsheet_template_id }}
                            </p>
                        </div>

                        <!-- Priority -->
                        <div class="space-y-1.5">
                            <Label for="rule-priority">Prioridade</Label>
                            <Input
                                id="rule-priority"
                                v-model.number="form.priority"
                                type="number"
                                min="0"
                                max="1000"
                                placeholder="0"
                            />
                            <p class="text-[10px] text-muted-foreground">
                                Maior valor tem preferência.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Matching Conditions Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base"
                        >2. Condições de Correspondência (Lógica E /
                        AND)</CardTitle
                    >
                    <CardDescription class="text-xs">
                        O e-mail deve satisfazer todas as condições preenchidas
                        abaixo para ser vinculado a esta regra. Deixe em branco
                        as condições que não deseja validar.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-xs">
                    <!-- Sender Condition -->
                    <div class="space-y-1.5">
                        <Label>Remetente do E-mail</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <select
                                v-model="form.sender_operator"
                                class="h-9 rounded-md border border-input bg-background px-2 text-xs shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option value="contains">Contém</option>
                                <option value="equals">Igual a</option>
                            </select>
                            <Input
                                v-model="form.sender_value"
                                class="col-span-2 text-xs"
                                placeholder="Ex: relatorios@fornecedor.com ou @dominio.com.br"
                            />
                        </div>
                    </div>

                    <!-- Subject Condition -->
                    <div class="space-y-1.5 border-t pt-3">
                        <Label>Assunto do E-mail</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <select
                                v-model="form.subject_operator"
                                class="h-9 rounded-md border border-input bg-background px-2 text-xs shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option value="contains">Contém</option>
                                <option value="equals">Igual a</option>
                            </select>
                            <Input
                                v-model="form.subject_value"
                                class="col-span-2 text-xs"
                                placeholder="Ex: Relatório de Parâmetros"
                            />
                        </div>
                    </div>

                    <!-- Body Condition -->
                    <div class="space-y-1.5 border-t pt-3">
                        <Label>Corpo do E-mail</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <select
                                v-model="form.body_operator"
                                class="h-9 rounded-md border border-input bg-background px-2 text-xs shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option value="contains">Contém</option>
                            </select>
                            <Input
                                v-model="form.body_value"
                                class="col-span-2 text-xs"
                                placeholder="Ex: medições atualizadas"
                            />
                        </div>
                    </div>

                    <!-- Attachment Name Condition -->
                    <div class="space-y-1.5 border-t pt-3">
                        <Label>Nome do Arquivo Anexo</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <select
                                v-model="form.attachment_name_operator"
                                class="h-9 rounded-md border border-input bg-background px-2 text-xs shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option value="contains">Contém</option>
                            </select>
                            <Input
                                v-model="form.attachment_name_value"
                                class="col-span-2 text-xs"
                                placeholder="Ex: medicao_diaria"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Date Extraction Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base"
                        >3. Extração Automática de Data de Referência</CardTitle
                    >
                    <CardDescription class="text-xs">
                        Configure como o sistema deve tentar detectar a data dos
                        dados da planilha.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-xs">
                    <div class="space-y-1.5">
                        <Label>Estratégia de Busca</Label>
                        <select
                            v-model="form.date_extraction_source"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="auto">
                                Automática (Nome do Arquivo &rarr; Assunto
                                &rarr; Corpo &rarr; Planilha)
                            </option>
                            <option value="filename">
                                Apenas Nome do Arquivo
                            </option>
                            <option value="subject">
                                Apenas Assunto do E-mail
                            </option>
                            <option value="body">Apenas Corpo do E-mail</option>
                            <option value="spreadsheet">
                                Apenas Conteúdo Interno da Planilha
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="custom-pattern"
                            >Padrão Regex Customizado (Opcional)</Label
                        >
                        <Input
                            id="custom-pattern"
                            v-model="form.date_extraction_pattern"
                            placeholder="Ex: /data[_-](\d{4}[-_]\d{2}[-_]\d{2})/i"
                            class="font-mono text-xs"
                        />
                        <p class="text-[10px] text-muted-foreground">
                            Deixe em branco para usar os padrões automáticos do
                            sistema (formatos YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY
                            e nomes de meses em português).
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions Bar -->
            <div class="flex items-center justify-between border-t pt-4">
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            rulesIndex.url({ current_team: currentTeam.slug })
                        "
                    >
                        Cancelar
                    </Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{
                        isEditing
                            ? 'Salvar Alterações'
                            : 'Criar Regra de E-mail'
                    }}
                </Button>
            </div>
        </form>
    </div>
</template>
