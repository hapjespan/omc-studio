<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import UndoMessage from '../../components/list/UndoMessage.vue';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import type { TextMeasurer } from '../../layout';
import type { TargetRow } from '../../allocation/types';
import { routes } from '../../routes';
import { t } from '../../i18n';
import EntryTree from './EntryTree.vue';
import type { AreaKey, ElementKind, ElementLayout } from '../../layout/types';
import AppButton from '../../components/ui/AppButton.vue';
import Icon from '../../components/ui/Icon.vue';
import { detail, discardDetail, openDetail, requestCloseDetail } from '../../shell/detail';
import { modeSwitchPlacement } from '../../shell/modes';
import ModeSwitch from '../../shell/ModeSwitch.vue';
import type { ShellCanvas } from '../../shell/TopBar.vue';
import AddDomain from './AddDomain.vue';
import DirectionEntry from './DirectionEntry.vue';
import ElementPanel from './ElementPanel.vue';
import ArchivedToggle from './ArchivedToggle.vue';
import ListAreaEntry from './ListAreaEntry.vue';
import QuickEntry from './QuickEntry.vue';
import EntrySearch from './EntrySearch.vue';
import Warnings from './Warnings.vue';
import { buildRows, createdElement, elementPath, entryWarnings, filterByName, kindLabelKey, type ElementCost, type EntryAreaInfo, type EntryElement } from './entry';
import SaveTemplateButton from './SaveTemplateButton.vue';
import ImportStructure from './ImportStructure.vue';

interface EntryArea {
    key: string;
    kind: string;
    label: string;
    summary: string;
}

const props = defineProps<{ areas: EntryArea[]; selected: string; elements: EntryElement[]; modalities: { key: string; letter: string }[]; layouts?: Record<string, ElementLayout>; icons: string[]; year: number | null; costs: Record<string, ElementCost>; targets: TargetRow[]; statements: { mission: string | null; vision: string | null; goals: { id: number; title: string; body: string }[] } }>();
const canvas = computed(() => usePage().props.canvas as { id: number; name: string });
const current = ref(props.selected);
const area = computed(() => props.areas.find(({ key }) => key === current.value) ?? props.areas[0]);
const selectedId = ref<string | null>(null);
const saved = ref(false);
const adding = ref(false);
const page = usePage<{ auth: { role: string } }>();
const auth = computed(() => page.props.auth);
const shellCanvas = computed(() => page.props.canvas as unknown as ShellCanvas);
modeSwitchPlacement.inPage = true;
onBeforeUnmount(() => (modeSwitchPlacement.inPage = false));
const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer().catch(() => null)));
const undo = ref<{ token: string; name: string; refusal: string | null } | null>(null);
const areaInfo = computed(() => props.areas as unknown as EntryAreaInfo[]);
const labelOf = (key: string): string => props.areas.find((item) => item.key === key)?.label ?? key;
const selectedElement = computed(() => props.elements.find(({ id }) => id === selectedId.value));
const warnings = computed(() => (measurer.value ? entryWarnings(props.elements, areaInfo.value, area.value.key as AreaKey, measurer.value, props.layouts) : []));
const letters = computed(() => Object.fromEntries(props.modalities.map(({ key, letter }) => [key, letter])));
const search = ref('');
const showArchived = ref(false);
const restoreError = ref('');
const rows = computed(() => buildRows(filterByName(props.elements.filter((element) => showArchived.value || !element.archived), search.value), area.value.key as AreaKey));

const listOf = (key: string): boolean => props.areas.find((item) => item.key === key)?.kind === 'list';

const kickerOf = (element?: EntryElement): string => (element ? `${t(kindLabelKey(element.kind, element.area))} · ${elementPath(element, props.elements, labelOf(element.area)).split(' > ').slice(0, -1).join(' › ')}` : '');

watch(() => kickerOf(selectedElement.value), (kicker) => {
    if (detail.open) detail.kicker = kicker;
});

