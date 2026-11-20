<script setup lang="ts">
import { ref, watch } from 'vue';
import NativeSelect from '../../components/fields/NativeSelect.vue';
import TextField from '../../components/fields/TextField.vue';
import CheckBox from '../../components/ui/CheckBox.vue';
import SegmentedControl from '../../components/ui/SegmentedControl.vue';
import type { StylePatch } from '../../edit-engine/commands/style';
import { t } from '../../i18n';
import { supportsItalic } from '../../layout/fonts';
import ColourField from './ColourField.vue';
import type { StyleTarget } from './StylePanel.vue';

const props = defineProps<{ id: string; name: string; target: StyleTarget; colourError?: string }>();
const emit = defineEmits<{ style: [patch: StylePatch]; invalid: [key: string, text: string] }>();
defineExpose({ focusColour: () => colour.value?.focus() });

const colour = ref<{ focus: () => void }>();
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
    if (typed === '' || !Number.isInteger(number) || number < 10 || number > 48) {
        sizeError.value = t('layout.style.font_size', { name: props.name });
        return;
    }
    sizeError.value = '';
    emit('style', { font_size: number });
}

const pickFont = (value: string): void => emit('style', value === 'space_grotesk' ? { font: value, italic: null } : { font: value });
</script>

<template>
    <section class="flex flex-col gap-3 border-t border-hairline pt-3" :aria-label="t('drawing_style.text_heading')" data-text-style-panel>
        <h3 class="text-[13px] font-semibold">{{ t('drawing_style.text_heading') }}</h3>
        <NativeSelect id="style-font" :label="t('drawing_style.font_of', { name })" :model-value="family()" :options="fonts" @update:model-value="pickFont" />
        <TextField id="style-font-size" v-model="size" type="number" :label="t('drawing_style.font_size_of', { name })" :error="sizeError || undefined" @change="commitSize" />
        <SegmentedControl :label="t('drawing_style.weight_of', { name })" :items="weights" :active="target.resolved.font.weight >= 600 ? 'bold' : 'normal'" @select="emit('style', { font_weight: $event })" />
        <label class="inline-flex items-center gap-1 text-[13px]">
            <CheckBox :model-value="target.resolved.font.italic" :disabled="!supportsItalic(target.resolved.font.family)" :aria-label="t('drawing_style.italic_of', { name })" @update:model-value="emit('style', { italic: Boolean($event) })" />
            <span aria-hidden="true">{{ t('drawing_style.italic') }}</span>
        </label>
        <ColourField
            id="style-text-colour"
            ref="colour"
            :caption="t('drawing_style.text')"
            :label="t('drawing_style.text_colour_of', { name })"
            :swatch-label="t('drawing_style.text_swatch', { name })"
            :value="target.resolved.textColour"
            :invalid-message="t('layout.style.text_colour', { name })"
            :error="colourError"
            @commit="emit('style', { text_colour: $event })"
            @invalid="emit('invalid', 'text_colour', $event)"
        />
        <SegmentedControl :label="t('drawing_style.align_horizontal_of', { name })" :items="horizontal" :active="target.resolved.alignHorizontal" @select="emit('style', { align_horizontal: $event })" />
        <SegmentedControl :label="t('drawing_style.align_vertical_of', { name })" :items="vertical" :active="target.resolved.alignVertical" @select="emit('style', { align_vertical: $event })" />
    </section>
</template>
