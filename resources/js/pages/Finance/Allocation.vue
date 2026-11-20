<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import UndoMessage from '../../components/list/UndoMessage.vue';
import { useUndoFlash } from '../../components/list/undoFlash';
import Dialog from '../../components/overlays/Dialog.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { activitiesOf, formatPercentage, remaining, restToLine, splitEvenly, splitOverDomainActivities } from '../../allocation/helpers';
import { indexTargets, type CostRecordRow, type DraftLine, type TargetRow } from '../../allocation/types';
import { t } from '../../i18n';
import { routes } from '../../routes';
import AllocationEditor from './AllocationEditor.vue';
import AllocationQuick from './AllocationQuick.vue';
import AllocationSummary from './AllocationSummary.vue';
import ImportAllocations from './ImportAllocations.vue';
import CostRecordStatusList from './CostRecordStatusList.vue';
import TargetTree from './TargetTree.vue';

const props = defineProps<{ canvas: { id: number; name: string; archived: boolean }; year: number | null; finalised: boolean; costRecords: CostRecordRow[]; chosen: string | null; set: { elementId: string; basisPoints: number; position: number }[]; targets: TargetRow[]; canvasTotalCents: number | null; figuresAvailable: boolean; areaTotals: Record<string, number> | null }>();

const page = usePage();
const stored = (): DraftLine[] => [...props.set].sort((a, b) => a.position - b.position).map((line) => ({ elementId: line.elementId, basisPoints: line.basisPoints }));
const draft = ref<DraftLine[]>(stored());
const info = computed(() => indexTargets(props.targets));
const record = computed(() => props.costRecords.find((row) => row.id === props.chosen) ?? null);
const writable = computed(() => record.value !== null && props.year !== null && !props.finalised && !props.canvas.archived);
const dirty = computed(() => JSON.stringify(draft.value) !== JSON.stringify(stored()));
const left = computed(() => remaining(draft.value.map((line) => line.basisPoints)));
const total = computed(() => draft.value.reduce((sum, line) => sum + line.basisPoints, 0));
const canSave = computed(() => writable.value && dirty.value && draft.value.length > 0 && left.value === 0);
const errors = computed(() => (page.props.errors as Record<string, string>).rule ? { rule: (page.props.errors as Record<string, string>).rule } : {});
const undo = useUndoFlash();
const clearOpen = ref(false);
const leaveOpen = ref(false);
const lastVisit = ref<{ url: URL; method: string } | null>(null);
let bypass = false;

watch(() => [props.chosen, props.set], () => (draft.value = stored()));
watch(() => (page.props.flash as { status?: string | null } | undefined)?.status, (status) => status && announce(status));

const removeBefore = router.on('before', (event) => {
    if (!dirty.value || bypass) return;
    event.preventDefault();
    lastVisit.value = { url: event.detail.visit.url, method: event.detail.visit.method };
    leaveOpen.value = true;
});
const warn = (event: BeforeUnloadEvent): void => {
    if (dirty.value) event.preventDefault();
};
window.addEventListener('beforeunload', warn);
onBeforeUnmount(() => {
    removeBefore();
    window.removeEventListener('beforeunload', warn);
});

function through(visit: () => void): void {
    bypass = true;
    try {
        visit();
    } finally {
        bypass = false;
    }
}

const add = async (id: string): Promise<void> => {
    if (!writable.value || !info.value[id] || draft.value.some((line) => line.elementId === id)) return;
    draft.value = [...draft.value, { elementId: id, basisPoints: Math.max(0, left.value) }];
    announce(t('allocation_page.add', { target: info.value[id].path }));
    await nextTick();
    document.getElementById(`allocation-line-${draft.value.length - 1}`)?.focus();
};
const change = (index: number, basisPoints: number): void => void (draft.value = draft.value.map((line, i) => (i === index ? { ...line, basisPoints } : line)));
const remove = (index: number): void => void (draft.value = draft.value.filter((_, i) => i !== index));
const rest = (index: number): void => {
    const next = restToLine(draft.value.map((line) => line.basisPoints), index);
    draft.value = draft.value.map((line, i) => ({ ...line, basisPoints: next[i] }));
};
const even = (): void => {
    const shares = splitEvenly(draft.value.length);
    draft.value = draft.value.map((line, i) => ({ ...line, basisPoints: shares[i] }));
};
const overDomain = (id: string): void => {
    const domain = props.targets.find((row) => String(row.id) === id);
    if (domain && activitiesOf(domain).length > 0) draft.value = splitOverDomainActivities(domain).map((line) => ({ elementId: String(line.id), basisPoints: line.basisPoints }));
};
const choose = (id: string): void => router.get(routes.allocation(props.canvas.id, props.year ?? undefined, id), {}, { preserveScroll: true });
const save = (): void => {
    if (!canSave.value || !record.value || props.year === null) return;
    through(() => router.put(routes.allocationSet(props.canvas.id, props.year as number, record.value!.id), { lines: draft.value.map((line) => ({ element_id: line.elementId, basis_points: line.basisPoints })) }, { preserveScroll: true, preserveState: true }));
};
const discard = (): void => void (draft.value = stored());
const clear = (): void => {
    if (!record.value || props.year === null) return;
    clearOpen.value = false;
    undo.start();
    through(() => router.delete(routes.allocationSet(props.canvas.id, props.year as number, record.value!.id), { preserveScroll: true, preserveState: true }));
};
const leave = (): void => {
    const visit = lastVisit.value;
    leaveOpen.value = false;
    discard();
    if (visit) through(() => router.visit(visit.url.href, { method: visit.method as 'get' }));
};
const draftText = computed(() => (dirty.value ? formatPercentage(total.value) : null));
const canClear = computed(() => writable.value && record.value?.status === 'allocated');
</script>

