<script setup lang="ts">
import type { DrawnBlock } from '../layout/types';
import UnknownCard from './UnknownCard.vue';

const props = defineProps<{ block: DrawnBlock; label: string; selected: boolean; described: boolean; modalityColour: string | null }>();
const emit = defineEmits<{ select: [id: string]; peek: [id: string | null] }>();

const padding = { area: [12, 5], domain: [12, 12], cluster: [8, 8], activity: [8, 6] } as const;
const box = (): Record<string, string> => {
    const { block } = props;
    const [x, y] = padding[block.kind];
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
        class="absolute box-border block overflow-visible text-left"
        :class="{ 'outline-2 outline-[#2459D6]': selected }"
        :data-block="block.id"
        :data-kind="block.kind"
        :data-area="block.areaKey"
        :tabindex="block.kind === 'area' ? 0 : -1"
        :aria-label="label"
        :aria-pressed="selected"
        :aria-describedby="described ? 'view-hover' : undefined"
        :style="box()"
        @click="emit('select', block.id)"
        @pointerenter="emit('peek', block.id)"
        @pointerleave="emit('peek', null)"
        @focus="emit('peek', block.id)"
        @blur="emit('peek', null)"
    >
        <span v-for="(line, index) in block.textLines" :key="index" class="block whitespace-pre">{{ line }}</span>
        <UnknownCard v-if="block.system" :colour="block.style.textColour" />
        <span
            v-if="block.modality && modalityColour"
            class="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-[3px] text-[11px] font-bold leading-none text-white"
            :style="{ background: modalityColour }"
            aria-hidden="true"
        >{{ block.modality }}</span>
    </button>
</template>
