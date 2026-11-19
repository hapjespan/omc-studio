<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import SelectField from '../../components/fields/SelectField.vue';
import TextArea from '../../components/fields/TextArea.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import Dialog from '../../components/overlays/Dialog.vue';
import ModalityButtons from '../../components/ModalityButtons.vue';
import { NO_MODALITY } from '../../components/modality';
import { t } from '../../i18n';
import { routes } from '../../routes';
import { detail } from '../../shell/detail';
import { AREA_PREFIX, parentOptions, parentValue, type EntryAreaInfo, type EntryElement } from './entry';
import ElementActions from './ElementActions.vue';
import ParentSelect from './ParentSelect.vue';

const props = defineProps<{ canvasId: number; element: EntryElement; elements: EntryElement[]; areas: EntryAreaInfo[]; path: string; list: boolean; icons: string[]; gone: (kind: 'archived' | 'deleted', name: string) => void }>();
const emit = defineEmits<{ saved: []; changed: [] }>();
const form = useForm({ name: props.element.name, description: props.element.description ?? '', icon: props.element.icon ?? '', parent: parentValue(props.element), modality: props.element.modality ?? NO_MODALITY, rationale: props.element.rationale ?? '', confirm_rationale_clear: false });
const confirming = ref(false);
const options = computed(() => parentOptions(props.element, props.elements, props.areas));
const errors = computed(() => form.errors as Record<string, string>);
const moving = computed(() => form.parent !== parentValue(props.element));
const modalityChanged = computed(() => form.modality !== (props.element.modality ?? NO_MODALITY));
const nameError = computed(() => errors.value.name ?? (moving.value || modalityChanged.value ? undefined : errors.value.rule));
const modalityError = computed(() => (!moving.value && modalityChanged.value ? errors.value.rule : undefined));
const parentError = computed(() => (moving.value ? errors.value.rule : undefined));
const summary = computed(() => Object.fromEntries(Object.entries(errors.value).filter(([field]) => field !== 'rule' && field !== 'name' && field !== 'description' && field !== 'rationale')));
const iconOptions = computed(() => props.icons.map((icon) => ({ value: icon, label: t(`structure.icon.${icon}`) })));

watch(() => form.isDirty, (dirty) => {
    detail.dirty = dirty;
    if (dirty) emit('changed');
});

const move = (parent: string): { area?: string; parent_id?: string } => {
    if (parent === parentValue(props.element)) return {};

    return parent.startsWith(AREA_PREFIX) ? { area: parent.slice(AREA_PREFIX.length) } : { parent_id: parent };
};

function choose(key: string): void {
    if (key === NO_MODALITY && form.modality !== NO_MODALITY && (props.element.rationale || form.rationale.trim() !== '')) {
        confirming.value = true;
        return;
    }
    form.modality = key;
    if (key === NO_MODALITY) form.rationale = '';
}

function confirmClear(): void {
    form.confirm_rationale_clear = true;
    form.modality = NO_MODALITY;
    form.rationale = '';
    confirming.value = false;
}

function save(): void {
    form.transform((data) => ({ name: data.name, description: data.description, ...(props.list ? { icon: data.icon || null } : { modality: data.modality, rationale: data.rationale, confirm_rationale_clear: data.confirm_rationale_clear }), ...move(data.parent) })).patch(routes.element(props.canvasId, props.element.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.confirm_rationale_clear = false;
            form.defaults().reset();
            detail.dirty = false;
            emit('saved');
        },
        onError: () => nextTick(focusFirstInvalid),
    });
}
</script>

<template>
    <Teleport to="#detail-body" defer>
        <p v-if="element.unknownAllocation" class="text-[13px]">{{ t('entry.unknown_explanation') }}</p>
        <form v-else :key="element.id" novalidate class="flex flex-col gap-3" @submit.prevent="save">
            <div role="alert"><ErrorSummary :errors="summary" prefix="element-" /></div>
            <p class="text-[12.5px] text-secondary"><span class="font-semibold">{{ t('entry.path') }}:</span> {{ path }}</p>
            <TextField id="element-name" v-model="form.name" :label="t('fields.name')" :error="nameError" autocomplete="off" />
            <TextArea id="element-description" v-model="form.description" :label="t('fields.description')" :error="errors.description" />
            <SelectField v-if="list" id="element-icon" v-model="form.icon" :label="t('entry.icon')" :options="iconOptions" :placeholder="t('entry.no_icon')" :error="errors.icon" />
            <template v-if="!list">
                <ModalityButtons :model-value="form.modality" :error="modalityError" @select="choose" />
                <TextArea v-if="form.modality !== NO_MODALITY" id="element-rationale" v-model="form.rationale" :label="t('modality_entry.rationale')" :error="errors.rationale" />
            </template>
            <ParentSelect v-model="form.parent" :options="options" :error="parentError" />
            <div class="flex flex-wrap gap-2">
                <AppButton type="submit" :disabled="form.processing">{{ t('entry.save') }}</AppButton>
            </div>
        </form>
        <Dialog v-model:open="confirming" :title="t('modality_entry.confirm_title')" :description="t('modality_entry.confirm_message')">
            <div class="flex gap-2">
                <AppButton type="button" @click="confirming = false">{{ t('modality_entry.confirm_no') }}</AppButton>
                <AppButton variant="danger" type="button" @click="confirmClear">{{ t('modality_entry.confirm_yes') }}</AppButton>
            </div>
        </Dialog>
        <div v-if="!element.unknownAllocation" class="mt-3"><ElementActions :canvas-id="canvasId" :element="element" :elements="elements" :gone="gone" /></div>
    </Teleport>
</template>
