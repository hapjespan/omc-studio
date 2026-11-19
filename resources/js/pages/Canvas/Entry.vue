<script setup lang="ts">
import { computed, nextTick, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import UndoMessage from '../../components/list/UndoMessage.vue';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import type { TextMeasurer } from '../../layout';
import { routes } from '../../routes';
import { t } from '../../i18n';
import EntryTree from './EntryTree.vue';
import type { AreaKey, ElementKind } from '../../layout/types';
import { detail, discardDetail, openDetail } from '../../shell/detail';
import AddDomain from './AddDomain.vue';
import DirectionEntry from './DirectionEntry.vue';
import ElementPanel from './ElementPanel.vue';
import ArchivedToggle from './ArchivedToggle.vue';
import ListAreaEntry from './ListAreaEntry.vue';
import QuickEntry from './QuickEntry.vue';
import EntrySearch from './EntrySearch.vue';
import Warnings from './Warnings.vue';
import { buildRows, createdElement, elementPath, entryWarnings, filterByName, type EntryAreaInfo, type EntryElement } from './entry';
import SaveTemplateButton from './SaveTemplateButton.vue';

interface EntryArea {
    key: string;
    kind: string;
    label: string;
    summary: string;
}

const props = defineProps<{ areas: EntryArea[]; selected: string; elements: EntryElement[]; modalities: { key: string; letter: string }[]; icons: string[]; statements: { mission: string | null; vision: string | null; goals: { id: number; title: string; body: string }[] } }>();
const canvas = computed(() => usePage().props.canvas as { id: number; name: string });
const current = ref(props.selected);
const area = computed(() => props.areas.find(({ key }) => key === current.value) ?? props.areas[0]);
const selectedId = ref<string | null>(null);
const saved = ref(false);
const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer().catch(() => null)));
const undo = ref<{ token: string; name: string; refusal: string | null } | null>(null);
const areaInfo = computed(() => props.areas as unknown as EntryAreaInfo[]);
const labelOf = (key: string): string => props.areas.find((item) => item.key === key)?.label ?? key;
const selectedElement = computed(() => props.elements.find(({ id }) => id === selectedId.value));
const warnings = computed(() => (measurer.value ? entryWarnings(props.elements, areaInfo.value, area.value.key as AreaKey, measurer.value) : []));
const letters = computed(() => Object.fromEntries(props.modalities.map(({ key, letter }) => [key, letter])));
const search = ref('');
const showArchived = ref(false);
const restoreError = ref('');
const rows = computed(() => buildRows(filterByName(props.elements.filter((element) => showArchived.value || !element.archived), search.value), area.value.key as AreaKey));

function select(id: string, opener: HTMLElement | null): void {
    selectedId.value = id;
    saved.value = false;
    openDetail(props.elements.find((element) => element.id === id)?.name ?? '', opener);
}

function gone(kind: 'archived' | 'deleted', name: string): void {
    discardDetail();
    const token = (usePage().props.flash as { undoToken?: string | null } | undefined)?.undoToken;
    if (kind === 'deleted' && token) undo.value = { token, name, refusal: null };
}

function undoDeletion(): void {
    const current = undo.value;
    if (!current) return;
    router.post(routes.undo(current.token), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            undo.value = null;
            void announce(t('entry.undone', { name: current.name }));
            await nextTick();
            document.getElementById('add-domain')?.focus();
        },
        onError: (errors) => (current.refusal = errors.rule ?? ''),
    });
}

function restore(element: EntryElement): void {
    restoreError.value = '';
    router.post(routes.elementRestore(canvas.value.id, element.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => void announce(t('entry.restored', { name: element.name })),
        onError: (errors) => (restoreError.value = errors.rule ?? ''),
    });
}

watch(current, () => (search.value = ''));

watch(() => detail.open, (open) => {
    if (!open) selectedId.value = null;
});

async function added(kind: ElementKind, name: string, parent: string | null): Promise<void> {
    await nextTick();
    const created = createdElement(props.elements, area.value.key as AreaKey, kind, name, parent);
    if (created) document.getElementById(`add-line-${created.id}`)?.focus();
}
</script>

<template>
    <Head :title="canvas.name" />
    <header class="flex items-center gap-4">
        <p role="status" class="order-last ml-auto text-[13px] font-semibold">{{ saved ? t('entry.saved') : '' }}</p>
        <h1 class="text-[20px] font-semibold">{{ canvas.name }}</h1>
        <SaveTemplateButton />
    </header>
    <div class="mt-4 flex gap-4">
        <nav :aria-label="t('entry.areas')" class="w-[216px] shrink-0">
            <ul class="flex flex-col gap-2">
                <li v-for="item in areas" :key="item.key">
                    <button type="button" :aria-current="item.key === area.key ? 'true' : undefined" class="w-full rounded-[10px] border border-edge px-3 py-2 text-left aria-[current=true]:border-2 aria-[current=true]:border-ink" @click="current = item.key">
                        <span class="block text-[13px] font-semibold">{{ item.label }}</span>
                        <span class="block text-[12.5px] text-secondary">{{ item.summary }}</span>
                    </button>
                </li>
            </ul>
        </nav>
        <section :aria-label="area.label" class="min-w-0 flex-1">
            <h2 class="font-display text-base font-semibold">{{ area.label }}</h2>
            <DirectionEntry v-if="area.kind === 'text'" :canvas-id="canvas.id" :statements="statements" />
            <template v-if="area.kind !== 'text'">
            <QuickEntry :key="area.key" :canvas-id="canvas.id" :area="area.key" :area-label="area.label" />
            <UndoMessage v-if="undo" :message="t('entry.deleted', { name: undo.name })" :refusal="undo.refusal" @undo="undoDeletion" @close="undo = null" />
            <AddDomain :canvas-id="canvas.id" :area="area.key" :kind-label="t(`areas.${area.key}.level_1`)" @added="added('domain', $event, null)" />
            <EntrySearch v-model="search" />
            <ArchivedToggle v-model="showArchived" />
            <p v-if="restoreError" role="alert" class="mt-2 text-[12.5px] font-medium text-danger">{{ restoreError }}</p>
            <Warnings :warnings="warnings" @select="select" />
            <ListAreaEntry v-if="area.kind === 'list'" :rows="rows" :selected-id="selectedId" :label="t('entry.tree', { area: area.label })" :canvas-id="canvas.id" :item-label="t(`areas.${area.key}.level_2`)" @select="select" @restore="restore" />
            <EntryTree v-else :rows="rows" :letters="letters" :selected-id="selectedId" :label="t('entry.tree', { area: area.label })" :canvas-id="canvas.id" :item-label="t(`areas.${area.key}.level_2`)" :clusters="area.kind === 'activities'" @select="select" @restore="restore" />
            <ElementPanel v-if="selectedElement" :key="selectedElement.id" :canvas-id="canvas.id" :element="selectedElement" :elements="elements" :areas="areaInfo" :path="elementPath(selectedElement, elements, labelOf(selectedElement.area))" :list="area.kind === 'list'" :icons="icons" @saved="saved = true" @changed="saved = false" :gone="gone" />
            </template>
        </section>
    </div>
</template>
