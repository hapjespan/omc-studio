<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import { t } from '../i18n';
import { routes } from '../routes';

defineProps<{ role: string }>();
</script>

<template>
    <DropdownMenuRoot :modal="false">
        <DropdownMenuTrigger class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]">{{ t(`shell.role.${role}`) }}</DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent align="end" class="z-40 min-w-40 rounded-md border border-[#848B95] bg-white p-1 text-[13px] shadow-lg">
                <DropdownMenuItem class="cursor-pointer rounded px-3 py-1.5 data-[highlighted]:bg-[#EEF2F7]" @select="router.visit(routes.account())">{{ t('shell.account') }}</DropdownMenuItem>
                <DropdownMenuItem class="cursor-pointer rounded px-3 py-1.5 data-[highlighted]:bg-[#EEF2F7]" @select="router.post(routes.signOut())">{{ t('auth.sign_out') }}</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
