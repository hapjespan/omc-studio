<script setup lang="ts">
import { isListArea } from '../layout/arrangeAreas';
import type { DrawnBlock } from '../layout/types';
import type { PeekSource } from './peek';
import UnknownCard from './UnknownCard.vue';

const props = defineProps<{ block: DrawnBlock; label: string | null; modalityName: string | null; selected: boolean; described: boolean; modalityColour: string | null; colour?: { background: string; text: string } | null; summary?: string | null; inert?: boolean }>();
const emit = defineEmits<{ select: [id: string]; peek: [id: string | null, source: PeekSource] }>();

const padding = { area: [12, 12], domain: [12, 12], cluster: [8, 8], activity: [8, 6] } as const;
const HAIRLINE = 'var(--color-hairline)';
const box = (): Record<string, string> => {
    const { block } = props;
    const [x, y] = block.kind !== 'area' && isListArea(block.areaKey) ? [0, 0] : padding[block.kind];
    const border = block.style.border ?? (props.colour ? HAIRLINE : null);
    return {
        left: `${block.x}px`,
        top: `${block.y}px`,
        width: `${block.width}px`,
        height: `${block.drawnHeight}px`,
        zIndex: String(block.z + 1),
        background: props.colour?.background ?? block.style.fill ?? 'transparent',
        border: border ? `1px solid ${border}` : '0',
        borderRadius: `${block.style.radius}px`,
        color: props.colour?.text ?? block.style.textColour,
        letterSpacing: block.style.font.tracking ? `${block.style.font.tracking}em` : 'normal',
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
        class="absolute box-border flex flex-col items-stretch justify-start overflow-visible text-left"
        :class="{ 'outline-2 -outline-offset-2 outline-ink': selected, 'pointer-events-none': inert }"
        :inert="inert || undefined"
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
        <span v-for="(line, index) in block.textLines" :key="index" class="block whitespace-pre">{{ line }}</span>
        <UnknownCard v-if="block.system" :colour="block.style.textColour" />
        <span
            v-if="block.modality && modalityColour"
            class="absolute flex h-4 w-4 items-center justify-center rounded-badge text-[11px] font-bold leading-none text-white"
            :class="block.kind === 'domain' ? 'right-3 top-3.5' : 'right-1.5 top-1.5'"
            :style="{ background: modalityColour }"
            aria-hidden="true"
        >{{ block.modality }}</span>
        <span v-if="modalityName" class="sr-only">{{ modalityName }}</span>
        <span v-if="summary" class="sr-only">{{ summary }}</span>
    </button>
</template>
