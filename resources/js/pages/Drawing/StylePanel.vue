<script setup lang="ts">
import { ref, watch } from 'vue';
import limits from '../../../data/layout-limits.json';
import AppButton from '../../components/ui/AppButton.vue';
import NativeSelect from '../../components/fields/NativeSelect.vue';
import TextField from '../../components/fields/TextField.vue';
import { canPaste, type CopiedStyle } from '../../layout/styleScope';
import type { StylePatch } from '../../edit-engine/commands/style';
import { t } from '../../i18n';
import type { BlockStyle } from '../../layout';
import ColourField from './ColourField.vue';

export interface StyleRefusal {
    id: string;
    key: string;
    value: string;
    message: string;
}

export interface StyleTarget {
    kind: 'area' | 'domain' | 'cluster' | 'activity';
    resolved: BlockStyle;
    backdrop: string;
}

const props = defineProps<{ id: string; name: string; target: StyleTarget; copied: CopiedStyle | null; scope: 'domains' | 'activities' | null; refused?: StyleRefusal | null }>();
const emit = defineEmits<{ style: [patch: StylePatch]; copy: []; paste: []; apply: [] }>();

const RANGES = {
    fill_opacity: { low: limits.style.fill_opacity[0], high: limits.style.fill_opacity[1], message: 'layout.style.fill_opacity' },
    border_width: { low: limits.style.border_width[0], high: limits.style.border_width[1], message: 'layout.style.border_width' },
    corner_radius: { low: limits.style.corner_radius[0], high: limits.style.corner_radius[1], message: 'layout.style.corner_radius' },
} as const;
type RangeKey = keyof typeof RANGES;

const current = (): Record<RangeKey, string> => ({ fill_opacity: String(props.target.resolved.fillOpacity), border_width: String(props.target.resolved.borderWidth), corner_radius: String(props.target.resolved.radius) });
const numbers = ref(current());
const errors = ref<Partial<Record<RangeKey, string>>>({});

watch(
    () => props.target,
    () => {
        numbers.value = current();
        errors.value = {};
    },
);

function commitNumber(key: RangeKey): void {
    const { low, high, message } = RANGES[key];
    const typed = String(numbers.value[key]).trim();
    const number = Number(typed);
    if (typed === current()[key]) return void (errors.value[key] = undefined);
    if (typed === '' || !Number.isInteger(number) || number < low || number > high) {
        errors.value[key] = t(message, { name: props.name });
        return;
    }
    errors.value[key] = undefined;
    emit('style', { [key]: number });
}

const colour = (key: 'fill' | 'border_colour', hex: string): void => {
    const patch: StylePatch = { [key]: hex };
    if (key === 'border_colour' && props.target.resolved.borderWidth === 0) patch.border_width = 1;
    emit('style', patch);
};

const borderStyles = [
    { value: 'solid', label: t('drawing_style.solid') },
    { value: 'dashed', label: t('drawing_style.dashed') },
];
</script>

<template>
    <section class="flex flex-col gap-3 border-t border-hairline pt-3" :aria-label="t('drawing_style.heading')" data-style-panel>
        <h3 class="text-[13px] font-semibold">{{ t('drawing_style.heading') }}</h3>
        <ColourField
            id="style-fill"
            :caption="t('drawing_style.fill')"
            :label="t('drawing_style.fill_of', { name })"
            :swatch-label="t('drawing_style.fill_swatch', { name })"
            :value="target.resolved.fill ?? ''"
            :invalid-message="t('layout.style.fill', { name })"
            :refused="refused?.key === 'fill' ? refused : null"
            @commit="colour('fill', $event)"
        />
        <TextField id="style-fill-opacity" v-model="numbers.fill_opacity" type="number" :label="t('drawing_style.fill_opacity_of', { name })" :error="errors.fill_opacity" @change="commitNumber('fill_opacity')" />
        <ColourField
            id="style-border"
            :caption="t('drawing_style.border')"
            :label="t('drawing_style.border_of', { name })"
            :swatch-label="t('drawing_style.border_swatch', { name })"
            :value="target.resolved.borderColour"
            :invalid-message="t('layout.style.border_colour', { name })"
            :refused="refused?.key === 'border_colour' ? refused : null"
            @commit="colour('border_colour', $event)"
        />
        <div class="flex flex-col gap-3">
            <TextField id="style-border-width" v-model="numbers.border_width" type="number" :label="t('drawing_style.border_width_of', { name })" :error="errors.border_width" @change="commitNumber('border_width')" />
            <NativeSelect id="style-border-style" :label="t('drawing_style.border_style_of', { name })" :model-value="target.resolved.borderStyle" :options="borderStyles" @update:model-value="emit('style', { border_style: $event })" />
        </div>
        <TextField id="style-corner-radius" v-model="numbers.corner_radius" type="number" :label="t('drawing_style.corner_radius_of', { name })" :error="errors.corner_radius" @change="commitNumber('corner_radius')" />
        <div class="flex flex-col gap-2">
            <AppButton size="dense" @click="emit('copy')">{{ t('drawing_style.copy') }}</AppButton>
            <AppButton v-if="scope" size="dense" @click="emit('apply')">{{ t(scope === 'domains' ? 'drawing_style.apply_domains' : 'drawing_style.apply_activities') }}</AppButton>
            <AppButton v-if="canPaste(copied, target.kind)" size="dense" @click="emit('paste')">{{ t('drawing_style.paste') }}</AppButton>
        </div>
    </section>
</template>
