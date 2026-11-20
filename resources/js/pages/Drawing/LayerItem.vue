<script setup lang="ts">
import Pill from '../../components/ui/Pill.vue';
import { t } from '../../i18n';

export interface LayerNode {
    id: string;
    name: string;
    children: LayerNode[];
}

const props = defineProps<{ node: LayerNode; selected: string[]; fresh: string[]; strong?: boolean }>();
const emit = defineEmits<{ select: [id: string, toggle: boolean] }>();
const onKey = (event: KeyboardEvent): void => {
    if (event.key !== ' ' || !event.ctrlKey) return;
    event.preventDefault();
    emit('select', props.node.id, true);
};
</script>

<template>
    <li>
        <div class="flex items-center gap-1">
            <button type="button" class="min-w-0 flex-1 rounded-control px-2 py-1 text-left text-[13px] aria-pressed:bg-accent-soft" :class="{ 'font-semibold': strong }" :aria-pressed="selected.includes(node.id)" :data-layer="node.id" @click="emit('select', node.id, false)" @keydown="onKey">{{ node.name }}</button>
            <Pill v-if="fresh.includes(node.id)">{{ t('drawing.new') }}</Pill>
        </div>
        <ul v-if="node.children.length" class="ml-3">
            <LayerItem v-for="child in node.children" :key="child.id" :node="child" :selected="selected" :fresh="fresh" @select="(id, toggle) => emit('select', id, toggle)" />
        </ul>
    </li>
</template>
