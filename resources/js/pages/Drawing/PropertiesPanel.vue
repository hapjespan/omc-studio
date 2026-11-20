<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import TextField from '../../components/fields/TextField.vue';
import Panel from '../../components/ui/Panel.vue';
import SelectField from '../../components/fields/SelectField.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';
import type { MoveTarget } from './moveTargets';
import type { BoxValues } from '../../edit-engine';
import type { CopiedStyle } from '../../edit-engine/commands/pasteStyle';
import type { StylePatch } from '../../edit-engine/commands/style';
import StylePanel, { type StyleTarget } from './StylePanel.vue';
import TextStylePanel from './TextStylePanel.vue';

export interface InvalidValue {
    id: string;
    key: 'x' | 'y' | 'width' | 'height';
    value: string;
    message: string;
}

const props = defineProps<{ id: string | null; name: string; box: BoxValues | null; targets: MoveTarget[]; invalid: InvalidValue | null; domain: boolean; target?: StyleTarget | null; copied?: CopiedStyle | null; scope?: 'domains' | 'activities' | null }>();
const emit = defineEmits<{ box: [id: string, box: Partial<BoxValues>]; move: [targetId: string, opener: HTMLElement | null]; reset: [scope: 'element' | 'domain']; style: [patch: StylePatch]; copyStyle: []; pasteStyle: []; applyStyle: [] }>();
const chosen = ref('');
const opener = ref<{ focus: () => void; $el?: HTMLElement } | null>(null);
watch(() => props.id, () => (chosen.value = ''));
watch(
    () => props.invalid,
    async (invalid) => {
        if (!invalid) return;
        await nextTick();
        document.getElementById(`box-${invalid.key}`)?.focus();
    },
);
const errorOf = (key: string): string | undefined => (props.invalid && props.invalid.id === props.id && props.invalid.key === key ? props.invalid.message : undefined);

const keys = ['x', 'y', 'width', 'height'] as const;
const labels = { x: 'drawing.field_x', y: 'drawing.field_y', width: 'drawing.field_width', height: 'drawing.field_height' } as const;
const values = ref<Record<(typeof keys)[number], string>>({ x: '', y: '', width: '', height: '' });

watch(
    () => props.box,
    (box) => {
        for (const key of keys) values.value[key] = box ? String(box[key]) : '';
        if (props.invalid && props.invalid.id === props.id) values.value[props.invalid.key] = props.invalid.value;
    },
    { immediate: true },
);

function commit(key: (typeof keys)[number]): void {
    const typed = String(values.value[key]).trim();
    const number = Number(typed);
    if (!props.id || typed === '' || !Number.isFinite(number)) {
        values.value[key] = props.box ? String(props.box[key]) : '';
        return;
    }
    if (props.box?.[key] === Math.round(number)) return;
    emit('box', props.id, { [key]: Math.round(number) });
}
</script>

<template>
    <Panel :heading="t('drawing.properties')" role="complementary" :aria-label="t('drawing.properties')">
        <slot name="peek" />
        <form v-if="id && box" class="flex flex-col gap-2" :aria-label="t('drawing.place_and_size')" @submit.prevent>
            <h3 class="text-[13px] font-semibold">{{ t('drawing.place_and_size') }}</h3>
            <p class="text-[13px] text-secondary" data-size-label>{{ box.width }} × {{ box.height }}</p>
            <div class="grid grid-cols-2 gap-2">
                <TextField v-for="key in keys" :id="`box-${key}`" :key="key" v-model="values[key]" type="number" :label="t(labels[key], { name })" :error="errorOf(key)" @change="commit(key)" />
            </div>
        </form>
        <StylePanel v-if="id && target" :id="id" :name="name" :target="target" :copied="copied ?? null" :scope="scope ?? null" @style="emit('style', $event)" @copy="emit('copyStyle')" @paste="emit('pasteStyle')" @apply="emit('applyStyle')" />
        <TextStylePanel v-if="id && target && target.kind !== 'area'" :id="id" :name="name" :target="target" @style="emit('style', $event)" />
        <div v-if="id" class="mt-3 flex flex-col gap-2" data-reset-panel>
            <AppButton size="dense" @click="emit('reset', 'element')">{{ t('drawing.reset_element') }}</AppButton>
            <AppButton v-if="domain" size="dense" @click="emit('reset', 'domain')">{{ t('drawing.reset_domain') }}</AppButton>
        </div>
        <div v-if="id && targets.length > 0" class="mt-3 flex flex-col gap-2" data-move-panel>
            <SelectField id="move-target" v-model="chosen" :label="t('drawing.move_to')" :options="targets.map((target) => ({ value: target.id, label: target.path }))" :placeholder="t('drawing.move_choose')" />
            <AppButton ref="opener" :disabled="chosen === ''" @click="emit('move', chosen, (opener?.$el as HTMLElement) ?? null)">{{ t('drawing.move_confirm') }}</AppButton>
        </div>
        <p v-if="!id && !$slots.peek" class="text-[13px] text-secondary">{{ t('drawing.nothing_selected') }}</p>
    </Panel>
</template>
