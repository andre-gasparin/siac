<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    CheckCircle2,
    ClipboardList,
    LockKeyhole,
    LogIn,
    Monitor,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const previewRows = [
    {
        label: 'Parametros diarios',
        status: 'Em revisao',
        icon: ClipboardList,
    },
    {
        label: 'Relatorio operacional',
        status: 'Concluido',
        icon: CheckCircle2,
    },
    {
        label: 'Sistemas monitorados',
        status: '18 ativos',
        icon: Monitor,
    },
];
</script>

<template>
    <Head title="Consucal" />

    <main
        class="min-h-screen bg-slate-50 text-slate-950 dark:bg-neutral-950 dark:text-white"
    >
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6">
            <header
                class="flex items-center justify-between gap-4 border-b border-slate-200 py-5 dark:border-neutral-800"
            >
                <Link
                    :href="$page.props.auth.user ? dashboardUrl : '/'"
                    class="shrink-0"
                >
                    <img
                        src="/logo.png"
                        alt="Consucal"
                        class="h-10 w-auto object-contain"
                    />
                </Link>

                <nav class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardUrl"
                        class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                    >
                        Dashboard
                        <ArrowRight class="size-4" />
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm transition hover:border-primary/40 hover:text-primary dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
                        >
                            <LogIn class="size-4" />
                            Entrar
                        </Link>
                        <Link
                            :href="register()"
                            class="hidden h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:inline-flex"
                        >
                            Criar conta
                            <ArrowRight class="size-4" />
                        </Link>
                    </template>
                </nav>
            </header>

            <section
                class="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[0.95fr_1.05fr] lg:py-12"
            >
                <div class="max-w-xl space-y-7">
                    <div
                        class="inline-flex items-center gap-2 rounded-md border border-primary/20 bg-primary/10 px-3 py-1 text-sm font-medium text-primary dark:border-primary/30 dark:bg-primary/15"
                    >
                        <ShieldCheck class="size-4" />
                        Ambiente seguro SIAC
                    </div>

                    <div class="space-y-4">
                        <h1
                            class="text-4xl font-semibold text-balance text-slate-950 md:text-5xl dark:text-white"
                        >
                            Controle operacional com dados claros.
                        </h1>
                        <p class="text-base leading-7 text-muted-foreground">
                            Acesse equipes, monitoramentos e relatorios em uma
                            interface objetiva para a rotina da Consucal.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="
                                $page.props.auth.user ? dashboardUrl : login()
                            "
                            class="inline-flex h-11 items-center gap-2 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                        >
                            <LockKeyhole class="size-4" />
                            Acessar painel
                        </Link>
                        <Link
                            v-if="!$page.props.auth.user"
                            :href="register()"
                            class="inline-flex h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-5 text-sm font-medium text-slate-700 shadow-sm transition hover:border-primary/40 hover:text-primary dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
                        >
                            Solicitar acesso
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>

                <div
                    class="rounded-lg border border-slate-200 bg-white shadow-xl shadow-slate-200/70 dark:border-neutral-800 dark:bg-neutral-900 dark:shadow-black/30"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                    >
                        <div class="flex items-center gap-3">
                            <img
                                src="/favicon.png"
                                alt=""
                                class="size-9 object-contain"
                            />
                            <div>
                                <p class="text-sm font-semibold">
                                    Painel Consucal
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Monitoramento semanal
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-md bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                        >
                            Online
                        </span>
                    </div>

                    <div class="grid gap-4 p-5">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                class="rounded-lg border border-slate-200 p-4 dark:border-neutral-800"
                            >
                                <BarChart3 class="mb-3 size-5 text-primary" />
                                <p class="text-2xl font-semibold">42</p>
                                <p class="text-xs text-muted-foreground">
                                    Relatorios
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 p-4 dark:border-neutral-800"
                            >
                                <Monitor class="mb-3 size-5 text-primary" />
                                <p class="text-2xl font-semibold">18</p>
                                <p class="text-xs text-muted-foreground">
                                    Sistemas
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 p-4 dark:border-neutral-800"
                            >
                                <ShieldCheck class="mb-3 size-5 text-primary" />
                                <p class="text-2xl font-semibold">7</p>
                                <p class="text-xs text-muted-foreground">
                                    Pendencias
                                </p>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-lg border">
                            <div
                                class="grid grid-cols-[1fr_120px] gap-4 border-b bg-slate-50 px-4 py-3 text-xs font-medium text-muted-foreground dark:bg-neutral-950"
                            >
                                <span>Fila</span>
                                <span>Status</span>
                            </div>
                            <div
                                v-for="row in previewRows"
                                :key="row.label"
                                class="grid grid-cols-[1fr_120px] items-center gap-4 border-b px-4 py-3 last:border-b-0"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary"
                                    >
                                        <component
                                            :is="row.icon"
                                            class="size-4"
                                        />
                                    </span>
                                    <span class="text-sm font-medium">
                                        {{ row.label }}
                                    </span>
                                </div>
                                <span class="text-sm text-muted-foreground">
                                    {{ row.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
