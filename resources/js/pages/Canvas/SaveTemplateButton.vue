<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import { t } from '../../i18n';
import TemplateDialogs from '../Overview/TemplateDialogs.vue';

const canvas = computed(() => usePage().props.canvas as { id: number; name: string; kind: string });
const dialogs = ref<InstanceType<typeof TemplateDialogs>>();
</script>

<template>
    <AppButton v-if="canvas.kind === 'canvas'" @click="dialogs?.show('save', { id: canvas.id, name: canvas.name, counts: null })">{{ t('entry.save_template') }}</AppButton>
    <TemplateDialogs ref="dialogs" @done="announce(t('templates.saved'))" />
</template>
