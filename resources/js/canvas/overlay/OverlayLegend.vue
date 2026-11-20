<script setup lang="ts">
import { t } from '../../i18n';
import type { ModalityMark } from '../CanvasView.vue';
import { shareColour } from './ShareColour';

defineProps<{ overlay: string; modalities: ModalityMark[] }>();
const steps = [0, 2500, 5000].map((share) => shareColour(share, '#FFFFFF').background);
</script>

<template>
    <div v-if="overlay === 'financial'" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-secondary" data-legend="financial">
        <p class="inline-flex items-center gap-1.5">
            <span>{{ t('overlay.legend.scale_label') }}</span>
            <span class="inline-flex gap-0.5" aria-hidden="true"><span v-for="(step, index) in steps" :key="step" class="h-2.5 w-[18px]" :class="{ 'border border-hairline': index === 0 }" :style="{ background: step }" /></span>
            <span>{{ t('overlay.legend.scale') }}</span>
        </p>
        <p class="inline-flex items-center gap-1.5"><span class="h-3 w-0.5 bg-ink" aria-hidden="true" />{{ t('overlay.legend.bar') }}</p>
        <p>{{ t('overlay.legend.lists') }}</p>
    </div>
    <div v-else-if="overlay === 'modality'" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-secondary" data-legend="modality">
        <p>{{ t('overlay.legend.modality') }}</p>
        <ul class="flex flex-wrap gap-x-4 gap-y-1">
            <li v-for="modality in modalities" :key="modality.key" class="flex items-center gap-2">
                <span class="flex h-4 w-4 items-center justify-center rounded-badge text-[11px] font-bold leading-none text-white" :style="{ background: modality.colour }" aria-hidden="true">{{ modality.letter }}</span>
                <span>{{ modality.letter }}: {{ t(`view.modality.${modality.key}`) }}</span>
            </li>
        </ul>
    </div>
</template>
