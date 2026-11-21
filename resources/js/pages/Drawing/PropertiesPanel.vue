<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import Panel from '../../components/ui/Panel.vue';
import SelectField from '../../components/fields/SelectField.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';
import type { MoveTarget } from './moveTargets';
import type { DrawingCommand } from '../../edit-engine';
import CheckBox from '../../components/ui/CheckBox.vue';
import { routes } from '../../routes';
import type { BoxValues } from '../../edit-engine';
import type { CopiedStyle } from '../../edit-engine/commands/pasteStyle';
import type { StylePatch } from '../../edit-engine/commands/style';
import StylePanel, { type StyleRefusal, type StyleTarget } from './StylePanel.vue';
import TextStylePanel from './TextStylePanel.vue';

export interface InvalidValue {
    id: string;
    key: 'x' | 'y' | 'width' | 'height';
    value: string;
    message: string;
}

const props = defineProps<{ id: string | null; name: string; box: BoxValues | null; targets: MoveTarget[]; invalid: InvalidValue | null; domain: boolean; count: number; path: string; canvasId: number; target?: StyleTarget | null; copied?: CopiedStyle | null; scope?: 'domains' | 'activities' | null; refused?: StyleRefusal | null }>();
const grid = defineModel<boolean>('grid', { default: false });
const emit = defineEmits<{ box: [id: string, box: Partial<BoxValues>]; move: [targetId: string, opener: HTMLElement | null]; reset: [scope: 'element' | 'domain']; command: [command: DrawingCommand]; style: [patch: StylePatch]; copyStyle: []; pasteStyle: []; applyStyle: [] }>();
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
const shortLabels = { x: 'X', y: 'Y', width: 'B', height: 'H' } as const;
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
    <Panel :heading="id ? name : t('drawing.properties')" role="complementary" :aria-label="t('drawing.properties')">
        <p v-if="id && path" class="-mt-2 mb-3 text-[11.5px] text-muted" data-kicker>{{ path }}</p>
        <slot name="peek" />
        <form v-if="id && box" class="flex flex-col gap-2" :aria-label="t('drawing.place_and_size')" @submit.prevent>
            <h3 class="text-[13px] font-semibold">{{ t('drawing.place_and_size') }}</h3>
            <p class="text-[13px] text-secondary" data-size-label>{{ box.width }} × {{ box.height }}</p>
            <div class="grid grid-cols-2 gap-x-3 gap-y-2">
                <div v-for="key in keys" :key="key" class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <label :for="`box-${key}`" class="w-3 text-[13px] font-semibold text-ink"><span aria-hidden="true">{{ shortLabels[key] }}</span><span class="sr-only">{{ t(labels[key], { name }) }}</span></label>
                        <input :id="`box-${key}`" v-model="values[key]" type="number" :aria-describedby="errorOf(key) ? `box-${key}-error` : undefined" :aria-invalid="errorOf(key) ? 'true' : undefined" class="h-control min-w-0 flex-1 rounded-control border border-edge bg-white px-2.5 text-[13px] text-ink" @change="commit(key)" />
                    </div>
                    <p v-if="errorOf(key)" :id="`box-${key}-error`" class="text-[12.5px] font-medium text-danger">{{ errorOf(key) }}</p>
                </div>
            </div>
        </form>
        <StylePanel v-if="id && target" :key="id" :id="id" :name="name" :target="target" :copied="copied ?? null" :scope="scope ?? null" :refused="refused ?? null" @style="emit('style', $event)" @copy="emit('copyStyle')" @paste="emit('pasteStyle')" @apply="emit('applyStyle')" />
        <TextStylePanel v-if="id && target && target.kind !== 'area'" :key="id" :id="id" :name="name" :target="target" :refused="refused ?? null" @style="emit('style', $event)" />
        <div v-if="count > 0" class="mt-3 flex flex-col gap-2" data-arrange>
            <div class="flex flex-wrap gap-2">
                <AppButton size="dense" :disabled="count < 2" @click="emit('command', { kind: 'equalWidth' })">{{ t('drawing.equal_width') }}</AppButton>
                <AppButton size="dense" :disabled="count < 2" @click="emit('command', { kind: 'equalHeight' })">{{ t('drawing.equal_height') }}</AppButton>
            </div>
            <div class="flex flex-wrap gap-2">
                <AppButton size="dense" @click="emit('command', { kind: 'bringForward' })">{{ t('drawing.bring_forward') }}</AppButton>
                <AppButton size="dense" @click="emit('command', { kind: 'sendBackward' })">{{ t('drawing.send_backward') }}</AppButton>
            </div>
            <span class="inline-flex items-center gap-1 text-[13px]"><CheckBox v-model="grid" :aria-label="t('drawing.grid')" />{{ t('drawing.grid') }}</span>
        </div>
        <div v-if="id" class="mt-3 flex flex-col gap-2" data-reset-panel>
            <AppButton size="dense" @click="emit('reset', 'element')">{{ t('drawing.reset_element') }}</AppButton>
            <AppButton v-if="domain" size="dense" @click="emit('reset', 'domain')">{{ t('drawing.reset_domain') }}</AppButton>
        </div>
        <div v-if="id && targets.length > 0" class="mt-3 flex flex-col gap-2" data-move-panel>
            <SelectField id="move-target" v-model="chosen" :label="t('drawing.move_to')" :options="targets.map((target) => ({ value: target.id, label: target.path }))" :placeholder="t('drawing.move_choose')" />
            <AppButton ref="opener" :disabled="chosen === ''" @click="emit('move', chosen, (opener?.$el as HTMLElement) ?? null)">{{ t('drawing.move_confirm') }}</AppButton>
        </div>
        <p v-if="id" class="mt-3 border-t border-hairline pt-3 text-[12.5px] text-secondary" data-entry-note>{{ t('drawing.entry_note_before') }} <a :href="routes.entry(canvasId)" class="font-semibold underline">{{ t('shell.mode.entry') }}</a>{{ t('drawing.entry_note_after') }}</p>
        <p v-else-if="!$slots.peek" class="text-[13px] text-secondary">{{ t('drawing.nothing_selected') }}</p>
    </Panel>
</template>
