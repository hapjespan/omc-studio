<script setup lang="ts">
import { t } from '../../i18n';
import { kindLabelKey, type EntryRowData } from './entry';

defineProps<{ row: EntryRowData; selected: boolean }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null] }>();
</script>

<template>
    <li data-testid="entry-row">
        <button type="button" :aria-current="selected ? 'true' : undefined" class="flex w-full items-baseline gap-3 rounded-md border border-[#848B95] bg-[#F1F3F6] px-3 py-1.5 text-left text-[13px] aria-[current=true]:border-2 aria-[current=true]:border-[#1B1F27]" @click="(event) => $emit('select', row.element.id, event.currentTarget as HTMLElement)">
            <span class="w-24 shrink-0 text-[12.5px] text-[#454C57]">{{ t(kindLabelKey(row.element.kind, row.element.area)) }}</span>
            <span class="min-w-0 flex-1 font-semibold">{{ row.element.name }}</span>
            <span class="text-[12.5px] font-semibold text-[#454C57]">{{ t('entry.system') }}</span>
        </button>
    </li>
</template>
