<script setup lang="ts">
import { watch } from 'vue';
import { announce } from '../components/feedback/status';
import { t } from '../i18n';

const props = defineProps<{ count: number }>();
const text = (): string => t('view.overflow', { count: props.count });

watch(() => props.count, (count) => count > 0 && announce(text()), { immediate: true });
</script>

<template>
    <p v-if="count > 0" class="mt-3 rounded-md border border-[#8A5200] bg-[#FFF3D6] px-3 py-2 text-[13px] text-[#8A5200]" data-overflow-notice>{{ text() }}</p>
</template>
