<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import CanvasView from '../../canvas/CanvasView.vue';
import type { ModalityMark } from '../../canvas/CanvasView.vue';
import { contentHeight } from '../../canvas/extent';
import OverflowNotice from '../../canvas/OverflowNotice.vue';
import ViewDetail, { detailOf } from '../../canvas/ViewDetail.vue';
import ZoomToolbar, { fitZoom } from '../../canvas/ZoomToolbar.vue';
import { ARTBOARD } from '../../layout';
import { detail, openDetail } from '../../shell/detail';
import { modeSwitchPlacement } from '../../shell/modes';
import ModeSwitch from '../../shell/ModeSwitch.vue';
import type { ShellCanvas } from '../../shell/TopBar.vue';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import { computeCanvasLayout } from '../../layout';
import type { CanvasLayoutInput, TextMeasurer } from '../../layout';

const props = defineProps<{
    canvas: { id: number; name: string; archived: boolean };
    canWrite: boolean;
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
    modalities: ModalityMark[];
    layouts?: CanvasLayoutInput['layouts'];
}>();

const page = usePage<{ auth: { role: string } }>();
const shellCanvas = computed(() => page.props.canvas as unknown as ShellCanvas);
modeSwitchPlacement.inPage = true;
onBeforeUnmount(() => (modeSwitchPlacement.inPage = false));

const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));
const drawn = computed(() => (measurer.value ? computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements, layouts: props.layouts }, measurer.value) : null));
const selectedId = ref<string | null>(null);
const content = computed(() => (selectedId.value ? detailOf(selectedId.value, { areas: props.layout.areas, elements: props.elements, statements: props.statements, modalities: props.modalities }) : null));
const kickerOf = (id: string): string => {
    const element = props.elements.find((candidate) => candidate.id === id);
    if (!element || !content.value) return '';
    const path = [props.layout.areas.find((candidate) => candidate.key === element.area)?.label ?? ''];
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
const scroller = ref<HTMLElement>();
const fit = (): void => {
    if (scroller.value && drawn.value) zoom.value = fitZoom({ width: scroller.value.clientWidth, height: scroller.value.clientHeight }, { width: ARTBOARD.width, height: contentHeight(drawn.value) });
};
</script>

<template>
    <Head :title="canvas.name" />
    <header class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <h1 class="font-display text-[22px] font-semibold">{{ canvas.name }}</h1>
        <ModeSwitch :role="page.props.auth.role" :canvas="shellCanvas" :url="page.url" />
    </header>
    <OverflowNotice v-if="drawn" :count="drawn.beyondArtboardCount" :administrator="page.props.auth.role === 'administrator'" />
    <ZoomToolbar v-if="drawn" v-model:zoom="zoom" class="mt-3" @fit="fit" />
    <div ref="scroller" class="mt-3 h-[calc(100vh-14rem)] overflow-auto" tabindex="-1" data-scroller>
        <CanvasView v-if="drawn" :drawn="drawn" :areas="layout.areas" :elements="elements" :statements="statements" :modalities="modalities" :zoom="zoom" :selected-id="selectedId" @select="select" />
    </div>
    <ViewDetail v-if="content && detail.open" :content="content" />
</template>
