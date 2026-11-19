<script setup lang="ts">
import { computed, nextTick, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import SelectField from '../../components/fields/SelectField.vue';
import TextArea from '../../components/fields/TextArea.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import type { TargetRow } from '../../allocation/types';
import { t } from '../../i18n';
import { routes } from '../../routes';
import { detail } from '../../shell/detail';
import { AREA_PREFIX, parentOptions, parentValue, type ElementCost, type EntryAreaInfo, type EntryElement } from './entry';
import ElementActions from './ElementActions.vue';
import ElementCostsLine from './ElementCostsLine.vue';
import ParentSelect from './ParentSelect.vue';

const props = defineProps<{ canvasId: number; element: EntryElement; elements: EntryElement[]; areas: EntryAreaInfo[]; path: string; list: boolean; icons: string[]; year: number | null; cost?: ElementCost; targets: TargetRow[]; gone: (kind: 'archived' | 'deleted', name: string) => void }>();
const emit = defineEmits<{ saved: []; changed: [] }>();
const form = useForm({ name: props.element.name, description: props.element.description ?? '', icon: props.element.icon ?? '', parent: parentValue(props.element) });
const options = computed(() => parentOptions(props.element, props.elements, props.areas));
const errors = computed(() => form.errors as Record<string, string>);
const moving = computed(() => form.parent !== parentValue(props.element));
const nameError = computed(() => errors.value.name ?? (moving.value ? undefined : errors.value.rule));
const parentError = computed(() => (moving.value ? errors.value.rule : undefined));
const summary = computed(() => Object.fromEntries(Object.entries(errors.value).filter(([field]) => field !== 'rule' && field !== 'name' && field !== 'description')));
const iconOptions = computed(() => props.icons.map((icon) => ({ value: icon, label: t(`structure.icon.${icon}`) })));

watch(() => form.isDirty, (dirty) => {
    detail.dirty = dirty;
    if (dirty) emit('changed');
});

const move = (parent: string): { area?: string; parent_id?: string } => {
    if (parent === parentValue(props.element)) return {};

    return parent.startsWith(AREA_PREFIX) ? { area: parent.slice(AREA_PREFIX.length) } : { parent_id: parent };
};

function save(): void {
    form.transform((data) => ({ name: data.name, description: data.description, ...(props.list ? { icon: data.icon || null } : {}), ...move(data.parent) })).patch(routes.element(props.canvasId, props.element.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
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
            <p class="text-[12.5px] text-[#454C57]"><span class="font-semibold">{{ t('entry.path') }}:</span> {{ path }}</p>
            <TextField id="element-name" v-model="form.name" :label="t('fields.name')" :error="nameError" autocomplete="off" />
            <TextArea id="element-description" v-model="form.description" :label="t('fields.description')" :error="errors.description" />
            <SelectField v-if="list" id="element-icon" v-model="form.icon" :label="t('entry.icon')" :options="iconOptions" :placeholder="t('entry.no_icon')" :error="errors.icon" />
            <ParentSelect v-model="form.parent" :options="options" :error="parentError" />
            <ElementCostsLine :year="year" :cost="cost" />
            <div class="flex flex-wrap gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('entry.save') }}</button>
            </div>
        </form>
        <div v-if="!element.unknownAllocation" class="mt-3"><ElementActions :canvas-id="canvasId" :element="element" :elements="elements" :cost="cost" :targets="targets" :gone="gone" /></div>
    </Teleport>
</template>
