<script setup lang="ts">
import { computed } from 'vue';
import { t } from '../../i18n';
import type { LayoutArea, LayoutElement } from '../../layout';

const props = defineProps<{ areas: LayoutArea[]; elements: LayoutElement[]; selected: string[] }>();
const emit = defineEmits<{ select: [id: string, toggle: boolean] }>();
const onKey = (event: KeyboardEvent, id: string): void => {
    if (event.key !== ' ' || !event.ctrlKey) return;
    event.preventDefault();
    emit('select', id, true);
};

const shown = computed(() => props.areas.filter((area) => area.key !== 'direction'));
const rootsOf = (key: string): LayoutElement[] => props.elements.filter((element) => element.area === key && element.parent === null).sort((a, b) => a.position - b.position);
const childrenOf = (id: string): LayoutElement[] => props.elements.filter((element) => element.parent === id).sort((a, b) => a.position - b.position);
</script>

<template>
    <nav :aria-label="t('drawing.layers')">
        <h2 class="text-[13px] font-semibold">{{ t('drawing.layers') }}</h2>
        <ul class="mt-2 flex flex-col gap-2">
            <li v-for="area in shown" :key="area.key">
                <button type="button" class="w-full rounded-control px-2 py-1 text-left text-[13px] font-semibold aria-pressed:bg-accent-soft" :aria-pressed="selected.includes(area.key)" :data-layer="area.key" @click="$emit('select', area.key, false)" @keydown="onKey($event, area.key)">{{ area.label }}</button>
                <ul v-if="rootsOf(area.key).length" class="ml-3">
                    <template v-for="root in rootsOf(area.key)" :key="root.id">
                        <li>
                            <button type="button" class="w-full rounded-control px-2 py-1 text-left text-[13px] aria-pressed:bg-accent-soft" :aria-pressed="selected.includes(root.id)" :data-layer="root.id" @click="$emit('select', root.id, false)" @keydown="onKey($event, root.id)">{{ root.name }}</button>
                            <ul v-if="childrenOf(root.id).length" class="ml-3">
                                <template v-for="child in childrenOf(root.id)" :key="child.id">
                                    <li>
                                        <button type="button" class="w-full rounded-control px-2 py-1 text-left text-[13px] aria-pressed:bg-accent-soft" :aria-pressed="selected.includes(child.id)" :data-layer="child.id" @click="$emit('select', child.id, false)" @keydown="onKey($event, child.id)">{{ child.name }}</button>
                                        <ul v-if="childrenOf(child.id).length" class="ml-3">
                                            <li v-for="leaf in childrenOf(child.id)" :key="leaf.id">
                                                <button type="button" class="w-full rounded-control px-2 py-1 text-left text-[13px] aria-pressed:bg-accent-soft" :aria-pressed="selected.includes(leaf.id)" :data-layer="leaf.id" @click="$emit('select', leaf.id, false)" @keydown="onKey($event, leaf.id)">{{ leaf.name }}</button>
                                            </li>
                                        </ul>
                                    </li>
                                </template>
                            </ul>
                        </li>
                    </template>
                </ul>
            </li>
        </ul>
    </nav>
</template>
