<script setup lang="ts">
import { computed } from 'vue';
import Panel from '../../components/ui/Panel.vue';
import { t } from '../../i18n';
import type { LayoutArea, LayoutElement } from '../../layout';
import LayerItem, { type LayerNode } from './LayerItem.vue';

const props = defineProps<{ areas: LayoutArea[]; elements: LayoutElement[]; selected: string[]; fresh: string[] }>();
defineEmits<{ select: [id: string, toggle: boolean] }>();

const tree = (parent: string | null, area: string): LayerNode[] =>
    props.elements
        .filter((element) => element.parent === parent && (parent !== null || element.area === area))
        .sort((a, b) => a.position - b.position)
        .map((element) => ({ id: element.id, name: element.name, children: tree(element.id, area) }));
const nodes = computed<LayerNode[]>(() => props.areas.filter((area) => area.key !== 'direction').map((area) => ({ id: area.key, name: area.label, children: tree(null, area.key) })));
</script>

<template>
    <Panel :heading="t('drawing.layers')">
        <nav :aria-label="t('drawing.layers')">
            <ul class="flex flex-col gap-2">
                <LayerItem v-for="node in nodes" :key="node.id" :node="node" :selected="selected" :fresh="fresh" strong @select="(id, toggle) => $emit('select', id, toggle)" />
            </ul>
        </nav>
    </Panel>
</template>
