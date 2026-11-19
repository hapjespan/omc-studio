<script setup lang="ts">
import { computed, ref } from 'vue';
import { t } from '../i18n';
import { ARTBOARD } from '../layout';
import type { DrawnCanvas, LayoutArea, LayoutElement, LayoutStatement } from '../layout/types';
import ArtboardEdge from './ArtboardEdge.vue';
import Block from './Block.vue';
import { contentHeight } from './extent';
import { isNavigationKey, navigate } from './treeNavigation';

export interface ModalityMark {
    key: string;
    letter: string;
    colour: string;
}

const props = defineProps<{
    drawn: DrawnCanvas;
    areas: LayoutArea[];
    elements: LayoutElement[];
    statements: LayoutStatement[];
    modalities: ModalityMark[];
    selectedId?: string | null;
    zoom?: number;
}>();
const scale = computed(() => (props.zoom ?? 100) / 100);
const height = computed(() => contentHeight(props.drawn));
const artboard = ref<HTMLElement>();
const move = (event: KeyboardEvent): void => {
    const current = (event.target as HTMLElement).closest<HTMLElement>('[data-block]')?.dataset.block;
    if (!current || !isNavigationKey(event.key)) return;
    event.preventDefault();
    const next = navigate(props.drawn.blocks, current, event.key);
    if (next) artboard.value?.querySelector<HTMLElement>(`[data-block="${CSS.escape(next)}"]`)?.focus();
};
const emit = defineEmits<{ select: [id: string]; peek: [id: string | null] }>();

const names = computed(() => new Map<string, string>([...props.areas.map((area) => [area.key, area.label] as [string, string]), ...props.elements.map((element) => [element.id, element.name] as [string, string])]));
const colours = computed(() => new Map(props.modalities.map((modality) => [modality.letter, modality.colour])));
const band = computed(() => props.drawn.blocks.find((block) => block.id === 'direction'));
const blocks = computed(() => props.drawn.blocks);
</script>

<template>
    <div class="relative" :style="{ width: `${ARTBOARD.width * scale}px`, height: `${height * scale}px` }">
    <div class="absolute left-0 top-0 origin-top-left" :style="{ width: `${ARTBOARD.width}px`, height: `${height}px`, transform: `scale(${scale})` }" ref="artboard" data-artboard @keydown="move">
        <ArtboardEdge :height="height" />
        <Block
            v-for="block in blocks"
            :key="block.id"
            :block="block"
            :label="names.get(block.id) ?? block.id"
            :selected="selectedId === block.id"
            :modality-colour="block.modality ? (colours.get(block.modality) ?? null) : null"
            @select="emit('select', $event)"
            @peek="emit('peek', $event)"
        />
        <div v-if="band" class="pointer-events-none absolute left-0 top-0 z-[2]" :style="{ width: `${ARTBOARD.width}px`, height: `${ARTBOARD.directionHeight}px`, color: band.style.textColour, fontFamily: '\'Inter\'' }">
            <div v-for="column in drawn.direction" :key="column.key" class="absolute top-0 box-border p-4" :style="{ left: `${column.x}px`, width: `${column.width}px` }" :data-direction="column.key">
                <p v-if="column.label" class="text-[11px] font-semibold leading-[14px]" :style="{ color: band.style.labelColour }">{{ column.label }}</p>
                <p v-for="(line, index) in column.lines" :key="index" class="whitespace-pre text-[13px] leading-[18px]" :class="{ 'mt-2': index === 0 && column.label }">{{ line }}</p>
            </div>
        </div>
        <span class="sr-only">{{ t('view.artboard') }}</span>
    </div>
    </div>
</template>
