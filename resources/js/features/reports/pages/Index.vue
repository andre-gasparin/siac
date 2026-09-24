<script setup lang="ts">
import {
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
} from '@inertiajs/vue3';
import {
    CalendarDays,
    Ellipsis,
    Eye,
    FileText,
    Filter,
    Mail,
    MonitorCheck,
    Plus,
    Search,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import { index, show } from '@/routes/reports';
import { Button } from '@/shared/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu';
import { Input } from '@/shared/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/shared/components/ui/select';
import type { Team } from '@/shared/types';

export interface ReportSummary {
    id: number;
    title: string;
    status: 'draft' | 'completed' | string;
    date_reference: string;
    finished_at: string | null;
    emailed_at: string | null;
    email_count: number;
    included_systems_count: number;
    recipients_count: number;
    updated_at: string | null;
}

type PaginatedData<T> = {
    data: T[];
};

type ReportFilters = {
    status: 'all' | 'draft' | 'completed';
    search: string;
};

type Props = {
    currentTeam: Team;
    reports: PaginatedData<ReportSummary>;
    filters: ReportFilters;
    statusCounts: {
        draft: number;
        completed: number;
    };
};

const props = defineProps<Props>();

const filterForm = reactive<ReportFilters>({ ...props.filters });
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const hasFilters = computed(
    () => filterForm.status !== 'all' || filterForm.search !== '',
);

watch(
    () => props.filters,
    (filters) => {
        Object.assign(filterForm, filters);
    },
);

watch(
    () => filterForm.search,
    () => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(() => visitWithFilters(), 300);
    },
);

const normalizedFilters = () => ({
    ...(filterForm.status !== 'all' ? { status: filterForm.status } : {}),
    ...(filterForm.search ? { search: filterForm.search } : {}),
});

const visitWithFilters = () => {
    router.visit(
        index.url(
            { current_team: props.currentTeam.slug },
            { query: normalizedFilters() },
        ),
        {
            only: ['reports', 'filters', 'statusCounts'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
            reset: ['reports'],
        },
    );
};

const selectStatus = (status: ReportFilters['status']) => {
    filterForm.status = status;
    visitWithFilters();
};

const selectStatusValue = (status: unknown) => {
    selectStatus(String(status) as ReportFilters['status']);
};

const clearFilters = () => {
    filterForm.status = 'all';
    filterForm.search = '';
    visitWithFilters();
};

function formatDate(value: string | null, includeTime = false): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'pt-BR',
        includeTime ? { dateStyle: 'short', timeStyle: 'short' } : undefined,
    ).format(new Date(includeTime ? value : `${value}T12:00:00`));
}

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Unidades',
            href: '/unidades',
        },
        {
            title: props.currentTeam.name,
            href: `/${props.currentTeam.slug}/tabela-dados`,
        },
        {
            title: 'Relatórios',
            href: index(props.currentTeam.slug),
        },
    ],
});
</script>

