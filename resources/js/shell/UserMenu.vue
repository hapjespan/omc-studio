<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import Icon from '../components/ui/Icon.vue';
import { t } from '../i18n';
import { routes } from '../routes';

defineProps<{ role: string }>();
</script>

<template>
    <DropdownMenuRoot :modal="false">
        <DropdownMenuTrigger class="inline-flex h-dense items-center gap-1 rounded-control px-2 text-[13px] font-medium text-ink">{{ t(`shell.role.${role}`) }}<Icon name="chevron-down" :size="14" /></DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent align="end" class="z-40 min-w-40 rounded-control border border-hairline bg-white p-1 text-[13px] shadow-[0_1px_3px_rgba(0,0,0,.12)]">
                <DropdownMenuItem class="cursor-pointer rounded px-3 py-1.5 data-[highlighted]:bg-accent-soft" @select="router.visit(routes.account())">{{ t('shell.account') }}</DropdownMenuItem>
                <DropdownMenuItem class="cursor-pointer rounded px-3 py-1.5 data-[highlighted]:bg-accent-soft" @select="router.post(routes.signOut())">{{ t('auth.sign_out') }}</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
