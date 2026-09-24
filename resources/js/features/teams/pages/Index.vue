<script setup lang="ts">
import {
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
} from '@inertiajs/vue3';
import {
    Building2,
    Ellipsis,
    Filter,
    Pencil,
    Plus,
    Search,
    Sliders,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import CreateTeamModal from '@/features/teams/components/CreateTeamModal.vue';
import DeleteTeamModal from '@/features/teams/components/DeleteTeamModal.vue';
import { edit, index, update } from '@/routes/teams';
import { edit as editSystems } from '@/routes/teams/systems';
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
import type { TeamRole } from '@/shared/types';

type UnitMember = {
    id: number;
    name: string;
    email: string;
};

type UnitParameterValueActivity = {
    date: string;
    label: string;
    count: number;
};

type UnitTeam = {
    id: number;
    name: string;
    slug: string;
    isActive: boolean;
    isPersonal: boolean;
    role: TeamRole;
    roleLabel: string;
    isCurrent: boolean;
    membersCount: number;
    members: UnitMember[];
    parameterValueActivity: UnitParameterValueActivity[];
    canUpdate: boolean;
    canDelete: boolean;
};

type PaginatedData<T> = {
    data: T[];
};

type UnitFilters = {
    status: 'all' | 'active' | 'inactive';
    search: string;
    role: 'all' | TeamRole;
};

type Props = {
    teams: PaginatedData<UnitTeam>;
    filters: UnitFilters;
    statusCounts: {
        active: number;
        inactive: number;
    };
};

const props = defineProps<Props>();

const filterForm = reactive<UnitFilters>({ ...props.filters });
const deleteDialogOpen = ref(false);
const teamDeleting = ref<UnitTeam | null>(null);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const toggleTeamActive = (team: UnitTeam) => {
    router.patch(
        update(team.slug),
        {
            name: team.name,
            is_active: !team.isActive,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const hasFilters = computed(
    () =>
        filterForm.status !== 'active' ||
        filterForm.search !== '' ||
        filterForm.role !== 'all',
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
    ...(filterForm.status !== 'active' ? { status: filterForm.status } : {}),
    ...(filterForm.search ? { search: filterForm.search } : {}),
    ...(filterForm.role !== 'all' ? { role: filterForm.role } : {}),
});

const visitWithFilters = () => {
    router.visit(index({ query: normalizedFilters() }), {
        only: ['teams', 'filters', 'statusCounts'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        reset: ['teams'],
    });
};

const selectStatus = (status: UnitFilters['status']) => {
    filterForm.status = status;
    visitWithFilters();
};

const selectStatusValue = (status: unknown) => {
    selectStatus(String(status) as UnitFilters['status']);
};

const selectRole = (role: unknown) => {
    filterForm.role = String(role) as UnitFilters['role'];
    visitWithFilters();
};

const clearFilters = () => {
    filterForm.status = 'active';
    filterForm.search = '';
    filterForm.role = 'all';
    visitWithFilters();
};

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

const visibleMembers = (team: UnitTeam) => team.members.slice(0, 5);

const maxActivityCount = (team: UnitTeam) =>
    Math.max(...team.parameterValueActivity.map((entry) => entry.count), 0);

const activityBarHeight = (team: UnitTeam, count: number) => {
    const maxCount = maxActivityCount(team);

    if (count === 0 || maxCount === 0) {
        return '2px';
    }

    return `${Math.max(2, Math.round((count / maxCount) * 11))}px`;
};

const activityTitle = (entry: UnitParameterValueActivity) =>
    `Quantidade de dados do dia ${entry.label}: ${entry.count}`;

const openDeleteDialog = (team: UnitTeam) => {
    teamDeleting.value = team;
    deleteDialogOpen.value = true;
};

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Unidades',
            href: index(),
        },
    ],
});
</script>

