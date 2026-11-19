<script setup lang="ts">
import { t } from '../../i18n';
import { kindLabelKey, type EntryRowData } from './entry';

defineProps<{ row: EntryRowData; selected: boolean }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null] }>();
</script>

<template>
    <li data-testid="entry-row">
        <button :id="`row-${row.element.id}`" type="button" :aria-current="selected ? 'true' : undefined" :class="['flex min-h-control w-full items-center gap-3 rounded-control border bg-surface px-2.5 text-left text-[13px]', selected ? 'selected-ring' : 'border-hairline']" @click="(event) => $emit('select', row.element.id, event.currentTarget as HTMLElement)">
            <span class="w-[72px] shrink-0 text-[10.5px] text-muted">{{ t(kindLabelKey(row.element.kind, row.element.area)) }}</span>
            <span class="min-w-0 flex-1 font-semibold">{{ row.element.name }}</span>
            <span class="text-[12.5px] font-semibold text-secondary">{{ t('entry.system') }}</span>
        </button>
    </li>
</template>
