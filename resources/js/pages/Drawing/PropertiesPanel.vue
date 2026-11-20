<script setup lang="ts">
import { ref, watch } from 'vue';
import TextField from '../../components/fields/TextField.vue';
import Panel from '../../components/ui/Panel.vue';
import { t } from '../../i18n';
import type { BoxValues } from '../../edit-engine';
import type { CopiedStyle } from '../../edit-engine/commands/pasteStyle';
import type { StylePatch } from '../../edit-engine/commands/style';
import StylePanel, { type StyleTarget } from './StylePanel.vue';
import TextStylePanel from './TextStylePanel.vue';

const props = defineProps<{ id: string | null; name: string; box: BoxValues | null; target?: StyleTarget | null; copied?: CopiedStyle | null; scope?: 'domains' | 'activities' | null }>();
const emit = defineEmits<{ box: [id: string, box: Partial<BoxValues>]; style: [patch: StylePatch]; copyStyle: []; pasteStyle: []; applyStyle: [] }>();

const keys = ['x', 'y', 'width', 'height'] as const;
const labels = { x: 'drawing.field_x', y: 'drawing.field_y', width: 'drawing.field_width', height: 'drawing.field_height' } as const;
const values = ref<Record<(typeof keys)[number], string>>({ x: '', y: '', width: '', height: '' });

watch(
    () => props.box,
    (box) => {
        for (const key of keys) values.value[key] = box ? String(box[key]) : '';
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
                <TextField v-for="key in keys" :id="`box-${key}`" :key="key" v-model="values[key]" type="number" :label="t(labels[key], { name })" @change="commit(key)" />
            </div>
        </form>
        <StylePanel v-if="id && target" :id="id" :name="name" :target="target" :copied="copied ?? null" :scope="scope ?? null" @style="emit('style', $event)" @copy="emit('copyStyle')" @paste="emit('pasteStyle')" @apply="emit('applyStyle')" />
        <TextStylePanel v-if="id && target && target.kind !== 'area'" :id="id" :name="name" :target="target" @style="emit('style', $event)" />
        <p v-else-if="!$slots.peek" class="text-[13px] text-secondary">{{ t('drawing.nothing_selected') }}</p>
    </Panel>
</template>
