<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import { t } from '../../i18n';
import TemplateDialogs from '../Overview/TemplateDialogs.vue';

const canvas = computed(() => usePage().props.canvas as { id: number; name: string; kind: string });
const dialogs = ref<InstanceType<typeof TemplateDialogs>>();
</script>

<template>
    <button v-if="canvas.kind === 'canvas'" type="button" class="inline-flex h-control items-center justify-center rounded-control border border-edge bg-white px-3 text-[13px] font-medium" @click="dialogs?.show('save', { id: canvas.id, name: canvas.name, counts: null })">{{ t('entry.save_template') }}</button>
    <TemplateDialogs ref="dialogs" @done="announce(t('templates.saved'))" />
</template>
