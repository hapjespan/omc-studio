<script setup lang="ts">
import { ref, watch } from 'vue';
import NativeSelect from '../../components/fields/NativeSelect.vue';
import TextField from '../../components/fields/TextField.vue';
import type { StylePatch } from '../../edit-engine/commands/style';
import { t } from '../../i18n';
import type { BlockStyle } from '../../layout';
import ColourField from './ColourField.vue';

export interface StyleTarget {
    kind: 'area' | 'domain' | 'cluster' | 'activity';
    resolved: BlockStyle;
}

const props = defineProps<{ id: string; name: string; target: StyleTarget }>();
const emit = defineEmits<{ style: [patch: StylePatch] }>();

const RANGES = {
    fill_opacity: { low: 0, high: 100, message: 'layout.style.fill_opacity' },
    border_width: { low: 0, high: 8, message: 'layout.style.border_width' },
    corner_radius: { low: 0, high: 64, message: 'layout.style.corner_radius' },
} as const;
type RangeKey = keyof typeof RANGES;

const current = (): Record<RangeKey, string> => ({ fill_opacity: String(props.target.resolved.fillOpacity), border_width: String(props.target.resolved.borderWidth), corner_radius: String(props.target.resolved.radius) });
const numbers = ref(current());
const errors = ref<Partial<Record<RangeKey, string>>>({});
const fields = ref<Record<string, { focus: () => void } | null>>({});

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
            @commit="colour('border_colour', $event)"
        />
        <div class="grid grid-cols-2 gap-2">
            <TextField id="style-border-width" v-model="numbers.border_width" type="number" :label="t('drawing_style.border_width_of', { name })" :error="errors.border_width" @change="commitNumber('border_width')" />
            <NativeSelect id="style-border-style" :label="t('drawing_style.border_style_of', { name })" :model-value="target.resolved.borderStyle" :options="borderStyles" @update:model-value="emit('style', { border_style: $event })" />
        </div>
        <TextField id="style-corner-radius" v-model="numbers.corner_radius" type="number" :label="t('drawing_style.corner_radius_of', { name })" :error="errors.corner_radius" @change="commitNumber('corner_radius')" />
    </section>
</template>