<template>
    <div class="contents">
        <Head title="Unidades" />

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
                                <SelectItem value="all">Todas</SelectItem>
                                <SelectItem value="active">Ativas</SelectItem>
                                <SelectItem value="inactive"
                                    >Inativas</SelectItem
                                >
                            </SelectContent>
                        </Select>

                        <Select
                            :model-value="filterForm.role"
                            @update:model-value="selectRole"
                        >
                            <SelectTrigger
                                class="h-8 w-auto gap-2 rounded-md border-border bg-background px-3 text-xs shadow-xs dark:bg-background"
                            >
                                <span class="text-muted-foreground"
                                    >Funcao</span
                                >
                                <SelectValue placeholder="Todas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todas</SelectItem>
                                <SelectItem value="owner"
                                    >Proprietario</SelectItem
                                >
                                <SelectItem value="admin"
                                    >Administrador</SelectItem
                                >
                                <SelectItem value="member">Membro</SelectItem>
                            </SelectContent>
                        </Select>

                        <div
                            class="relative flex h-8 w-full items-center sm:w-64"
                        >
                            <Search
                                class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="filterForm.search"
                                class="h-8 w-full rounded-md border border-border bg-background pr-3 pl-8 text-xs shadow-xs focus-visible:ring-1 dark:bg-background"
                                placeholder="Busca"
                                type="search"
                            />
                        </div>

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

                    <CreateTeamModal>
                        <Button
                            data-test="teams-new-team-button"
                            class="h-8 w-full rounded-md px-3 text-xs shadow-xs lg:w-auto"
                        >
                            <Plus class="size-3.5" />
                            Nova unidade
                        </Button>
                    </CreateTeamModal>
                </div>

                <InfiniteScroll
                    data="teams"
                    :buffer="400"
                    items-element="#units-list"
                >
                    <section
                        class="overflow-hidden rounded-lg border border-border bg-background shadow-xs"
                    >
                        <div
                            class="hidden grid-cols-[minmax(240px,1.2fr)_1fr_150px_120px_36px] gap-4 border-b px-10 py-3 text-xs font-medium text-muted-foreground md:grid"
                        >
                            <span>Nome</span>
                            <span>Membros</span>
                            <span>Funcao</span>
                            <span>Status</span>
                            <span class="sr-only">Acoes</span>
                        </div>

                        <div id="units-list" class="divide-y">
                            <article
                                v-for="team in teams.data"
                                :key="team.id"
                                data-test="team-row"
                                class="grid min-h-10 gap-3 px-4 py-2 text-sm transition-colors hover:bg-muted/40 md:grid-cols-[minmax(240px,1.2fr)_1fr_150px_120px_36px] md:items-center md:gap-4 md:px-10"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="min-w-0">
                                        <div
                                            class="flex min-w-0 flex-wrap items-center gap-1.5"
                                        >
                                            <h2
                                                class="truncate text-sm leading-5 font-medium"
                                            >
                                                {{ team.name }}
                                            </h2>
                                            <div
                                                class="flex h-6 w-16 shrink-0 -translate-y-1.5 items-end gap-0.5"
                                                aria-label="Quantidade de dados dos ultimos 7 dias"
                                            >
                                                <span
                                                    v-for="entry in team.parameterValueActivity"
                                                    :key="entry.date"
                                                    class="w-1.5 rounded-sm bg-sky-500/40 transition-colors hover:bg-sky-600 dark:bg-sky-400/40 dark:hover:bg-sky-300"
                                                    :class="{
                                                        'bg-muted hover:bg-muted dark:bg-muted dark:hover:bg-muted':
                                                            entry.count === 0,
                                                    }"
                                                    :style="{
                                                        height: activityBarHeight(
                                                            team,
                                                            entry.count,
                                                        ),
                                                    }"
                                                    :title="
                                                        activityTitle(entry)
                                                    "
                                                    :aria-label="
                                                        activityTitle(entry)
                                                    "
                                                />
                                            </div>
                                            <span
                                                v-if="team.isCurrent"
                                                class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                            >
                                                Atual
                                            </span>
                                            <span
                                                v-if="team.isPersonal"
                                                class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                            >
                                                Pessoal
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <div class="flex -space-x-1.5">
                                        <div
                                            v-for="member in visibleMembers(
                                                team,
                                            )"
                                            :key="member.id"
                                            class="flex size-6 items-center justify-center rounded-full border-2 border-background bg-primary/15 text-[10px] font-semibold text-primary dark:border-background"
                                            :title="member.name"
                                        >
                                            {{ initials(member.name) }}
                                        </div>
                                    </div>
                                    <span
                                        v-if="
                                            team.membersCount >
                                            visibleMembers(team).length
                                        "
                                    >
                                        +{{
                                            team.membersCount -
                                            visibleMembers(team).length
                                        }}
                                    </span>
                                    <span
                                        v-if="team.membersCount === 0"
                                        class="inline-flex items-center gap-1"
                                    >
                                        <Users class="size-3.5" />
                                        Sem membros
                                    </span>
                                </div>

                                <span class="text-xs text-muted-foreground">
                                    {{ team.roleLabel }}
                                </span>

                                <div class="flex items-center gap-2">
                                    <button
                                        :id="`team-active-${team.id}`"
                                        type="button"
                                        role="switch"
                                        :aria-checked="team.isActive"
                                        data-test="team-active-slider"
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                        :class="
                                            team.isActive
                                                ? 'bg-primary'
                                                : 'bg-muted'
                                        "
                                        @click="toggleTeamActive(team)"
                                    >
                                        <span
                                            aria-hidden="true"
                                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out"
                                            :class="
                                                team.isActive
                                                    ? 'translate-x-4'
                                                    : 'translate-x-0'
                                            "
                                        />
                                    </button>
                                    <span
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{
                                            team.isActive ? 'Ativa' : 'Inativa'
                                        }}
                                    </span>
                                </div>

                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="size-7 justify-self-start text-muted-foreground md:justify-self-end"
                                            aria-label="Acoes da unidade"
                                        >
                                            <Ellipsis class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-48"
                                    >
                                        <DropdownMenuItem as-child>
                                            <Link :href="edit(team.slug)">
                                                <Pencil class="size-4" />
                                                Editar
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link
                                                :href="editSystems(team.slug)"
                                            >
                                                <Sliders class="size-4" />
                                                Sistemas
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            variant="destructive"
                                            :disabled="!team.canDelete"
                                            @select="openDeleteDialog(team)"
                                        >
                                            <Trash2 class="size-4" />
                                            Excluir
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </article>
                        </div>

                        <div
                            v-if="teams.data.length === 0"
                            class="flex flex-col items-center gap-3 px-6 py-12 text-center"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-md bg-muted"
                            >
                                <Building2
                                    class="size-4 text-muted-foreground"
                                />
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    Nenhuma unidade encontrada
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Ajuste os filtros ou cadastre uma nova
                                    unidade.
                                </p>
                            </div>
                        </div>

                        <div class="border-t px-7 py-3">
                            <CreateTeamModal>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                                >
                                    <Plus class="size-4" />
                                    Adicionar unidade
                                </button>
                            </CreateTeamModal>
                        </div>
                    </section>
                </InfiniteScroll>
            </section>
        </main>

        <DeleteTeamModal
            v-if="teamDeleting"
            v-model:open="deleteDialogOpen"
            :team="teamDeleting"
        />
    </div>
</template>
