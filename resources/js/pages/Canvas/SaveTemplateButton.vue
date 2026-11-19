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
    <button v-if="canvas.kind === 'canvas'" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="dialogs?.show('save', { id: canvas.id, name: canvas.name, counts: null })">{{ t('entry.save_template') }}</button>
    <TemplateDialogs ref="dialogs" @done="announce(t('templates.saved'))" />
</template>
