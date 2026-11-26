<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import ActionMenu from '../components/ui/ActionMenu.vue';
import Icon from '../components/ui/Icon.vue';
import { t } from '../i18n';
import { routes } from '../routes';

defineProps<{ role: string }>();

const select = (key: string): void => (key === 'account' ? router.visit(routes.account()) : void router.post(routes.signOut()));
</script>

<template>
    <ActionMenu :items="[{ key: 'account', label: t('shell.account') }, { key: 'sign_out', label: t('auth.sign_out') }]" @select="select">
        <template #trigger><button type="button" class="inline-flex h-dense items-center gap-1 rounded-control px-2 text-[13px] font-medium text-ink">{{ t(`shell.role.${role}`) }}<Icon name="chevron-down" :size="14" /></button></template>
    </ActionMenu>
</template>
