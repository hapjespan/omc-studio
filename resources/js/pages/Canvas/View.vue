<script setup lang="ts">
import { computed, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import CanvasView from '../../canvas/CanvasView.vue';
import type { ModalityMark } from '../../canvas/CanvasView.vue';
import { contentHeight } from '../../canvas/extent';
import OverflowNotice from '../../canvas/OverflowNotice.vue';
import OverlaySwitch from '../../canvas/OverlaySwitch.vue';
import { descendantCounts } from '../../canvas/overlay/counts';
import type { CanvasFigures, ElementFigures, OverlayFilters as Filters } from '../../canvas/overlay/figures';
import FinancialCard, { areaLine, cardLines } from '../../canvas/overlay/FinancialCard.vue';
import OverlayFilters from '../../canvas/overlay/OverlayFilters.vue';
import type { FilterOptions } from '../../canvas/overlay/OverlayFilters.vue';
import OverlayLegend from '../../canvas/overlay/OverlayLegend.vue';
import { visitOverlay } from '../../canvas/overlay/query';
import { shareColour } from '../../canvas/overlay/ShareColour';
import Breakdown from '../../canvas/overlay/Breakdown.vue';
import ViewDetail, { detailOf } from '../../canvas/ViewDetail.vue';
import ZoomToolbar, { fitZoom } from '../../canvas/ZoomToolbar.vue';
import { ARTBOARD } from '../../layout';
import { detail, openDetail } from '../../shell/detail';
import ElementFiguresPanel from './ElementFigures.vue';
import FigureSidePanel from './FigureSidePanel.vue';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import { computeCanvasLayout } from '../../layout';
import type { CanvasLayoutInput, DrawnBlock, TextMeasurer } from '../../layout';

const props = defineProps<{
    canvas: { id: number; name: string; archived: boolean };
    canWrite: boolean;
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
    modalities: ModalityMark[];
    overlay: string;
    year: number | null;
    figures: CanvasFigures | null;
    figuresMessage: string | null;
    filters: Filters;
    filterOptions: FilterOptions;
}>();

const role = computed(() => (usePage().props.auth as { role: string | null }).role);
const costAreas = computed(() => new Set(props.layout.areas.filter((area) => area.kind === 'activities').map((area) => area.key)));
const domainCounts = computed(() => descendantCounts(props.elements));
const cardFigures = (block: DrawnBlock): ElementFigures | null => (props.figures && block.kind === 'domain' && costAreas.value.has(block.areaKey) ? (props.figures.elements[block.id] ?? null) : null);
const selectedFigures = computed(() => {
    const element = props.elements.find((candidate) => candidate.id === selectedId.value);
    return props.figures && element && costAreas.value.has(element.area) ? (props.figures.elements[element.id] ?? null) : null;
});
const hoverBlock = (id: string | null): DrawnBlock | null => drawn.value?.blocks.find((candidate) => candidate.id === id) ?? null;
const cardColours = computed(() => Object.fromEntries((drawn.value?.blocks ?? []).flatMap((block) => {
    const figures = cardFigures(block);
    return figures ? [[block.id, shareColour(figures.shareBp, block.style.fill)]] : [];
})));
const areaTotal = (block: DrawnBlock): string | null => (props.figures && block.kind === 'area' && costAreas.value.has(block.areaKey) && props.figures.areas[block.id] ? areaLine(props.figures.areas[block.id], props.figures.canvasTotal) : null);
const figureLines = (id: string): string[] => {
    const block = drawn.value?.blocks.find((candidate) => candidate.id === id);
    const area = block ? areaTotal(block) : null;
    if (area) return [area];
    const figures = block ? cardFigures(block) : null;
    if (!figures) return [];
    const lines = cardLines(figures, domainCounts.value.get(id) ?? { activities: 0, clusters: 0 }, props.year);
    return lines.empty ? [lines.empty] : [lines.total, lines.share, lines.forecast, lines.counts];
};

const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));
const drawn = computed(() => (measurer.value ? computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements }, measurer.value) : null));
const selectedId = ref<string | null>(null);
const content = computed(() => (selectedId.value ? detailOf(selectedId.value, { areas: props.layout.areas, elements: props.elements, statements: props.statements, modalities: props.modalities }) : null));
const select = (id: string): void => {
    selectedId.value = id;
    if (content.value) openDetail(content.value.title, document.querySelector<HTMLElement>(`[data-block="${CSS.escape(id)}"]`));
};
watch(() => detail.open, (open) => !open && (selectedId.value = null));

const zoom = ref(100);
const scroller = ref<HTMLElement>();
const fit = (): void => {
    if (scroller.value && drawn.value) zoom.value = fitZoom({ width: scroller.value.clientWidth, height: scroller.value.clientHeight }, { width: ARTBOARD.width, height: contentHeight(drawn.value) });
};
</script>

<template>
    <Head :title="canvas.name" />
    <h1 class="text-[20px] font-semibold">{{ canvas.name }}</h1>
    <OverflowNotice v-if="drawn" :count="drawn.beyondArtboardCount" />
    <p v-if="figuresMessage" role="alert" class="mt-3 rounded-md border border-[#B3261E] px-3 py-2 text-[13px] text-[#8C1D18]" data-figures-message>{{ figuresMessage }}</p>
    <div class="mt-3 flex flex-wrap items-center gap-4">
        <ZoomToolbar v-if="drawn" v-model:zoom="zoom" @fit="fit" />
        <OverlaySwitch :model-value="overlay" @update:model-value="visitOverlay({ overlay: $event === 'none' ? null : $event })" />
    </div>
    <OverlayFilters v-if="overlay === 'financial'" :filters="filters" :options="filterOptions" />
    <OverlayLegend :overlay="overlay" :modalities="modalities" />
    <div class="mt-3 flex gap-4">
    <div ref="scroller" class="h-[calc(100vh-14rem)] min-w-0 flex-1 overflow-auto" tabindex="-1" data-scroller>
        <CanvasView v-if="drawn" :drawn="drawn" :areas="layout.areas" :elements="elements" :statements="statements" :modalities="modalities" :zoom="zoom" :selected-id="selectedId" :figure-lines="figureLines" :card-colours="cardColours" @select="select">
            <template v-if="figures" #card="{ block }">
                <FinancialCard v-if="cardFigures(block)" :block="block" :figures="cardFigures(block)!" :counts="domainCounts.get(block.id) ?? { activities: 0, clusters: 0 }" :year="year" :colour="cardColours[block.id]" />
                <p v-else-if="areaTotal(block)" class="absolute right-3 top-1 text-[12px] font-semibold" :style="{ color: block.style.textColour }" data-area-total>{{ areaTotal(block) }}</p>
            </template>
            <template #hover="{ id }">
                <Breakdown v-if="id && hoverBlock(id) && cardFigures(hoverBlock(id)!)" :key="id" :element-id="id" />
            </template>
        </CanvasView>
    </div>
    <FigureSidePanel v-if="figures" :figures="figures" :canvas-id="canvas.id" :year="year" :can-assign="role === 'administrator'" />
    </div>
    <ViewDetail v-if="content && detail.open" :content="content" />
    <ElementFiguresPanel v-if="selectedFigures && selectedId && detail.open" :element-id="selectedId" :figures="selectedFigures" />
</template>
