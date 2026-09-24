<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardEdit,
    FileSpreadsheet,
    FileText,
    LayoutGrid,
    Table,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/app/components/AppLogo.vue';
import NavMain from '@/app/components/NavMain.vue';
import NavUser from '@/app/components/NavUser.vue';
import TeamSwitcher from '@/features/teams/components/TeamSwitcher.vue';
import { dashboard } from '@/routes';
import { index as dataEntryIndex } from '@/routes/data-entry';
import { index as reportsIndex } from '@/routes/reports';
import { index as spreadsheetImportsIndex } from '@/routes/spreadsheet-imports';
import { index as teamsIndex } from '@/routes/teams';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/shared/components/ui/sidebar';
import type { NavItem } from '@/shared/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const dataEntryUrl = computed(() =>
    page.props.currentTeam
        ? dataEntryIndex.url({ current_team: page.props.currentTeam.slug })
        : '#',
);

const spreadsheetImportsUrl = computed(() =>
    page.props.currentTeam
        ? spreadsheetImportsIndex.url({
              current_team: page.props.currentTeam.slug,
          })
        : '#',
);

const dataTableUrl = computed(() =>
    page.props.currentTeam
        ? `/${page.props.currentTeam.slug}/tabela-dados`
        : '#',
);

const reportsUrl = computed(() => {
    if (!page.props.currentTeam) {
        return '#';
    }

    return isAdmin.value
        ? reportsIndex.url({ current_team: page.props.currentTeam.slug })
        : `/${page.props.currentTeam.slug}/meus-relatorios`;
});

const isAdmin = computed(() => Boolean(page.props.auth?.user?.is_admin));

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl.value,
            icon: LayoutGrid,
        },
        {
            title: 'Entrada de dados',
            href: dataEntryUrl.value,
            icon: ClipboardEdit,
        },
        {
            title: 'Importar Planilha',
            href: spreadsheetImportsUrl.value,
            icon: FileSpreadsheet,
        },
        {
            title: 'Tabela de dados',
            href: dataTableUrl.value,
            icon: Table,
        },
        {
            title: 'Relatórios',
            href: reportsUrl.value,
            icon: FileText,
        },
        {
            title: 'Unidades',
            href: teamsIndex(),
            icon: Building2,
        },
    ];

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
