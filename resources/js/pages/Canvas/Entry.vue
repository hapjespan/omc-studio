<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { t } from '../../i18n';
import EntryTree from './EntryTree.vue';
import type { AreaKey } from '../../layout/types';
import { buildRows, type EntryElement } from './entry';

interface EntryArea {
    key: string;
    kind: string;
    label: string;
    summary: string;
}

const props = defineProps<{ canvas: { id: number; name: string }; areas: EntryArea[]; selected: string; elements: EntryElement[]; modalities: { key: string; letter: string }[] }>();
const current = ref(props.selected);
const area = computed(() => props.areas.find(({ key }) => key === current.value) ?? props.areas[0]);
const selectedId = ref<string | null>(null);
const letters = computed(() => Object.fromEntries(props.modalities.map(({ key, letter }) => [key, letter])));
const rows = computed(() => buildRows(props.elements.filter((element) => !element.archived), area.value.key as AreaKey));
</script>

<template>
    <Head :title="canvas.name" />
    <header class="flex items-center gap-4">
        <h1 class="text-[20px] font-semibold">{{ canvas.name }}</h1>
    </header>
    <div class="mt-4 flex gap-4">
        <nav :aria-label="t('entry.areas')" class="w-[216px] shrink-0">
            <ul class="flex flex-col gap-2">
                <li v-for="item in areas" :key="item.key">
                    <button type="button" :aria-current="item.key === area.key ? 'true' : undefined" class="w-full rounded-[10px] border border-[#848B95] px-3 py-2 text-left aria-[current=true]:border-2 aria-[current=true]:border-[#1B1F27]" @click="current = item.key">
                        <span class="block text-[13px] font-semibold">{{ item.label }}</span>
                        <span class="block text-[12.5px] text-[#454C57]">{{ item.summary }}</span>
                    </button>
                </li>
            </ul>
        </nav>
        <section :aria-labelledby="`area-${area.key}`" class="min-w-0 flex-1">
            <h2 :id="`area-${area.key}`" class="font-[Space_Grotesk] text-base font-semibold">{{ area.label }}</h2>
            <EntryTree :rows="rows" :letters="letters" :selected-id="selectedId" :label="t('entry.tree', { area: area.label })" @select="selectedId = $event" />
        </section>
    </div>
</template>
