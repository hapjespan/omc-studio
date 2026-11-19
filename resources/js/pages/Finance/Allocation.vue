<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import UndoMessage from '../../components/list/UndoMessage.vue';
import { useUndoFlash } from '../../components/list/undoFlash';
import Dialog from '../../components/overlays/Dialog.vue';
import { activitiesOf, formatPercentage, remaining, restToLine, splitEvenly, splitOverDomainActivities } from '../../allocation/helpers';
import { indexTargets, type CostRecordRow, type DraftLine, type TargetRow } from '../../allocation/types';
import { t } from '../../i18n';
import { routes } from '../../routes';
import AllocationEditor from './AllocationEditor.vue';
import AllocationQuick from './AllocationQuick.vue';
import CostRecordStatusList from './CostRecordStatusList.vue';
import TargetTree from './TargetTree.vue';

const props = defineProps<{ canvas: { id: number; name: string; archived: boolean }; year: number | null; finalised: boolean; costRecords: CostRecordRow[]; chosen: string | null; set: { elementId: string; basisPoints: number; position: number }[]; targets: TargetRow[]; canvasTotalCents: number | null; figuresAvailable: boolean }>();

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

const add = (id: string): void => {
    if (!writable.value || !info.value[id] || draft.value.some((line) => line.elementId === id)) return;
    draft.value = [...draft.value, { elementId: id, basisPoints: Math.max(0, left.value) }].filter((line) => line.basisPoints > 0 || line.elementId === id);
    announce(t('allocation_page.add', { target: info.value[id].path }));
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
const statusText = computed(() => (record.value ? t(`allocation_page.status_${record.value.status}`) : ''));
const canClear = computed(() => writable.value && record.value?.status === 'allocated');
</script>

<template>
    <Head :title="t('allocation_page.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('allocation_page.title') }}: {{ canvas.name }}</h1>
    <p v-if="finalised" role="status" class="mt-2 text-[13px] text-[#8A5A00]">{{ t('allocation_page.finalised', { year: year ?? '' }) }}</p>
    <p v-if="year === null" role="status" class="mt-2 text-[13px]">{{ t('allocation_page.no_year') }}</p>
    <div v-if="errors.rule" role="alert" class="mt-3"><ErrorSummary :errors="errors" /></div>
    <div v-if="undo.state.value" class="mt-3"><UndoMessage :message="undo.state.value.message" :refusal="undo.state.value.refusal" @undo="undo.run" @close="undo.dismiss" /></div>
    <div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1fr)]">
        <CostRecordStatusList :records="costRecords" :chosen="chosen" @choose="choose" />
        <section aria-labelledby="allocation-middle" class="min-w-0">
            <template v-if="record">
                <h2 id="allocation-middle" class="text-[15px] font-semibold">{{ t('allocation_page.lines', { name: record.name }) }}</h2>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-[13px]">
                    <span class="flex items-center gap-1.5"><span aria-hidden="true" class="inline-block h-2.5 w-2.5 rounded-full" :class="record.status === 'allocated' ? 'bg-[#1F7A3D]' : 'bg-[#8A5A00]'" />{{ statusText }}</span>
                    <span v-if="dirty" class="font-semibold text-[#8A5A00]" data-testid="draft">{{ t('allocation_page.draft', { percentage: formatPercentage(total) }) }}</span>
                </p>
                <div class="mt-2 flex flex-wrap items-start gap-2">
                    <AllocationQuick :count="draft.length" :domains="targets" :disabled="!writable" @even="even" @domain="overDomain" />
                    <button v-if="dirty" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="discard">{{ t('allocation_page.discard') }}</button>
                    <button type="button" :disabled="!canClear" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E] disabled:opacity-60" @click="clearOpen = true">{{ t('allocation_page.clear') }}</button>
                </div>
                <div class="mt-3">
                    <AllocationEditor :lines="draft" :info="info" :budget-cents="record.budgetCents" :disabled="!writable" @change="change" @remove="remove" @rest="rest" @drop="add" />
                </div>
                <div class="mt-3">
                    <button type="button" :disabled="!canSave" aria-describedby="allocation-save-hint" class="rounded-md border border-[#171B21] bg-[#171B21] px-3 py-1.5 text-[13px] font-semibold text-white disabled:opacity-60" @click="save">{{ t('allocation_page.save') }}</button>
                    <p v-if="dirty && !canSave && writable" id="allocation-save-hint" class="mt-1 text-[13px]">{{ t('allocation_page.save_blocked') }}</p>
                </div>
            </template>
            <p v-else id="allocation-middle" class="text-[13px]">{{ t('allocation_page.pick') }}</p>
        </section>
        <TargetTree :targets="targets" :in-set="draft.map((line) => line.elementId)" :disabled="!writable" :figures-available="figuresAvailable" @add="add" />
    </div>
    <Dialog v-model:open="clearOpen" :title="t('allocation_page.clear_title')" :description="t('allocation_page.clear_text', { name: record?.name ?? '', year: year ?? '' })">
        <div class="mt-3 flex gap-2">
            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="clearOpen = false">{{ t('list.cancel') }}</button>
            <button type="button" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E]" @click="clear">{{ t('allocation_page.clear_confirm') }}</button>
        </div>
    </Dialog>
    <Dialog v-model:open="leaveOpen" :title="t('allocation_page.leave_title')" :description="t('allocation_page.leave_text')">
        <div class="mt-3 flex gap-2">
            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="leaveOpen = false">{{ t('allocation_page.leave_stay') }}</button>
            <button type="button" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E]" @click="leave">{{ t('allocation_page.leave_go') }}</button>
        </div>
    </Dialog>
</template>
