<script setup lang="ts">
import { t } from '../i18n';

const open = defineModel<boolean>({ default: false });
</script>

<template>
    <button type="button" aria-controls="primary-tabs" :aria-expanded="open" class="shell-menu-button h-dense rounded-control border border-edge bg-white px-3 text-[13px] font-medium" @click="open = !open">{{ t('shell.menu') }}</button>
</template>
