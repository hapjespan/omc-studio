<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { CanvasFigures } from './overlay/figures';
import AreaTotal from './overlay/AreaTotal.vue';
import Breakdown from './overlay/Breakdown.vue';
import FinancialCard from './overlay/FinancialCard.vue';
import ModalityOverlay from './overlay/ModalityOverlay.vue';
import OverlayLegend from './overlay/OverlayLegend.vue';
import { useOverlay } from './overlay/useOverlay';
import { takeViewport, useViewportSource } from './viewport';
import CanvasView from './CanvasView.vue';
import type { ModalityColour } from './CanvasView.vue';
import ViewDetail, { detailOf } from './ViewDetail.vue';
import { contentHeight } from './extent';
import ZoomToolbar, { fitZoom, ZOOM_MIN } from './ZoomToolbar.vue';
import { ARTBOARD } from '../layout';
import type { DrawnBlock, DrawnCanvas, LayoutArea, LayoutElement, LayoutStatement } from '../layout';
import ElementFiguresPanel from '../pages/Canvas/ElementFigures.vue';
import FigureSidePanel from '../pages/Canvas/FigureSidePanel.vue';
import { detail, openDetail } from '../shell/detail';

const props = defineProps<{
    drawn: DrawnCanvas | null;
    areas: LayoutArea[];
    elements: LayoutElement[];
    statements: LayoutStatement[];
    modalities: ModalityColour[];
    overlay: string;
    year: number | null;
    figures: CanvasFigures | null;
    costKinds: string[];
    canvasId: number;
    canAssign: boolean;
    plain?: boolean;
}>();

const selectedId = ref<string | null>(null);
const letters = computed(() => props.modalities.map((modality) => modality.letter));
const { domainCounts, cardFigures, modalityCard, areaTotal, cardColours, figureLines, inertIds, selectedFigures, hoverBlock } = useOverlay(
    () => ({ overlay: props.overlay, year: props.year, figures: props.figures, areas: props.areas, elements: props.elements, letters: letters.value }),
    computed(() => props.drawn),
    selectedId,
);

const cardIds = computed(() => new Set((props.drawn?.blocks ?? []).filter((block) => cardFigures(block) ?? modalityCard(block) ?? areaTotal(block)).map((block) => block.id)));
const content = computed(() => (selectedId.value ? detailOf(selectedId.value, { areas: props.areas, elements: props.elements, statements: props.statements, modalities: props.modalities }) : null));
const kickerOf = (id: string): string => {
    const element = props.elements.find((candidate) => candidate.id === id);
    if (!element || !content.value) return '';
    const path = [props.areas.find((candidate) => candidate.key === element.area)?.label ?? ''];
    let parent = props.elements.find((candidate) => candidate.id === element.parent);
    while (parent) {
        path.splice(1, 0, parent.name);
        parent = props.elements.find((candidate) => candidate.id === parent?.parent);
    }

    return `${content.value.kind} · ${path.join(' › ')}`;
};
const select = (id: string): void => {
    selectedId.value = id;
    if (content.value) openDetail(content.value.title, document.querySelector<HTMLElement>(`[data-block="${CSS.escape(id)}"]`), kickerOf(id));
};
watch(() => detail.open, (open) => !open && (selectedId.value = null));

const zoom = ref(100);
const manual = ref(false);
const scroller = ref<HTMLElement>();
const position = takeViewport();
const space = ref<Record<string, string>>();
if (position) {
    manual.value = true;
    zoom.value = position.zoom * 100;
}
useViewportSource(() => {
    const bounds = scroller.value?.querySelector('[data-artboard]')?.getBoundingClientRect();
    if (bounds) return { x: bounds.left, y: bounds.top, zoom: zoom.value / 100 };
});
if (position) {
    watch(() => props.drawn && scroller.value, async (el) => {
        if (!el) return;
        const bounds = el.getBoundingClientRect();
        const x = position.x - bounds.left;
        const y = position.y - bounds.top;
        space.value = {
            paddingLeft: `${Math.max(0, x)}px`,
            paddingTop: `${Math.max(0, y)}px`,
            minWidth: `calc(100% + ${Math.max(0, -x)}px)`,
            minHeight: y < 0 ? `calc(100vh - 8rem + ${-y}px)` : '0',
        };
        await nextTick();
        el.scrollTo(Math.max(0, -x), Math.max(0, -y));
    }, { flush: 'post', once: true });
}
const SCROLLER_MARGIN = 128;
const fitWidth = (): void => {
    if (scroller.value?.clientWidth) zoom.value = Math.max(ZOOM_MIN, fitZoom({ width: scroller.value.clientWidth, height: Number.POSITIVE_INFINITY }, { width: ARTBOARD.width, height: ARTBOARD.height }));
};
const fitWhole = (): void => {
    if (!scroller.value?.clientWidth || !props.drawn) return;
    manual.value = true;
    space.value = undefined;
    zoom.value = fitZoom({ width: scroller.value.clientWidth, height: window.innerHeight - SCROLLER_MARGIN }, { width: ARTBOARD.width, height: contentHeight(props.drawn) });
};
const zoomTo = (value: number): void => {
    manual.value = true;
    zoom.value = value;
};
let observer: ResizeObserver | undefined;
onMounted(() => {
    observer = new ResizeObserver(() => !manual.value && fitWidth());
    if (scroller.value) observer.observe(scroller.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pb-3">
        <OverlayLegend :overlay="overlay" :modalities="modalities" />
        <ZoomToolbar v-if="drawn" :zoom="zoom" class="ml-auto" @update:zoom="zoomTo" @fit="fitWhole" />
    </div>
    <div class="flex flex-wrap items-start gap-4 lg:flex-nowrap">
        <div ref="scroller" class="max-h-[calc(100vh-8rem)] min-w-0 flex-1 basis-full overflow-auto lg:basis-0" tabindex="-1" data-scroller>
            <div :style="space">
                <CanvasView v-if="drawn" :drawn="drawn" :areas="areas" :elements="elements" :statements="statements" :modalities="modalities" :zoom="zoom" :layers-above="!!figures || overlay === 'modality'" :selected-id="selectedId" :figure-lines="figureLines" :card-colours="plain ? {} : cardColours" :inert-ids="inertIds" :card-ids="cardIds" @select="select">
                    <template v-if="!plain && (figures || overlay === 'modality')" #card="{ block }">
                        <FinancialCard v-if="cardFigures(block)" :block="block" :figures="cardFigures(block)!" :counts="domainCounts.get(block.id) ?? { activities: 0, clusters: 0 }" :year="year" :colour="cardColours[block.id]" />
                        <ModalityOverlay v-else-if="modalityCard(block)" :block="block" :counts="modalityCard(block)!" :letters="letters" />
                        <AreaTotal v-else-if="areaTotal(block)" :parts="areaTotal(block)!" />
                    </template>
                    <template #hover="{ id }">
                        <Breakdown v-if="id && cardFigures(hoverBlock(id) ?? ({} as DrawnBlock))" :key="id" :element-id="id" />
                    </template>
                </CanvasView>
            </div>
        </div>
        <FigureSidePanel v-if="figures && !detail.open" :figures="figures" :cost-kinds="costKinds" :canvas-id="canvasId" :year="year" :can-assign="canAssign" :modalities="modalities" />
    </div>
    <ViewDetail v-if="content && detail.open" :content="content" />
    <ElementFiguresPanel v-if="selectedFigures && selectedId && detail.open" :element-id="selectedId" :figures="selectedFigures" />
</template>
