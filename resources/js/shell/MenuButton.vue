<script setup lang="ts">
import { t } from '../i18n';

const open = defineModel<boolean>({ default: false });
</script>

<template>
    <button type="button" aria-controls="primary-tabs" :aria-expanded="open" class="shell-menu-button rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="open = !open">{{ t('shell.menu') }}</button>
</template>