<template>
    <Head :title="t('allocation_page.title')" />
    <h1 class="sr-only">{{ t('allocation_page.title') }}: {{ canvas.name }}</h1>
    <p v-if="finalised" role="status" class="mb-3 text-[13px] text-warning">{{ t('allocation_page.finalised', { year: year ?? '' }) }}</p>
    <p v-if="year === null" role="status" class="mb-3 text-[13px]">{{ t('allocation_page.no_year') }}</p>
    <div v-if="errors.rule" role="alert" class="mb-3"><ErrorSummary :errors="errors" /></div>
    <div v-if="undo.state.value" class="mb-3"><UndoMessage :message="undo.state.value.message" :refusal="undo.state.value.refusal" @undo="undo.run" @close="undo.dismiss" /></div>
    <div class="grid items-start gap-5 lg:grid-cols-[346px_minmax(0,1fr)_380px]">
        <div class="min-w-0 space-y-4">
            <CostRecordStatusList :records="costRecords" :chosen="chosen" :year="year" :draft="draftText" @choose="choose" />
            <ImportAllocations v-if="!canvas.archived" :canvas-id="canvas.id" />
        </div>
        <section role="region" aria-labelledby="allocation-middle" class="min-w-0">
            <template v-if="record">
                <AllocationSummary :record="record" :year="year" :lines="draft" :draft="draftText">
                    <AllocationQuick :count="draft.length" :domains="targets" :disabled="!writable" @even="even" @domain="overDomain" />
                </AllocationSummary>
                <div class="mt-3">
                    <AllocationEditor :lines="draft" :info="info" :budget-cents="record.budgetCents" :disabled="!writable" @change="change" @remove="remove" @rest="rest" @drop="add" />
                </div>
                <div class="mt-3 flex items-start gap-3">
                    <AppButton :variant="canSave ? 'primary' : 'secondary'" :icon="canSave ? undefined : 'lock'" :disabled="!canSave" aria-describedby="allocation-save-hint" @click="save">{{ t('allocation_page.save') }}</AppButton>
                    <p v-if="dirty && !canSave && writable" id="allocation-save-hint" class="min-w-0 flex-1 pt-1.5 text-[12.5px] text-secondary">{{ t('allocation_page.save_blocked') }}</p>
                    <AppButton v-if="dirty" class="ml-auto shrink-0" @click="discard">{{ t('allocation_page.discard') }}</AppButton>
                </div>
                <AppButton class="mt-3" variant="danger" :disabled="!canClear" @click="clearOpen = true">{{ t('allocation_page.clear') }}</AppButton>
            </template>
            <p v-else id="allocation-middle" class="rounded-panel border border-hairline bg-white p-5 text-[13px]">{{ t('allocation_page.pick') }}</p>
        </section>
        <TargetTree :targets="targets" :in-set="draft.map((line) => line.elementId)" :disabled="!writable" :figures-available="figuresAvailable" :year="year" :area-totals="areaTotals" @add="add" />
    </div>
    <Dialog v-model:open="clearOpen" :title="t('allocation_page.clear_title')" :description="t('allocation_page.clear_text', { name: record?.name ?? '', year: year ?? '' })">
        <div class="mt-3 flex gap-2">
            <AppButton @click="clearOpen = false">{{ t('list.cancel') }}</AppButton>
            <AppButton variant="danger" @click="clear">{{ t('allocation_page.clear_confirm') }}</AppButton>
        </div>
    </Dialog>
    <Dialog v-model:open="leaveOpen" :title="t('allocation_page.leave_title')" :description="t('allocation_page.leave_text')">
        <div class="mt-3 flex gap-2">
            <AppButton @click="leaveOpen = false">{{ t('allocation_page.leave_stay') }}</AppButton>
            <AppButton variant="danger" @click="leave">{{ t('allocation_page.leave_go') }}</AppButton>
        </div>
    </Dialog>
</template>
