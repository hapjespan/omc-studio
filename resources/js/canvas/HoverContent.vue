<script lang="ts">
import type { DirectionColumnKey, LayoutStatement } from '../layout/types';
import { statementText } from './ViewDetail.vue';

export const DIRECTION_PREFIX = 'direction:';

export function directionText(statements: LayoutStatement[], key: DirectionColumnKey): string[] {
    const kind = key === 'goals' ? 'goal' : key;
    return statements.filter((statement) => statement.kind === kind).sort((a, b) => a.position - b.position).map(statementText);
}
</script>

<script setup lang="ts">
defineProps<{ id: string; x: number; y: number; title: string; lines: string[] }>();
const emit = defineEmits<{ enter: []; leave: [] }>();
</script>

<template>
    <div :id="id" role="tooltip" class="absolute z-[60] max-w-[420px] rounded-control border border-ink bg-white px-2.5 py-2 text-[12px] leading-[16.8px] text-ink shadow" :style="{ left: `${x}px`, top: `${y}px` }" data-hover-content @pointerenter="emit('enter')" @pointerleave="emit('leave')">
        <p class="text-[12.5px] font-semibold">{{ title }}</p>
        <p v-for="(line, index) in lines" :key="index" class="whitespace-pre-line">{{ line }}</p>
        <slot />
    </div>
</template>
