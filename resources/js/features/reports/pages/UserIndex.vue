<script setup lang="ts">
import {
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
} from '@inertiajs/vue3';
import { Eye, FileText, MonitorCheck, Search } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import type { Team } from '@/shared/types';

export interface UserReportSummary {
    id: number;
    title: string;
    status: string;
    date_reference: string;
    finished_at: string | null;
    included_systems_count: number;
    updated_at: string | null;
}

type PaginatedData<T> = {
    data: T[];
};

type Props = {
    currentTeam: Team;
    reports: PaginatedData<UserReportSummary>;
    filters: {
        search: string;
    };
};

const props = defineProps<Props>();

const filterForm = reactive({ ...props.filters });
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const hasFilters = computed(() => filterForm.search !== '');

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

const visitWithFilters = () => {
    const query = filterForm.search ? { search: filterForm.search } : {};
    router.visit(`/${props.currentTeam.slug}/meus-relatorios`, {
        data: query,
        only: ['reports', 'filters'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        reset: ['reports'],
    });
};

const clearFilters = () => {
    filterForm.search = '';
    visitWithFilters();
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');

    if (year && month && day) {
        return `${day}/${month}/${year}`;
    }

    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(
        new Date(value),
    );
}

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Relatórios',
            href: `/${props.currentTeam.slug}/meus-relatorios`,
        },
    ],
});
</script>

<template>
    <Head title="Relatórios" />

    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 lg:p-7">
        <header
            class="flex flex-col gap-4 rounded-3xl border border-border bg-card p-4 text-card-foreground shadow-sm sm:flex-row sm:items-center sm:justify-between lg:p-6"
        >
            <div class="flex items-center gap-3">
                <div
                    class="grid size-11 place-items-center rounded-2xl bg-primary/10 text-primary"
                >
                    <FileText class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl"
                    >
                        Relatórios
                    </h1>
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        Relatórios diários e análises operacionais de
                        {{ currentTeam.name }}
                    </p>
                </div>
            </div>
        </header>

        <section
            class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-3 text-card-foreground shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-4"
        >
            <div class="relative w-full max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filterForm.search"
                    placeholder="Buscar por título ou data…"
                    class="h-9 rounded-xl pl-9 text-xs"
                />
            </div>

            <Button
                v-if="hasFilters"
                variant="ghost"
                size="sm"
                class="h-9 rounded-xl text-xs text-muted-foreground hover:text-foreground"
                @click="clearFilters"
            >
                Limpar filtros
            </Button>
        </section>

        <div
            v-if="reports.data.length === 0"
            class="flex flex-col items-center justify-center gap-2 rounded-3xl border border-dashed border-border bg-card/50 py-16 text-center"
        >
            <FileText class="size-10 text-muted-foreground" />
            <p class="text-sm font-medium text-foreground">
                Nenhum relatório encontrado
            </p>
            <p class="text-xs text-muted-foreground">
                Nenhum relatório finalizado está disponível para visualização no
                momento.
            </p>
        </div>

        <div v-else>
            <InfiniteScroll data="reports">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="report in reports.data"
                        :key="report.id"
                        class="group relative flex flex-col justify-between rounded-3xl border border-border bg-card p-5 shadow-sm transition hover:border-primary/40 hover:shadow-md"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 ring-1 ring-emerald-500/30 dark:text-emerald-400"
                                >
                                    Finalizado
                                </span>
                                <time
                                    class="text-xs text-muted-foreground"
                                    :datetime="report.date_reference"
                                >
                                    {{ formatDate(report.date_reference) }}
                                </time>
                            </div>

                            <div>
                                <h2
                                    class="text-lg font-semibold text-foreground transition group-hover:text-primary"
                                >
                                    {{ report.title }}
                                </h2>
                            </div>

                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <MonitorCheck class="size-4 text-emerald-600" />
                                <span>
                                    {{ report.included_systems_count }}
                                    sistema(s) incluído(s)
                                </span>
                            </div>
                        </div>

                        <div class="mt-5 border-t border-border/60 pt-4">
                            <Link
                                :href="`/${currentTeam.slug}/meus-relatorios/${report.id}`"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                            >
                                <Eye class="size-4" /> Visualizar Relatório
                            </Link>
                        </div>
                    </article>
                </div>
            </InfiniteScroll>
        </div>
    </div>
</template>
