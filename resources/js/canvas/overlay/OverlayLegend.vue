<script setup lang="ts">
import { t } from '../../i18n';
import type { ModalityMark } from '../CanvasView.vue';
import { shareColour } from './ShareColour';

defineProps<{ overlay: string; modalities: ModalityMark[] }>();
const steps = [0, 1000, 2500, 5000].map((share) => shareColour(share, '#FFFFFF').background);
</script>

<template>
    <div v-if="overlay === 'financial'" class="mt-3 flex flex-col gap-1 text-[13px]" data-legend="financial">
        <p class="flex items-center gap-2">
            <span class="font-semibold">{{ t('overlay.legend.scale_label') }}</span>
            <span class="flex" aria-hidden="true"><span v-for="step in steps" :key="step" class="h-3 w-6 border border-[#848B95]" :style="{ background: step }" /></span>
            <span>{{ t('overlay.legend.scale') }}</span>
        </p>
        <p>{{ t('overlay.legend.bar') }}</p>
        <p>{{ t('overlay.legend.lists') }}</p>
    </div>
    <div v-else-if="overlay === 'modality'" class="mt-3 text-[13px]" data-legend="modality">
        <p class="font-semibold">{{ t('overlay.legend.modality') }}</p>
        <ul class="flex flex-wrap gap-x-4 gap-y-1">
            <li v-for="modality in modalities" :key="modality.key" class="flex items-center gap-2">
                <span class="flex h-4 w-4 items-center justify-center rounded-[3px] text-[11px] font-bold leading-none text-white" :style="{ background: modality.colour }" aria-hidden="true">{{ modality.letter }}</span>
                <span>{{ modality.letter }}: {{ t(`view.modality.${modality.key}`) }}</span>
            </li>
        </ul>
    </div>
</template>