function select(id: string, opener: HTMLElement | null): void {
    const element = props.elements.find((candidate) => candidate.id === id);
    const wasOpen = detail.open;
    selectedId.value = id;
    saved.value = false;
    openDetail(element?.name ?? '', document.getElementById(`row-${id}`) ?? opener, kickerOf(element));
    if (wasOpen) void nextTick(() => document.getElementById('detail-title')?.focus());
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
            document.getElementById('add-domain-open')?.focus();
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

watch(current, () => {
    search.value = '';
    adding.value = false;
    saved.value = false;
    if (detail.open) requestCloseDetail();
});

function closeAdding(): void {
    adding.value = false;
    void nextTick(() => document.getElementById('add-domain-open')?.focus());
}

watch(() => detail.dirty, (dirty) => {
    if (dirty) saved.value = false;
});

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
    <header class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <h1 class="font-display text-[22px] font-semibold">{{ canvas.name }}</h1>
        <ModeSwitch :role="auth.role" :canvas="shellCanvas" :url="page.url" />
        <p role="status" class="inline-flex items-center gap-1 text-[13px] font-medium text-success"><template v-if="saved && area.kind !== 'text'"><Icon name="check" />{{ t('entry.saved') }}</template></p>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <QuickEntry v-if="area.kind !== 'text'" :key="area.key" :canvas-id="canvas.id" :area="area.key" :area-label="area.label" />
            <SaveTemplateButton />
        </div>
    </header>
    <ImportStructure :canvas-id="canvas.id" />
    <div class="mt-4 flex items-start gap-4">
        <nav :aria-label="t('entry.areas')" class="w-[216px] shrink-0">
            <h2 class="mb-2 font-display text-[13px] font-semibold">{{ t('entry_design.areas_heading') }}</h2>
            <ul class="flex flex-col gap-2">
                <li v-for="item in areas" :key="item.key">
                    <button type="button" :aria-current="item.key === area.key ? 'true' : undefined" class="min-h-12 w-full rounded-panel border border-hairline bg-white px-3 py-1.5 text-left aria-[current=true]:selected-ring" @click="current = item.key">
                        <span class="block text-[13px] font-semibold">{{ item.label }}</span>
                        <span class="block text-[12.5px] text-muted">{{ item.summary }}</span>
                    </button>
                </li>
            </ul>
            <p class="mt-3 text-[12.5px] text-muted">{{ t('entry_design.areas_help') }}</p>
        </nav>
        <section :aria-label="area.label" class="min-w-0 flex-1 rounded-panel border border-hairline bg-surface p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-base font-semibold">{{ area.label }}</h2>
                <div v-if="area.kind !== 'text'" class="flex items-center gap-2">
                    <EntrySearch v-model="search" />
                    <AppButton id="add-domain-open" variant="primary" icon="plus" aria-controls="add-domain-form" :aria-expanded="adding" @click="adding = !adding">{{ t(`areas.${area.key}.level_1`) }}</AppButton>
                </div>
            </div>
            <DirectionEntry v-if="area.kind === 'text'" :canvas-id="canvas.id" :statements="statements" />
            <template v-else>
                <div id="quick-entry-slot" />
                <UndoMessage v-if="undo" :message="t('entry.deleted', { name: undo.name })" :refusal="undo.refusal" @undo="undoDeletion" @close="undo = null" />
                <AddDomain v-if="adding" :canvas-id="canvas.id" :area="area.key" :kind-label="t(`areas.${area.key}.level_1`)" @added="added('domain', $event, null)" @close="closeAdding" />
                <ArchivedToggle v-model="showArchived" />
                <p v-if="restoreError" role="alert" class="mt-2 text-[12.5px] font-medium text-danger">{{ restoreError }}</p>
                <Warnings v-if="!selectedElement" :warnings="warnings" @select="select" />
                <ListAreaEntry v-if="area.kind === 'list'" :rows="rows" :selected-id="selectedId" :label="t('entry.tree', { area: area.label })" :canvas-id="canvas.id" :item-label="t(`areas.${area.key}.level_2`)" @select="select" @restore="restore" />
                <EntryTree v-else :rows="rows" :letters="letters" :selected-id="selectedId" :label="t('entry.tree', { area: area.label })" :canvas-id="canvas.id" :item-label="t(`areas.${area.key}.level_2`)" :clusters="area.kind === 'activities'" @select="select" @restore="restore" />
                <p class="mt-4 text-[12.5px] text-secondary">{{ t('entry_design.order_help') }}</p>
            </template>
                <ElementPanel v-if="selectedElement" :key="selectedElement.id" :canvas-id="canvas.id" :element="selectedElement" :elements="elements" :areas="areaInfo" :list="listOf(selectedElement.area)" :icons="icons" :warnings="warnings" :year="year" :cost="costs[selectedElement.id]" :targets="targets" :gone="gone" @select="select" @saved="saved = true" @changed="saved = false" />
        </section>
    </div>
</template>
