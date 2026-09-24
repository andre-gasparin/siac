<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Mail,
    Plus,
    Sliders,
    Trash2,
    XCircle,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import type { SpreadsheetEmailRuleItem } from '@/features/spreadsheet-imports/types';
import { index as spreadsheetImportsIndex } from '@/routes/spreadsheet-imports';
import {
    create as rulesCreate,
    destroy as rulesDestroy,
    edit as rulesEdit,
    index as rulesIndex,
} from '@/routes/spreadsheet-imports/rules';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/shared/components/ui/card';
import type { Team } from '@/shared/types';

const props = defineProps<{
    rules: {
        data: SpreadsheetEmailRuleItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    currentTeam: Team;
}>();

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
    ],
});

function handleDeleteRule(rule: SpreadsheetEmailRuleItem) {
    if (!confirm(`Deseja realmente excluir a regra "${rule.name}"?`)) {
        return;
    }

    router.delete(
        rulesDestroy.url({
            current_team: props.currentTeam.slug,
            rule: rule.id,
        }),
        {
            onSuccess: () => toast.success('Regra excluída com sucesso!'),
            onError: () => toast.error('Erro ao excluir regra.'),
        },
    );
}
</script>

<template>
    <Head title="Regras de Leitura de E-mail" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 border-b pb-5 sm:flex-row sm:items-center"
        >
            <div>
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" as-child class="h-8 px-2">
                        <Link
                            :href="
                                spreadsheetImportsIndex.url({
                                    current_team: currentTeam.slug,
                                })
                            "
                        >
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1
                        class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground"
                    >
                        <Mail class="h-7 w-7 text-primary" />
                        Regras de Leitura de E-mail
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Configure as regras para associar automaticamente os e-mails
                    recebidos aos Modelos de Mapeamento de
                    {{ currentTeam.name }}.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Button as-child>
                    <Link
                        :href="
                            rulesCreate.url({ current_team: currentTeam.slug })
                        "
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Nova Regra de E-mail
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Rules Card -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Regras Cadastradas</CardTitle>
                <CardDescription class="text-xs">
                    As regras são avaliadas por ordem de prioridade (maior
                    prioridade primeiro). Todas as condições preenchidas em uma
                    regra devem corresponder (lógica E).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b bg-muted/50 text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-16 px-4 py-3">Prioridade</th>
                                <th class="px-4 py-3">Nome da Regra</th>
                                <th class="px-4 py-3">Modelo Vinculado</th>
                                <th class="px-4 py-3">
                                    Condições Configuradas
                                </th>
                                <th class="px-4 py-3">Status</th>
                                <th class="w-28 px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="rule in rules.data"
                                :key="rule.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td
                                    class="px-4 py-3 font-mono font-semibold text-foreground"
                                >
                                    {{ rule.priority }}
                                </td>
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ rule.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        variant="outline"
                                        class="font-normal"
                                    >
                                        {{
                                            rule.template?.name ??
                                            'Modelo #' +
                                                rule.spreadsheet_template_id
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <div
                                        class="flex flex-col gap-1 text-[11px]"
                                    >
                                        <span v-if="rule.sender_value">
                                            <strong class="text-foreground"
                                                >Remetente:</strong
                                            >
                                            {{
                                                rule.sender_operator ===
                                                'equals'
                                                    ? 'igual a'
                                                    : 'contém'
                                            }}
                                            "{{ rule.sender_value }}"
                                        </span>
                                        <span v-if="rule.subject_value">
                                            <strong class="text-foreground"
                                                >Assunto:</strong
                                            >
                                            {{
                                                rule.subject_operator ===
                                                'equals'
                                                    ? 'igual a'
                                                    : 'contém'
                                            }}
                                            "{{ rule.subject_value }}"
                                        </span>
                                        <span v-if="rule.body_value">
                                            <strong class="text-foreground"
                                                >Corpo:</strong
                                            >
                                            contém "{{ rule.body_value }}"
                                        </span>
                                        <span v-if="rule.attachment_name_value">
                                            <strong class="text-foreground"
                                                >Nome do anexo:</strong
                                            >
                                            contém "{{
                                                rule.attachment_name_value
                                            }}"
                                        </span>
                                        <span
                                            v-if="
                                                !rule.sender_value &&
                                                !rule.subject_value &&
                                                !rule.body_value &&
                                                !rule.attachment_name_value
                                            "
                                            class="text-muted-foreground/60 italic"
                                        >
                                            Sem condições (corresponde a todos
                                            os e-mails com planilhas)
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        v-if="rule.is_active"
                                        variant="outline"
                                        class="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                                    >
                                        <CheckCircle2 class="mr-1 h-3 w-3" />
                                        Ativa
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="border-muted bg-muted text-muted-foreground"
                                    >
                                        <XCircle class="mr-1 h-3 w-3" />
                                        Inativa
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                            class="h-7 px-2 text-xs"
                                        >
                                            <Link
                                                :href="
                                                    rulesEdit.url({
                                                        current_team:
                                                            currentTeam.slug,
                                                        rule: rule.id,
                                                    })
                                                "
                                            >
                                                <Sliders class="mr-1 h-3 w-3" />
                                                Editar
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="handleDeleteRule(rule)"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="rules.data.length === 0">
                                <td
                                    colspan="6"
                                    class="py-10 text-center text-muted-foreground"
                                >
                                    Nenhuma regra de leitura cadastrada. Clique
                                    em "Nova Regra de E-mail" para criar a
                                    primeira.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
