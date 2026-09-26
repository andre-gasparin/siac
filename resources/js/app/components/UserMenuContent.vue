<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CheckSquare, LogOut, Settings } from '@lucide/vue';
import UserInfo from '@/app/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { index as tasksIndex } from '@/routes/tasks';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/shared/components/ui/dropdown-menu';
import type { User } from '@/shared/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="user.is_admin" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="tasksIndex.url()"
                prefetch
            >
                <CheckSquare class="mr-2 h-4 w-4" />
                Tarefas
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
