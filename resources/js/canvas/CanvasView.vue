<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useSlots } from 'vue';
import { t } from '../i18n';
import { ARTBOARD } from '../layout';
import type { DrawnCanvas, LayoutArea, LayoutElement, LayoutStatement } from '../layout/types';
import ArtboardEdge from './ArtboardEdge.vue';
import Block from './Block.vue';
import HoverContent, { DIRECTION_PREFIX, directionText } from './HoverContent.vue';
import { contentHeight } from './extent';
import { splitDirectionLine } from './directionLines';
import { createPeek } from './peek';
import { detailOf } from './ViewDetail.vue';
import { isNavigationKey, navigate } from './treeNavigation';

export interface ModalityColour {
    key: string;
    letter: string;
    colour: string;
}

const props = defineProps<{
    drawn: DrawnCanvas;
    areas: LayoutArea[];
    elements: LayoutElement[];
    statements: LayoutStatement[];
    modalities: ModalityColour[];
    selectedId?: string | null;
    zoom?: number;
    cardColours?: Record<string, { background: string; text: string }>;
    figureLines?: (id: string) => string[];
    inertIds?: Set<string>;
}>();
const slots = useSlots();
const scale = computed(() => (props.zoom ?? 100) / 100);
const height = computed(() => contentHeight(props.drawn));
const artboard = ref<HTMLElement>();
const move = (event: KeyboardEvent): void => {
    const current = (event.target as HTMLElement).closest<HTMLElement>('[data-block]')?.dataset.block;
    if (!current || !isNavigationKey(event.key)) return;
    event.preventDefault();
    const next = navigate(props.inertIds?.size ? props.drawn.blocks.filter((block) => !props.inertIds?.has(block.id)) : props.drawn.blocks, current, event.key);
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
        return column && frame ? { x: column.x * scale.value, y: (frame.drawnHeight + 4) * scale.value, title: column.label ?? '', lines: directionText(props.statements, column.key) } : null;
    }
    const block = props.drawn.blocks.find((candidate) => candidate.id === id);
    const content = detailOf(id, { areas: props.areas, elements: props.elements, statements: props.statements, modalities: props.modalities });
    if (!block || !content) return null;
    if (block.kind === 'area') {
        const lines = props.figureLines?.(id) ?? [];
        return lines.length ? { x: (block.x + 8) * scale.value, y: (block.y + 30) * scale.value, title: content.title, lines } : null;
    }
    return { x: (block.x + block.width + 8) * scale.value, y: block.y * scale.value, title: content.title, lines: [content.kind, ...(content.modality ? [content.modality.name] : []), ...(content.system ? [t('view.system')] : []), ...(props.figureLines?.(id) ?? [])] };
});

const names = computed(() => new Map<string, string>([...props.areas.map((area) => [area.key, area.label] as [string, string]), ...props.elements.map((element) => [element.id, element.name] as [string, string])]));
const areaLabel = (id: string): string => [names.value.get(id) ?? id, ...(props.figureLines?.(id) ?? [])].join('. ');
const colours = computed(() => new Map(props.modalities.map((modality) => [modality.letter, modality.colour])));
const modalityNames = computed(() => new Map(props.modalities.map((modality) => [modality.letter, t(`view.modality.${modality.key}`)])));
const band = computed(() => props.drawn.blocks.find((block) => block.id === 'direction'));
const blocks = computed(() => props.drawn.blocks);
const segments = computed(() => new Map(props.drawn.direction.map((column) => [column.key, column.lines.map((line, index) => splitDirectionLine(line, column.marks[index]))])));
</script>

<template>
    <div class="relative" :style="{ width: `${ARTBOARD.width * scale}px`, height: `${height * scale}px` }">
    <div class="absolute left-0 top-0 origin-top-left" :style="{ width: `${ARTBOARD.width}px`, height: `${height}px`, transform: `scale(${scale})`, '--artboard-scale': scale }" ref="artboard" data-artboard @keydown="move">
        <ArtboardEdge :height="height" />
        <Block
            v-for="block in blocks"
            :key="block.id"
            :block="block"
            :label="block.kind === 'area' ? areaLabel(block.id) : null"
            :selected="selectedId === block.id"
            :modality-colour="block.modality ? (colours.get(block.modality) ?? null) : null"
            :modality-name="block.modality ? (modalityNames.get(block.modality) ?? null) : null"
            :colour="cardColours?.[block.id] ?? null"
            :summary="figureLines && block.kind !== 'area' ? figureLines(block.id).join('. ') : null"
            :inert="inertIds?.has(block.id) ?? false"
            @select="emit('select', $event)"
            :described="!!hover && peeked === block.id"
            @peek="peek"
        />
        <template v-if="slots.card">
            <div
                v-for="block in blocks"
                :key="block.id"
                class="pointer-events-none absolute overflow-hidden"
                :class="{ 'outline-2 -outline-offset-2 outline-ink': selectedId === block.id }"
                :style="{ left: `${block.x}px`, top: `${block.y}px`, width: `${block.width}px`, height: `${block.drawnHeight}px`, zIndex: 40, borderRadius: `${block.style.radius}px` }"
                :data-overlay-card="block.id"
                aria-hidden="true"
            >
                <slot name="card" :block="block" />
            </div>
        </template>
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
                @focus="column.truncated && peek(DIRECTION_PREFIX + column.key, 'focus')"
                @blur="column.truncated && peek(null, 'focus')"
            >
                <p v-if="column.label" class="text-[11px] font-semibold uppercase leading-[14px] tracking-[0.05em]" :style="{ color: band.style.labelColour }">{{ column.label }}</p>
                <div v-if="column.key === 'logo'" class="flex h-[92px] w-[160px] items-center justify-center rounded-card border border-dashed border-white/40 text-[11px] font-semibold uppercase tracking-[0.05em]" :style="{ color: band.style.labelColour }" data-direction-logo>{{ t('view.direction.logo') }}</div>
                <p v-for="(segment, index) in segments.get(column.key)" :key="index" class="whitespace-pre text-[13px] leading-[18px]" :class="{ 'mt-2': index === 0 && column.label, 'pl-[18px]': column.key === 'goals' && !segment.number }"><span v-if="segment.number" class="inline-block w-[18px]">{{ segment.number }}</span><strong v-if="segment.bold" class="font-semibold">{{ segment.bold }}</strong>{{ segment.rest }}</p>
            </div>
        </div>
        <span class="sr-only">{{ t('view.artboard') }}</span>
    </div>
    <HoverContent v-if="hover" id="view-hover" v-bind="hover" @enter="peek(peeked)" @leave="peek(null)">
        <slot name="hover" :id="peeked" />
    </HoverContent>
    </div>
</template>
