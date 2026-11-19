<script setup lang="ts">
import { isListArea } from '../layout/arrangeAreas';
import type { DrawnBlock } from '../layout/types';
import type { PeekSource } from './peek';
import UnknownCard from './UnknownCard.vue';

const props = defineProps<{ block: DrawnBlock; label: string | null; modalityName: string | null; selected: boolean; described: boolean; modalityColour: string | null }>();
const emit = defineEmits<{ select: [id: string]; peek: [id: string | null, source: PeekSource] }>();

const padding = { area: [12, 5], domain: [12, 12], cluster: [8, 8], activity: [8, 6] } as const;
const box = (): Record<string, string> => {
    const { block } = props;
    const [x, y] = block.kind !== 'area' && isListArea(block.areaKey) ? [0, 0] : padding[block.kind];
    return {
        left: `${block.x}px`,
        top: `${block.y}px`,
        width: `${block.width}px`,
        height: `${block.drawnHeight}px`,
        zIndex: String(block.z + 1),
        background: block.style.fill ?? 'transparent',
        border: block.style.border ? `1px solid ${block.style.border}` : '0',
        borderRadius: `${block.style.radius}px`,
        color: block.style.textColour,
        padding: `${y}px ${x}px`,
        fontFamily: `"${block.style.font.family}"`,
        fontSize: `${block.style.font.size}px`,
        fontWeight: String(block.style.font.weight),
        lineHeight: `${block.style.lineHeight}px`,
    };
};
</script>

<template>
    <button
        type="button"
        class="absolute box-border flex flex-col justify-start overflow-visible text-left"
        :class="{ 'outline-2 outline-accent': selected }"
        :data-block="block.id"
        :data-kind="block.kind"
        :data-area="block.areaKey"
        :tabindex="block.kind === 'area' ? 0 : -1"
        :aria-label="label ?? undefined"
        :aria-pressed="selected"
        :aria-describedby="described ? 'view-hover' : undefined"
        :style="box()"
        @click="emit('select', block.id)"
        @pointerenter="emit('peek', block.id, 'pointer')"
        @pointerleave="emit('peek', null, 'pointer')"
        @focus="emit('peek', block.id, 'focus')"
        @blur="emit('peek', null, 'focus')"
    >
        <span v-for="(line, index) in block.textLines" :key="index" class="block shrink-0 whitespace-pre">{{ line }}</span>
        <UnknownCard v-if="block.system" :colour="block.style.textColour" />
        <span
            v-if="block.modality && modalityColour"
            class="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-[3px] text-[11px] font-bold leading-none text-white"
            :style="{ background: modalityColour }"
            aria-hidden="true"
        >{{ block.modality }}</span>
        <span v-if="modalityName" class="sr-only">{{ modalityName }}</span>
    </button>
</template>
