<script setup lang="ts">
import { computed, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import CanvasView from '../../canvas/CanvasView.vue';
import ExportMenu from '../../canvas/ExportMenu.vue';
import type { ModalityMark } from '../../canvas/CanvasView.vue';
import { contentHeight } from '../../canvas/extent';
import OverflowNotice from '../../canvas/OverflowNotice.vue';
import OverlaySwitch from '../../canvas/OverlaySwitch.vue';
import type { CanvasFigures, OverlayFilters as Filters } from '../../canvas/overlay/figures';
import FinancialCard from '../../canvas/overlay/FinancialCard.vue';
import ModalityOverlay from '../../canvas/overlay/ModalityOverlay.vue';
import OverlayFilters from '../../canvas/overlay/OverlayFilters.vue';
import type { FilterOptions } from '../../canvas/overlay/OverlayFilters.vue';
import OverlayLegend from '../../canvas/overlay/OverlayLegend.vue';
import { visitOverlay } from '../../canvas/overlay/query';
import { useOverlay } from '../../canvas/overlay/useOverlay';
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
const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));
const drawn = computed(() => (measurer.value ? computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements }, measurer.value) : null));
const selectedId = ref<string | null>(null);
const letters = computed(() => props.modalities.map((modality) => modality.letter));
const { domainCounts, cardFigures, modalityCard, areaTotal, cardColours, figureLines, inertIds, selectedFigures, hoverBlock } = useOverlay(
    () => ({ overlay: props.overlay, year: props.year, figures: props.figures, areas: props.layout.areas, elements: props.elements, letters: letters.value }),
    drawn,
    selectedId,
);

const organisationId = computed(() => (usePage().props.canvas as { organisation: { id: number } | null } | null)?.organisation?.id ?? null);
const content = computed(() => (selectedId.value ? detailOf(selectedId.value, { areas: props.layout.areas, elements: props.elements, statements: props.statements, modalities: props.modalities }) : null));
const select = (id: string): void => {
    selectedId.value = id;
    if (content.value) openDetail(content.value.title, document.querySelector<HTMLElement>(`[data-block="${CSS.escape(id)}"]`));
};
watch(() => detail.open, (open) => !open && (selectedId.value = null));

const plain = ref(false);
const zoom = ref(100);
const scroller = ref<HTMLElement>();
const fit = (): void => {
    if (scroller.value && drawn.value) zoom.value = fitZoom({ width: scroller.value.clientWidth, height: scroller.value.clientHeight }, { width: ARTBOARD.width, height: contentHeight(drawn.value) });
};
</script>

<template>
    <Head :title="canvas.name" />
    <h1 class="text-[20px] font-semibold">{{ canvas.name }}</h1>
    <ExportMenu :canvas-id="canvas.id" :organisation-id="organisationId" :beyond="drawn?.beyondArtboardCount ?? 0" class="mt-2" @plain="plain = $event" />
    <OverflowNotice v-if="drawn" :count="drawn.beyondArtboardCount" />
    <p v-if="figuresMessage" role="alert" class="mt-3 rounded-md border border-[#B3261E] px-3 py-2 text-[13px] text-[#8C1D18]" data-figures-message>{{ figuresMessage }}</p>
    <div class="mt-3 flex flex-wrap items-center gap-4">
        <ZoomToolbar v-if="drawn" v-model:zoom="zoom" @fit="fit" />
        <OverlaySwitch :model-value="overlay" @update:model-value="visitOverlay({ overlay: $event === 'none' ? null : $event })" />
    </div>
    <OverlayFilters v-if="overlay === 'financial'" :filters="filters" :options="filterOptions" />
    <OverlayLegend :overlay="overlay" :modalities="modalities" />
    <div class="mt-3 flex flex-wrap gap-4 lg:flex-nowrap">
        <div ref="scroller" class="h-[calc(100vh-14rem)] min-w-0 flex-1 basis-full overflow-auto lg:basis-0" tabindex="-1" data-scroller>
            <CanvasView v-if="drawn" :drawn="drawn" :areas="layout.areas" :elements="elements" :statements="statements" :modalities="modalities" :zoom="zoom" :selected-id="selectedId" :figure-lines="figureLines" :card-colours="plain ? {} : cardColours" :inert-ids="inertIds" @select="select">
                <template v-if="!plain && (figures || overlay === 'modality')" #card="{ block }">
                    <FinancialCard v-if="cardFigures(block)" :block="block" :figures="cardFigures(block)!" :counts="domainCounts.get(block.id) ?? { activities: 0, clusters: 0 }" :year="year" :colour="cardColours[block.id]" />
                    <ModalityOverlay v-else-if="modalityCard(block)" :block="block" :counts="modalityCard(block)!" :letters="letters" />
                    <p v-else-if="areaTotal(block)" class="absolute right-3 top-1 text-[12px] font-semibold" :style="{ color: block.style.textColour }" data-area-total>{{ areaTotal(block) }}</p>
                </template>
                <template #hover="{ id }">
                    <Breakdown v-if="id && cardFigures(hoverBlock(id) ?? ({} as DrawnBlock))" :key="id" :element-id="id" />
                </template>
            </CanvasView>
        </div>
        <FigureSidePanel v-if="figures" :figures="figures" :cost-kinds="filterOptions.costKinds" :canvas-id="canvas.id" :year="year" :can-assign="role === 'administrator'" />
    </div>
    <ViewDetail v-if="content && detail.open" :content="content" />
    <ElementFiguresPanel v-if="selectedFigures && selectedId && detail.open" :element-id="selectedId" :figures="selectedFigures" />
</template>
