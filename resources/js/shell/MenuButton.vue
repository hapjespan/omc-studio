<script setup lang="ts">
import AppButton from '../components/ui/AppButton.vue';
import { t } from '../i18n';

const open = defineModel<boolean>({ default: false });
</script>

<template>
    <AppButton size="dense" class="shell-menu-button" aria-controls="primary-tabs" :aria-expanded="open" @click="open = !open">{{ t('shell.menu') }}</AppButton>
</template>
