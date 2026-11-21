<script setup lang="ts">
import { watch } from 'vue';
import { announce } from '../components/feedback/status';
import Callout from '../components/ui/Callout.vue';
import { t } from '../i18n';

const props = defineProps<{ count: number; administrator?: boolean }>();
const text = (): string => t('view.overflow', { count: props.count });
const hint = (): string => (props.administrator ? t('view.overflow_hint') : '');

watch(() => props.count, (count) => count > 0 && announce(`${text()} ${hint()}`.trim()), { immediate: true });
</script>

<template>
    <template v-if="count > 0">
        <Callout class="mt-3" data-overflow-notice>{{ text() }}</Callout>
        <p v-if="administrator" class="mt-1 text-[13px] text-secondary" data-overflow-hint>{{ hint() }}</p>
    </template>
</template>
