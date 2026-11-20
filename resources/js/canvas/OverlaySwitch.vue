<script setup lang="ts">
import SegmentedControl from '../components/ui/SegmentedControl.vue';
import { t } from '../i18n';

const model = defineModel<string>({ required: true });
const items = ['none', 'modality', 'financial'].map((key) => ({ key, label: t(`overlay.${key}`) }));
</script>

<template>
    <div class="flex items-center gap-2">
        <span class="text-[12.5px] text-secondary" aria-hidden="true">{{ t('overlay.label') }}</span>
        <SegmentedControl :items="items" :active="model" :label="t('overlay.label')" @select="model = $event" />
    </div>
</template>
