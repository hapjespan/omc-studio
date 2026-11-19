<script setup lang="ts">
import { t } from '../../i18n';

export type BulkAction = { key: string; label: string };
export type BulkSummary = { done: number; verb: string; skipped: { name: string; reason: string }[] };

defineProps<{ actions: BulkAction[]; selected: number; result?: BulkSummary | null }>();
defineEmits<{ run: [key: string] }>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="selected > 0" class="flex flex-wrap items-center gap-2">
            <p class="text-[13px]">{{ t('list.selected', { count: selected }) }}</p>
            <button v-for="action in actions" :key="action.key" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="$emit('run', action.key)">{{ action.label }}</button>
        </div>
        <div v-if="result" role="status" class="text-[13px]">
            <p>{{ result.skipped.length ? t('list.bulk_result', { done: result.done, verb: result.verb, skipped: result.skipped.length }) : t('list.bulk_result_all', { done: result.done, verb: result.verb }) }}</p>
            <ul v-if="result.skipped.length" class="list-disc pl-5">
                <li v-for="row in result.skipped" :key="row.name">{{ t('list.skipped_row', { name: row.name, reason: row.reason }) }}</li>
            </ul>
        </div>
    </div>
</template>
