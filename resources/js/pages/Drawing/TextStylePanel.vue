<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import limits from '../../../data/layout-limits.json';
import NativeSelect from '../../components/fields/NativeSelect.vue';
import TextField from '../../components/fields/TextField.vue';
import CheckBox from '../../components/ui/CheckBox.vue';
import SegmentedControl from '../../components/ui/SegmentedControl.vue';
import { contrastRatio, MINIMUM_CONTRAST, ratioLabel } from '../../colour';
import type { StylePatch } from '../../edit-engine/commands/style';
import { t } from '../../i18n';
import { supportsItalic } from '../../layout/fonts';
import ColourField from './ColourField.vue';
import type { StyleRefusal, StyleTarget } from './StylePanel.vue';

const props = defineProps<{ id: string; name: string; target: StyleTarget; refused?: StyleRefusal | null }>();
const emit = defineEmits<{ style: [patch: StylePatch] }>();

const colour = ref<{ focus: () => void }>();
const shownColour = computed(() => (props.refused?.key === 'text_colour' && /^#[0-9a-f]{6}$/i.test(props.refused.value) ? props.refused.value : props.target.resolved.textColour));
const ratio = computed(() => contrastRatio(shownColour.value, props.target.backdrop));
const failing = computed(() => ratio.value < MINIMUM_CONTRAST);
const contrastError = computed(() => (failing.value ? t('layout.rule.contrast', { name: props.name, ratio: ratioLabel(ratio.value) }) : undefined));
const size = ref(String(props.target.resolved.font.size));
const sizeError = ref('');
const fonts = [
    { value: 'inter', label: 'Inter' },
    { value: 'space_grotesk', label: 'Space Grotesk' },
];
const weights = [
    { key: 'normal', label: t('drawing_style.normal') },
    { key: 'bold', label: t('drawing_style.bold') },
];
const horizontal = [
    { key: 'left', label: t('drawing_style.left') },
    { key: 'center', label: t('drawing_style.centre') },
    { key: 'right', label: t('drawing_style.right') },
];
const vertical = [
    { key: 'top', label: t('drawing_style.top') },
    { key: 'middle', label: t('drawing_style.middle') },
    { key: 'bottom', label: t('drawing_style.bottom') },
];

const family = (): 'inter' | 'space_grotesk' => (props.target.resolved.font.family === 'Inter' ? 'inter' : 'space_grotesk');

watch(
    () => props.target,
    () => {
        size.value = String(props.target.resolved.font.size);
        sizeError.value = '';
    },
);

function commitSize(): void {
    const typed = String(size.value).trim();
    const number = Number(typed);
    if (typed === String(props.target.resolved.font.size)) return void (sizeError.value = '');
    if (typed === '' || !Number.isInteger(number) || number < limits.style.font_size[0] || number > limits.style.font_size[1]) {
        sizeError.value = t('layout.style.font_size', { name: props.name });
        return;
    }
    sizeError.value = '';
    emit('style', { font_size: number });
}

function commitColour(hex: string): void {
    emit('style', { text_colour: hex });
    if (contrastRatio(hex, props.target.backdrop) < MINIMUM_CONTRAST) void nextTick().then(nextTick).then(() => colour.value?.focus());
}

const pickFont = (value: string): void => emit('style', value === 'space_grotesk' ? { font: value, italic: null } : { font: value });
</script>

<template>
    <section class="flex flex-col gap-3 border-t border-hairline pt-3" :aria-label="t('drawing_style.text_heading')" data-text-style-panel>
        <h3 class="text-[13px] font-semibold">{{ t('drawing_style.text_heading') }}</h3>
        <NativeSelect id="style-font" :label="t('drawing_style.font_of', { name })" :model-value="family()" :options="fonts" @update:model-value="pickFont" />
        <TextField id="style-font-size" v-model="size" type="number" :label="t('drawing_style.font_size_of', { name })" :error="sizeError || undefined" @change="commitSize" />
        <SegmentedControl :label="t('drawing_style.weight_of', { name })" :items="weights" :active="target.resolved.font.weight >= 600 ? 'bold' : 'normal'" @select="emit('style', { font_weight: $event })" />
        <span class="inline-flex items-center gap-1 text-[13px]">
            <CheckBox :model-value="target.resolved.font.italic" :disabled="!supportsItalic(target.resolved.font.family)" :aria-label="t('drawing_style.italic_of', { name })" @update:model-value="emit('style', { italic: Boolean($event) })" />
            {{ t('drawing_style.italic') }}
        </span>
        <ColourField
            id="style-text-colour"
            ref="colour"
            :caption="t('drawing_style.text')"
            :label="t('drawing_style.text_colour_of', { name })"
            :swatch-label="t('drawing_style.text_swatch', { name })"
            :value="target.resolved.textColour"
            :invalid-message="t('layout.style.text_colour', { name })"
            :error="contrastError"
            :refused="refused?.key === 'text_colour' ? refused : null"
            @commit="commitColour"
        />
        <p class="text-[12.5px] text-secondary" data-contrast>{{ t('drawing_style.contrast', { ratio: ratioLabel(ratio) }) }}</p>
        <SegmentedControl :label="t('drawing_style.align_horizontal_of', { name })" :items="horizontal" :active="target.resolved.alignHorizontal" @select="emit('style', { align_horizontal: $event })" />
        <SegmentedControl :label="t('drawing_style.align_vertical_of', { name })" :items="vertical" :active="target.resolved.alignVertical" @select="emit('style', { align_vertical: $event })" />
    </section>
</template>
