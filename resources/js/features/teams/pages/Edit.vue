<script setup lang="ts">
import { Form, Head, router, setLayoutProps } from '@inertiajs/vue3';
import {
    ChevronDown,
    Mail,
    Settings,
    ShieldAlert,
    UserPlus,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CancelInvitationModal from '@/features/teams/components/CancelInvitationModal.vue';
import DeleteTeamModal from '@/features/teams/components/DeleteTeamModal.vue';
import InviteMemberModal from '@/features/teams/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/features/teams/components/RemoveMemberModal.vue';
import { edit, index, update } from '@/routes/teams';
import { update as updateMember } from '@/routes/teams/members';
import Heading from '@/shared/components/Heading.vue';
import InputError from '@/shared/components/InputError.vue';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/shared/components/ui/avatar';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/shared/components/ui/tooltip';
import { useInitials } from '@/shared/composables/useInitials';
import type {
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/shared/types';

type Props = {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
};

const props = defineProps<Props>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Unidades',
            href: index(),
        },
        {
            title: props.team.name,
            href: edit(props.team.slug),
        },
    ],
});

const { getInitials } = useInitials();

type TabType = 'general' | 'members' | 'danger';
const activeTab = ref<TabType>('general');
const isActive = ref(Boolean(props.team.isActive));

const inviteDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<TeamInvitation | null>(null);

const pageTitle = computed(() =>
    props.permissions.canUpdateTeam
        ? `Editar ${props.team.name}`
        : `Visualizar ${props.team.name}`,
);

const updateMemberRole = (member: TeamMember, newRole: string) => {
    router.visit(updateMember([props.team.slug, member.id]), {
        data: { role: newRole },
        preserveScroll: true,
    });
};

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};