<template>
    <div class="contents">
        <Head title="Relatórios" />

        <main class="flex flex-1 flex-col bg-background text-foreground">
            <section class="flex flex-col gap-4 px-4 py-3 md:px-6">
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Select
                            :model-value="filterForm.status"
                            @update:model-value="selectStatusValue"
                        >
                            <SelectTrigger
                                class="h-8 w-auto gap-2 rounded-md border-border bg-background px-3 text-xs shadow-xs dark:bg-background"
                            >
                                <span class="text-muted-foreground"
                                    >Status</span
                                >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="draft">Rascunhos</SelectItem>
                                <SelectItem value="completed"
                                    >Finalizados</SelectItem
                                >
                            </SelectContent>
                        </Select>

                        <label
                            class="flex h-8 w-full items-center gap-2 rounded-md border border-border bg-background px-3 text-xs shadow-xs sm:w-64 dark:bg-background"
                        >
                            <Search class="size-3.5 text-muted-foreground" />
                            <span class="text-muted-foreground">Busca</span>
                            <Input
                                v-model="filterForm.search"
                                class="h-6 border-0 px-0 py-0 text-xs shadow-none focus-visible:ring-0"
                                placeholder="Título ou data..."
                                type="search"
                            />
                        </label>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 rounded-md border-border px-3 text-xs shadow-xs"
                            :disabled="!hasFilters"
                            @click="clearFilters"
                        >
                            <Filter class="size-3.5" />
                            Limpar filtros
                        </Button>
                    </div>

                    <Link :href="`/${currentTeam.slug}/tabela-dados`">
                        <Button
                            class="h-8 w-full rounded-md px-3 text-xs shadow-xs lg:w-auto"
                        >
                            <Plus class="size-3.5" />
                            Novo relatório
                        </Button>
                    </Link>
                </div>

                <InfiniteScroll
                    data="reports"
                    :buffer="400"
                    items-element="#reports-list"
                >
                    <section
                        class="overflow-hidden rounded-lg border border-border bg-background shadow-xs"
                    >
                        <div
                            class="hidden grid-cols-[minmax(240px,1.2fr)_140px_180px_120px_140px_36px] gap-4 border-b px-10 py-3 text-xs font-medium text-muted-foreground md:grid"
                        >
                            <span>Título</span>
                            <span>Data de ref.</span>
                            <span>Sistemas / Envios</span>
                            <span>Status</span>
                            <span>Atualizado</span>
                            <span class="sr-only">Ações</span>
                        </div>

                        <div id="reports-list" class="divide-y">
                            <article
                                v-for="report in reports.data"
                                :key="report.id"
                                class="grid min-h-10 gap-3 px-4 py-2 text-sm transition-colors hover:bg-muted/40 md:grid-cols-[minmax(240px,1.2fr)_140px_180px_120px_140px_36px] md:items-center md:gap-4 md:px-10"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="min-w-0">
                                        <Link
                                            :href="
                                                show.url({
                                                    current_team:
                                                        currentTeam.slug,
                                                    report: report.id,
                                                })
                                            "
                                            class="truncate font-medium text-foreground hover:text-primary hover:underline"
                                        >
                                            {{ report.title }}
                                        </Link>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <CalendarDays class="size-3.5 shrink-0" />
                                    <span>{{
                                        formatDate(report.date_reference)
                                    }}</span>
                                </div>

                                <div
                                    class="flex flex-col gap-0.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <MonitorCheck class="size-3.5" />
                                        {{ report.included_systems_count }}
                                        sistema(s)
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5 text-[11px]"
                                    >
                                        <Mail class="size-3" />
                                        {{ report.recipients_count }} dest. ·
                                        {{ report.email_count }} envio(s)
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="
                                            report.status === 'completed'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300'
                                        "
                                    >
                                        {{
                                            report.status === 'completed'
                                                ? 'Finalizado'
                                                : 'Rascunho'
                                        }}
                                    </span>
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(report.updated_at, true) }}
                                </div>

                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="size-7 justify-self-start text-muted-foreground md:justify-self-end"
                                            aria-label="Ações do relatório"
                                        >
                                            <Ellipsis class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-44"
                                    >
                                        <DropdownMenuItem as-child>
                                            <Link
                                                :href="
                                                    show.url({
                                                        current_team:
                                                            currentTeam.slug,
                                                        report: report.id,
                                                    })
                                                "
                                            >
                                                <Eye class="size-4" />
                                                Visualizar
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </article>
                        </div>

                        <div
                            v-if="reports.data.length === 0"
                            class="flex flex-col items-center gap-3 px-6 py-12 text-center"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-md bg-muted"
                            >
                                <FileText
                                    class="size-4 text-muted-foreground"
                                />
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    Nenhum relatório encontrado
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Ajuste os filtros ou crie um novo relatório
                                    na tabela de dados.
                                </p>
                            </div>
                        </div>

                        <div class="border-t px-7 py-3">
                            <Link
                                :href="`/${currentTeam.slug}/tabela-dados`"
                                class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                            >
                                <Plus class="size-4" />
                                Criar relatório
                            </Link>
                        </div>
                    </section>
                </InfiniteScroll>
            </section>
        </main>
    </div>
</template>
