<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import TextArea from '../../components/fields/TextArea.vue';
import TextField from '../../components/fields/TextField.vue';
import ConfirmDelete from '../../components/list/ConfirmDelete.vue';
import type { ListRow } from '../../components/list/ListView.vue';
import { plural, t } from '../../i18n';
import { routes } from '../../routes';

const props = defineProps<{ row: ListRow; year: number }>();
const emit = defineEmits<{ dirty: [value: boolean] }>();

const page = usePage();
const errors = computed(() => ((page.props.errors as Record<string, unknown>).figures ?? {}) as Record<string, string>);
const figures = computed(() => props.row.figures as Record<string, string>);
const draft = reactive({ ...figures.value });
const initial = JSON.stringify(draft);
const busy = ref(false);
const deleting = ref(false);
const archived = computed(() => Boolean(props.row.archived_at));
const hasFigures = computed(() => Boolean(props.row.has_figures));

const basisPoints = (value: string): number => {
    const parsed = Number(value.replace(',', '.'));
    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
};
const undetermined = computed(() => {
    const share = (10000 - basisPoints(draft.run) - basisPoints(draft.change)) / 100;
    return `${share.toFixed(2).replace('.', ',')}%`;
});
const touch = (): void => emit('dirty', JSON.stringify(draft) !== initial);
const hint = (label: string, example: string): string => `${label} (${t('yearly_figures.example', { example })})`;
const optionalHint = (label: string, example: string): string => `${label} (${t('form.optional')}, ${t('yearly_figures.example', { example })})`;

watch(() => props.row.id, () => Object.assign(draft, figures.value));

function save(): void {
    router.put(routes.costRecordYear(props.row.id, props.year), { ...draft }, { errorBag: 'figures', preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false), onError: () => nextTick(focusFirstInvalid) });
}

const consequence = (): string | null => (Number(props.row.year_set_count) > 0 ? plural('yearly_figures.delete_consequence', Number(props.row.year_set_count)) : null);
const remove = (): void => router.delete(routes.costRecordYear(props.row.id, props.year), { errorBag: 'figures', preserveScroll: true, preserveState: true });
</script>

<template>
    <section :aria-label="t('yearly_figures.title', { year })" class="mt-4 border-t border-[#D5D9DF] pt-3">
        <h3 class="text-[14px] font-semibold">{{ t('yearly_figures.title', { year }) }}</h3>
        <div role="alert" class="mt-2"><ErrorSummary :errors="errors" prefix="figures-" /></div>
        <form novalidate class="mt-2 flex flex-col gap-3" @submit.prevent="save">
            <fieldset :disabled="archived" class="flex flex-col gap-3">
                <TextField id="figures-budget" v-model="draft.budget" :label="hint(t('fields.budget'), '1.234,56')" :error="errors.budget" @update:model-value="touch" />
                <TextField id="figures-forecast" v-model="draft.forecast" :label="optionalHint(t('fields.forecast'), '1.234,56')" :error="errors.forecast" @update:model-value="touch" />
                <TextField id="figures-run" v-model="draft.run" :label="optionalHint(t('fields.run'), '60')" :error="errors.run" @update:model-value="touch" />
                <TextField id="figures-change" v-model="draft.change" :label="optionalHint(t('fields.change'), '30')" :error="errors.change" @update:model-value="touch" />
                <p aria-live="polite" class="text-[13px]">{{ t('yearly_figures.undetermined', { share: undetermined }) }}</p>
                <TextField id="figures-fte" v-model="draft.fte" :label="optionalHint(t('fields.fte'), '1,5')" :error="errors.fte" @update:model-value="touch" />
                <TextField id="figures-reference_date" v-model="draft.reference_date" type="date" :label="`${t('fields.reference_date')} (${t('form.optional')})`" :error="errors.reference_date" @update:model-value="touch" />
                <TextArea id="figures-remark" v-model="draft.remark" :label="`${t('fields.remark')} (${t('form.optional')})`" :error="errors.remark" @update:model-value="touch" />
            </fieldset>
            <div v-if="!archived" class="flex gap-2">
                <button type="submit" :disabled="busy" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('yearly_figures.save') }}</button>
                <button v-if="hasFigures" type="button" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E]" @click="deleting = true">{{ t('yearly_figures.delete') }}</button>
            </div>
        </form>
        <ConfirmDelete v-if="deleting" v-model:open="deleting" :name="String(year)" :consequence="consequence()" @confirm="remove" />
    </section>
</template>