const confirmCancelInvitation = (invitation: TeamInvitation) => {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
};
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <div class="space-y-6 px-4 py-6 md:px-6">
        <!-- Team Header Card -->
        <div
            class="flex flex-col gap-4 rounded-xl border bg-card p-6 shadow-xs sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-lg font-bold text-primary"
                >
                    {{ getInitials(team.name) }}
                </div>
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        {{ team.name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Gerencie as configurações, membros e acessos desta
                        unidade
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-border">
            <nav
                class="-mb-px flex space-x-6 overflow-x-auto"
                aria-label="Tabs"
            >
                <button
                    type="button"
                    @click="activeTab = 'general'"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                        activeTab === 'general'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground',
                    ]"
                >
                    <Settings class="h-4 w-4" />
                    <span>Geral</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'members'"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                        activeTab === 'members'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground',
                    ]"
                >
                    <Users class="h-4 w-4" />
                    <span>Membros & Convites</span>
                    <Badge variant="secondary" class="ml-1 text-xs">
                        {{ members.length }}
                    </Badge>
                </button>

                <button
                    v-if="permissions.canDeleteTeam && !team.isPersonal"
                    type="button"
                    @click="activeTab = 'danger'"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                        activeTab === 'danger'
                            ? 'border-destructive text-destructive'
                            : 'border-transparent text-muted-foreground hover:border-destructive/30 hover:text-destructive',
                    ]"
                >
                    <ShieldAlert class="h-4 w-4" />
                    <span>Zona de Perigo</span>
                </button>
            </nav>
        </div>

        <!-- Tab 1: Geral -->
        <div v-if="activeTab === 'general'" class="space-y-6">
            <div
                v-if="permissions.canUpdateTeam"
                class="space-y-6 rounded-xl border bg-card p-6 shadow-xs"
            >
                <Heading
                    variant="small"
                    title="Configurações da Unidade"
                    description="Atualize o nome e as preferências da sua unidade"
                />

                <Form
                    v-bind="update.form(team.slug)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid max-w-lg gap-2">
                        <Label for="name">Nome da Unidade</Label>
                        <Input
                            id="name"
                            name="name"
                            data-test="team-name-input"
                            :default-value="team.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            id="is_active_toggle"
                            type="button"
                            role="switch"
                            :aria-checked="isActive"
                            data-test="team-is-active-toggle"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                            :class="isActive ? 'bg-primary' : 'bg-muted'"
                            @click="isActive = !isActive"
                        >
                            <span
                                aria-hidden="true"
                                class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out"
                                :class="
                                    isActive ? 'translate-x-4' : 'translate-x-0'
                                "
                            />
                        </button>
                        <input
                            type="hidden"
                            name="is_active"
                            :value="isActive ? '1' : '0'"
                        />
                        <Label
                            for="is_active_toggle"
                            class="cursor-pointer text-sm font-medium"
                        >
                            Empresa {{ isActive ? 'ativa' : 'inativa' }}
                        </Label>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            type="submit"
                            data-test="team-save-button"
                            :disabled="processing"
                        >
                            Salvar Alterações
                        </Button>
                    </div>
                </Form>
            </div>

            <div
                v-else
                class="space-y-2 rounded-xl border bg-card p-6 shadow-xs"
            >
                <Heading variant="small" :title="team.name" />
                <p class="text-sm text-muted-foreground">
                    Você não tem permissão para alterar o nome desta unidade.
                </p>
            </div>
        </div>

        <!-- Tab 2: Membros & Convites -->
        <div v-if="activeTab === 'members'" class="space-y-8">
            <!-- Members Section -->
            <div class="space-y-6 rounded-xl border bg-card p-6 shadow-xs">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Heading
                        variant="small"
                        title="Membros da Unidade"
                        :description="
                            permissions.canCreateInvitation
                                ? 'Gerencie as pessoas que possuem acesso a esta unidade'
                                : ''
                        "
                    />

                    <Button
                        v-if="permissions.canCreateInvitation"
                        data-test="invite-member-button"
                        @click="inviteDialogOpen = true"
                    >
                        <UserPlus class="mr-2 h-4 w-4" /> Convidar Membro
                    </Button>
                </div>

                <div class="divide-y divide-border rounded-lg border">
                    <div
                        v-for="member in members"
                        :key="member.id"
                        data-test="member-row"
                        class="flex items-center justify-between p-4 transition-colors hover:bg-muted/30"
                    >
                        <div class="flex items-center gap-4">
                            <Avatar class="h-10 w-10">
                                <AvatarImage
                                    v-if="member.avatar"
                                    :src="member.avatar"
                                    :alt="member.name"
                                />
                                <AvatarFallback>{{
                                    getInitials(member.name)
                                }}</AvatarFallback>
                            </Avatar>
                            <div>
                                <div class="font-medium text-foreground">
                                    {{ member.name }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ member.email }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <DropdownMenu
                                v-if="
                                    member.role !== 'owner' &&
                                    permissions.canUpdateMember
                                "
                            >
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        data-test="member-role-trigger"
                                        variant="outline"
                                        size="sm"
                                    >
                                        {{ member.role_label }}
                                        <ChevronDown
                                            class="ml-2 h-4 w-4 opacity-50"
                                        />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-for="role in availableRoles"
                                        :key="role.value"
                                        data-test="member-role-option"
                                        @click="
                                            updateMemberRole(member, role.value)
                                        "
                                    >
                                        {{ role.label }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Badge v-else variant="secondary">
                                {{ member.role_label }}
                            </Badge>

                            <TooltipProvider
                                v-if="
                                    member.role !== 'owner' &&
                                    permissions.canRemoveMember
                                "
                            >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            data-test="member-remove-button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-muted-foreground hover:text-destructive"
                                            @click="confirmRemoveMember(member)"
                                        >
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Remover membro</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Invitations Section -->
            <div
                v-if="invitations.length > 0"
                class="space-y-6 rounded-xl border bg-card p-6 shadow-xs"
            >
                <Heading
                    variant="small"
                    title="Convites Pendentes"
                    description="Convites enviados que ainda não foram aceitos"
                />

                <div class="divide-y divide-border rounded-lg border">
                    <div
                        v-for="invitation in invitations"
                        :key="invitation.code"
                        data-test="invitation-row"
                        class="flex items-center justify-between p-4 transition-colors hover:bg-muted/30"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                            >
                                <Mail class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <div class="font-medium text-foreground">
                                    {{ invitation.email }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ invitation.role_label }}
                                </div>
                            </div>
                        </div>

                        <TooltipProvider v-if="permissions.canCancelInvitation">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        data-test="invitation-cancel-button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="
                                            confirmCancelInvitation(invitation)
                                        "
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Cancelar convite</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Danger Zone -->
        <div
            v-if="
                activeTab === 'danger' &&
                permissions.canDeleteTeam &&
                !team.isPersonal
            "
            class="space-y-6"
        >
            <div
                class="space-y-6 rounded-xl border border-destructive/30 bg-destructive/5 p-6 shadow-xs"
            >
                <Heading
                    variant="small"
                    title="Excluir Unidade"
                    description="Esta ação é permanente e não poderá ser desfeita"
                />

                <div class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        Ao excluir a unidade, todos os recursos, acessos e
                        configurações vinculados a ela serão permanentemente
                        removidos.
                    </p>
                    <Button
                        data-test="delete-team-button"
                        variant="destructive"
                        @click="deleteDialogOpen = true"
                    >
                        Excluir Unidade
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <InviteMemberModal
        v-if="permissions.canCreateInvitation"
        :team="team"
        :available-roles="availableRoles"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :team="team"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <CancelInvitationModal
        :team="team"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />

    <DeleteTeamModal
        v-if="permissions.canDeleteTeam && !team.isPersonal"
        :team="team"
        :open="deleteDialogOpen"
        @update:open="deleteDialogOpen = $event"
    />
</template>
