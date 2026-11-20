<script lang="ts">
import type { DirectionColumnKey, LayoutStatement } from '../layout/types';
import { statementLines } from './ViewDetail.vue';

export const DIRECTION_PREFIX = 'direction:';

export function directionText(statements: LayoutStatement[], key: DirectionColumnKey): string[] {
    return key === 'logo' ? [] : statementLines(statements, key === 'goals' ? 'goal' : key);
}
</script>

<script setup lang="ts">
defineProps<{ id: string; x: number; y: number; title: string; lines: string[] }>();
const emit = defineEmits<{ enter: []; leave: [] }>();
</script>

<template>
    <div :id="id" role="tooltip" class="absolute z-[60] max-w-[420px] rounded-md border border-edge bg-white px-3 py-2 text-[13px] leading-[18px] text-ink shadow" :style="{ left: `${x}px`, top: `${y}px` }" data-hover-content @pointerenter="emit('enter')" @pointerleave="emit('leave')">
        <p class="font-semibold">{{ title }}</p>
        <p v-for="(line, index) in lines" :key="index" class="whitespace-pre-line">{{ line }}</p>
    </div>
</template>
