<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { t } from '../i18n';
import { ARTBOARD } from '../layout';
import type { DrawnCanvas, LayoutArea, LayoutElement, LayoutStatement } from '../layout/types';
import ArtboardEdge from './ArtboardEdge.vue';
import Block from './Block.vue';
import HoverContent, { DIRECTION_PREFIX, directionText } from './HoverContent.vue';
import { contentHeight } from './extent';
import { createPeek } from './peek';
import { detailOf } from './ViewDetail.vue';
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
const emit = defineEmits<{ select: [id: string] }>();

const { peeked, peek, dismiss } = createPeek();
const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && peeked.value) dismiss();
};
onMounted(() => document.addEventListener('keydown', onEscape));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onEscape);
    dismiss();
});

const hover = computed(() => {
    const id = peeked.value;
    if (!id) return null;
    if (id.startsWith(DIRECTION_PREFIX)) {
        const column = props.drawn.direction.find((candidate) => candidate.key === id.slice(DIRECTION_PREFIX.length));
        const frame = props.drawn.blocks.find((block) => block.id === 'direction');
        return column && frame ? { x: column.x, y: frame.drawnHeight + 4, title: column.label ?? '', lines: directionText(props.statements, column.key) } : null;
    }
    const block = props.drawn.blocks.find((candidate) => candidate.id === id);
    const content = detailOf(id, { areas: props.areas, elements: props.elements, statements: props.statements, modalities: props.modalities });
    if (!block || !content || block.kind === 'area') return null;
    return { x: block.x, y: block.y + block.drawnHeight + 4, title: content.title, lines: [content.kind, ...(content.modality ? [content.modality.name] : []), ...(content.system ? [t('view.system')] : [])] };
});

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
            :described="peeked === block.id"
            @peek="peek"
        />
        <div v-if="band" class="pointer-events-none absolute left-0 top-0 z-[2]" :style="{ width: `${ARTBOARD.width}px`, height: `${ARTBOARD.directionHeight}px`, color: band.style.textColour, fontFamily: '\'Inter\'' }">
            <div
                v-for="column in drawn.direction"
                :key="column.key"
                class="absolute top-0 box-border p-4"
                :class="{ 'pointer-events-auto': column.truncated }"
                :style="{ left: `${column.x}px`, width: `${column.width}px` }"
                :data-direction="column.key"
                :tabindex="column.truncated ? 0 : undefined"
                :role="column.truncated ? 'group' : undefined"
                :aria-label="column.truncated ? column.label ?? undefined : undefined"
                :aria-describedby="column.truncated && peeked === DIRECTION_PREFIX + column.key ? 'view-hover' : undefined"
                @pointerenter="column.truncated && peek(DIRECTION_PREFIX + column.key)"
                @pointerleave="column.truncated && peek(null)"
                @focus="column.truncated && peek(DIRECTION_PREFIX + column.key)"
                @blur="column.truncated && peek(null)"
            >
                <p v-if="column.label" class="text-[11px] font-semibold leading-[14px]" :style="{ color: band.style.labelColour }">{{ column.label }}</p>
                <p v-for="(line, index) in column.lines" :key="index" class="whitespace-pre text-[13px] leading-[18px]" :class="{ 'mt-2': index === 0 && column.label }">{{ line }}</p>
            </div>
        </div>
        <HoverContent v-if="hover" id="view-hover" v-bind="hover" @enter="peek(peeked)" @leave="peek(null)" />
        <span class="sr-only">{{ t('view.artboard') }}</span>
    </div>
    </div>
</template>
