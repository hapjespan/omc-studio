<script setup lang="ts">
import { t } from '../../i18n';
import { kindLabelKey, type EntryRowData } from './entry';

const depthClass = { 0: 'pl-0', 1: 'pl-6', 2: 'pl-12' } as const;

defineProps<{ row: EntryRowData; letter: string | null; selected: boolean }>();
defineEmits<{ select: [id: string] }>();
</script>

<template>
    <li :class="depthClass[row.depth]">
        <button type="button" :aria-current="selected ? 'true' : undefined" class="flex w-full items-baseline gap-3 rounded-md border border-[#848B95] px-3 py-1.5 text-left text-[13px] aria-[current=true]:border-2 aria-[current=true]:border-[#1B1F27]" @click="$emit('select', row.element.id)">
            <span class="w-24 shrink-0 text-[12.5px] text-[#454C57]">{{ t(kindLabelKey(row.element.kind, row.element.area)) }}</span>
            <span class="min-w-0 flex-1 font-semibold">{{ row.element.name }}</span>
            <span v-if="letter"><span class="sr-only">{{ t('entry.modality') }}</span>{{ letter }}</span>
        </button>
    </li>
</template>
