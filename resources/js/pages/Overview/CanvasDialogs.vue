<script lang="ts">
export type DialogKind = 'create' | 'copy' | 'rename' | 'archive' | 'delete';

export interface DialogCanvas {
    id: number;
    name: string;
    organisationId: number;
    counts: { elements: number; statements: number } | null;
}

export const exactName = (typed: string, name: string): boolean => typed !== '' && typed === name;
</script>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import SelectField from '../../components/fields/SelectField.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import Dialog from '../../components/overlays/Dialog.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

const emit = defineEmits<{ done: [] }>();
const props = defineProps<{ organisations: { id: number; name: string }[]; templates: { id: number; name: string }[] }>();

const open = ref(false);
const kind = ref<DialogKind>('create');
const canvas = ref<DialogCanvas | null>(null);
const form = useForm({ name: '', organisation_id: '', template_id: '', confirmation: '' });

const organisationOptions = computed(() => props.organisations.map((organisation) => ({ value: String(organisation.id), label: organisation.name })));
const templateOptions = computed(() => props.templates.map((template) => ({ value: String(template.id), label: t('canvas_dialog.template_option', { name: template.name }) })));
const titles: Record<DialogKind, () => string> = {
    create: () => t('canvas_dialog.new_title'),
    copy: () => t('canvas_dialog.copy_title'),
    rename: () => t('canvas_dialog.rename_title'),
    archive: () => t('canvas_dialog.archive_title', { name: canvas.value?.name ?? '' }),
    delete: () => t('canvas_dialog.delete_title', { name: canvas.value?.name ?? '' }),
};
const description = computed(() => {
    if (kind.value === 'archive') return t('canvas_dialog.archive_description');
    if (kind.value !== 'delete' || !canvas.value?.counts) return undefined;
    const { elements, statements } = canvas.value.counts;

    return t('canvas_dialog.delete_description', { counts: [t('canvas_dialog.count_elements', { count: elements }), t('canvas_dialog.count_statements', { count: statements })].join(', ') });
});
const hasName = computed(() => kind.value === 'create' || kind.value === 'copy' || kind.value === 'rename');
const canSubmit = computed(() => !form.processing && (kind.value !== 'delete' || exactName(form.confirmation, canvas.value?.name ?? '')));
const labels: Record<DialogKind, string> = { create: 'create', copy: 'copy', rename: 'save', archive: 'archive', delete: 'delete' };

function show(next: DialogKind, target: DialogCanvas | null = null): void {
    kind.value = next;
    canvas.value = target;
    form.clearErrors();
    form.name = next === 'rename' ? (target?.name ?? '') : '';
    form.organisation_id = String(target?.organisationId ?? props.organisations[0]?.id ?? '');
    form.template_id = String(props.templates[0]?.id ?? '');
    form.confirmation = '';
    form.defaults();
    open.value = true;
}

function submit(): void {
    const id = canvas.value?.id ?? 0;
    const options = { preserveScroll: true, onSuccess: () => {
            open.value = false;
            emit('done');
        }, onError: () => nextTick(focusFirstInvalid) };
    if (kind.value === 'create') form.transform((data) => ({ source: 'template', name: data.name, organisation_id: data.organisation_id, template_id: data.template_id })).post(routes.canvases(), options);
    else if (kind.value === 'copy') form.transform((data) => ({ source: 'copy', name: data.name, organisation_id: data.organisation_id, canvas_id: id })).post(routes.canvases(), options);
    else if (kind.value === 'rename') form.transform((data) => ({ name: data.name })).patch(routes.canvas(id), options);
    else if (kind.value === 'archive') form.transform(() => ({})).post(routes.canvasArchive(id), options);
    else form.transform((data) => ({ confirmation: data.confirmation })).delete(routes.canvas(id), options);
}

defineExpose({ show });
</script>

<template>
    <Dialog v-model:open="open" :title="titles[kind]()" :description="description" :dirty="form.isDirty && kind !== 'delete'">
        <form novalidate class="flex flex-col gap-3" @submit.prevent="submit">
            <div role="alert"><ErrorSummary :errors="form.errors" /></div>
            <TextField v-if="hasName" id="name" v-model="form.name" :label="t('fields.name')" :error="form.errors.name" autocomplete="off" />
            <SelectField v-if="kind === 'create' || kind === 'copy'" id="organisation_id" v-model="form.organisation_id" :label="t('canvas_dialog.organisation')" :options="organisationOptions" :error="form.errors.organisation_id" />
            <SelectField v-if="kind === 'create'" id="template_id" v-model="form.template_id" :label="t('canvas_dialog.start')" :options="templateOptions" :error="form.errors.template_id" />
            <TextField v-if="kind === 'delete'" id="confirmation" v-model="form.confirmation" :label="t('canvas_dialog.confirmation')" :hint="t('canvas_dialog.confirmation_hint', { name: canvas?.name ?? '' })" :error="form.errors.confirmation" autocomplete="off" />
            <div class="flex gap-2">
                <button type="button" class="inline-flex h-control items-center justify-center rounded-control border border-edge bg-white px-3 text-[13px] font-medium" @click="open = false">{{ t('canvas_dialog.cancel') }}</button>
                <button type="submit" :disabled="!canSubmit" class="rounded-md border px-3 py-1.5 text-[13px] font-semibold disabled:opacity-60" :class="kind === 'delete' ? 'border-danger-edge text-danger' : 'border-edge'">{{ t(`canvas_dialog.${labels[kind]}`) }}</button>
            </div>
        </form>
    </Dialog>
</template>
