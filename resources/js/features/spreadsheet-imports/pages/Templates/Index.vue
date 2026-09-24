<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    FileSpreadsheet,
    Plus,
    TableProperties,
    Trash2,
    UploadCloud,
} from '@lucide/vue';
import type { SpreadsheetTemplateItem } from '@/features/spreadsheet-imports/types';
import { index as spreadsheetImportsIndex } from '@/routes/spreadsheet-imports';
import {
    create as templatesCreate,
    destroy as templatesDestroy,
    edit as templatesEdit,
    index as templatesIndex,
} from '@/routes/spreadsheet-imports/templates';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/shared/components/ui/card';
import type { Team } from '@/shared/types';

const props = defineProps<{
    templates: SpreadsheetTemplateItem[];
    currentTeam: Team;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Importação de Planilhas',
            href: props.currentTeam
                ? spreadsheetImportsIndex.url({
                      current_team: props.currentTeam.slug,
                  })
                : '/',
        },
        {
            title: 'Modelos de Mapeamento',
            href: props.currentTeam
                ? templatesIndex.url({ current_team: props.currentTeam.slug })
                : '/',
        },
    ],
});

function getTotalMappings(template: SpreadsheetTemplateItem): number {
    if (!template.config?.sheets) {
        return 0;
    }

    return template.config.sheets.reduce(
        (sum, s) => sum + (s.mappings?.length ?? 0),
        0,
    );
}

function handleDelete(template: SpreadsheetTemplateItem) {
    if (
        confirm(
            `Tem certeza que deseja remover o modelo "${template.name}"? Esta ação não afetará os dados já importados anteriormente.`,
        )
    ) {
        router.delete(
            templatesDestroy.url({
                current_team: props.currentTeam.slug,
                template: template.id,
            }),
        );
    }
}
</script>

<template>
    <Head title="Modelos de Mapeamento de Planilhas" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 border-b pb-5 sm:flex-row sm:items-center"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    <TableProperties class="h-7 w-7 text-primary" />
                    Modelos de Mapeamento de Planilhas
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Crie e gerencie os modelos com regras de células Excel,
                    datas dinâmicas, abas e multiplicadores para
                    {{ currentTeam.name }}.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            spreadsheetImportsIndex.url({
                                current_team: currentTeam.slug,
                            })
                        "
                    >
                        <UploadCloud class="mr-2 h-4 w-4" />
                        Ir para Upload
                    </Link>
                </Button>
                <Button as-child>
                    <Link
                        :href="
                            templatesCreate.url({
                                current_team: currentTeam.slug,
                            })
                        "
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Novo Modelo
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Templates Grid -->
        <div
            v-if="templates.length > 0"
            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
        >
            <Card
                v-for="template in templates"
                :key="template.id"
                class="flex flex-col justify-between transition-shadow hover:shadow-md"
            >
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-1">
                            <CardTitle
                                class="text-base leading-tight font-semibold text-foreground"
                            >
                                {{ template.name }}
                            </CardTitle>
                            <CardDescription class="line-clamp-2 text-xs">
                                {{
                                    template.description ||
                                    'Sem descrição informada.'
                                }}
                            </CardDescription>
                        </div>
                        <Badge
                            :variant="
                                template.is_active ? 'default' : 'secondary'
                            "
                        >
                            {{ template.is_active ? 'Ativo' : 'Inativo' }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent
                    class="space-y-2 py-2 text-xs text-muted-foreground"
                >
                    <div
                        class="grid grid-cols-2 gap-2 rounded-md bg-muted/40 p-2.5 font-mono"
                    >
                        <div>
                            <div
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Abas
                            </div>
                            <div class="text-sm font-bold text-foreground">
                                {{ template.config?.sheets?.length ?? 1 }}
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Células Mapeadas
                            </div>
                            <div
                                class="text-sm font-bold text-emerald-700 dark:text-emerald-400"
                            >
                                {{ getTotalMappings(template) }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between pt-1 text-[11px]"
                    >
                        <span
                            >Lotes importados:
                            <strong>{{
                                template.batches_count ?? 0
                            }}</strong></span
                        >
                        <span v-if="template.updater || template.creator">
                            Por:
                            {{
                                template.updater?.name || template.creator?.name
                            }}
                        </span>
                    </div>
                </CardContent>

                <CardFooter
                    class="flex items-center justify-between border-t bg-muted/20 px-6 py-3"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="handleDelete(template)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="
                                    templatesEdit.url({
                                        current_team: currentTeam.slug,
                                        template: template.id,
                                    })
                                "
                            >
                                Editar Modelo
                            </Link>
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>

        <!-- Empty state -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center"
        >
            <div class="mb-4 rounded-full bg-primary/10 p-4 text-primary">
                <FileSpreadsheet class="h-10 w-10" />
            </div>
            <h3 class="text-lg font-semibold text-foreground">
                Nenhum modelo de planilha cadastrado
            </h3>
            <p class="mt-1 max-w-md text-sm text-muted-foreground">
                Configure seu primeiro modelo estilo Excel para mapear
                automaticamente parâmetros, datas e multiplicadores das
                planilhas de rotina da unidade.
            </p>
            <Button class="mt-6" as-child>
                <Link
                    :href="
                        templatesCreate.url({ current_team: currentTeam.slug })
                    "
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Criar Primeiro Modelo
                </Link>
            </Button>
        </div>
    </div>
</template>
