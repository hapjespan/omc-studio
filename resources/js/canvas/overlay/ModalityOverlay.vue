<script lang="ts">
import { t } from '../../i18n';
import type { ModalityCounts } from './counts';

export function countsLine(counts: ModalityCounts, letters: string[]): string {
    return [...letters.map((letter) => `${letter} ${counts.byLetter[letter] ?? 0}`), `${t('overlay.modality_none')} ${counts.none}`].join(', ');
}
</script>

<script setup lang="ts">
import type { DrawnBlock } from '../../layout/types';

defineProps<{ block: DrawnBlock; counts: ModalityCounts; letters: string[] }>();
</script>

<template>
    <p class="absolute inset-x-0 bottom-0 truncate px-3 pb-0.5 text-[11px] font-semibold leading-[14px]" :style="{ background: block.style.fill ?? 'transparent', color: block.style.textColour }" data-modality-counts>{{ countsLine(counts, letters) }}</p>
</template>
