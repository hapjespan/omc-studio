<script setup lang="ts">
import { computed, watch } from 'vue';
import { t } from '../../i18n';
import { announce } from '../feedback/status';
import AppButton from '../ui/AppButton.vue';

export type BulkAction = { key: string; label: string };
export type BulkSummary = { done: number; verb: string; skipped: { name: string; reason: string }[] };

const props = defineProps<{ actions: BulkAction[]; selected: number; result?: BulkSummary | null }>();
defineEmits<{ run: [key: string] }>();

const summary = computed(() => (props.result ? (props.result.skipped.length ? t('list.bulk_result', { done: props.result.done, verb: props.result.verb, skipped: props.result.skipped.length }) : t('list.bulk_result_all', { done: props.result.done, verb: props.result.verb })) : ''));

watch(summary, (text) => text && announce(text), { immediate: true });
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="selected > 0" class="flex flex-wrap items-center gap-2">
            <p class="text-[13px]">{{ t('list.selected', { count: selected }) }}</p>
            <AppButton v-for="action in actions" :key="action.key" @click="$emit('run', action.key)">{{ action.label }}</AppButton>
        </div>
        <div v-if="result" class="text-[13px]">
            <p>{{ summary }}</p>
            <ul v-if="result.skipped.length" class="list-disc pl-5">
                <li v-for="(row, index) in result.skipped" :key="index">{{ t('list.skipped_row', { name: row.name, reason: row.reason }) }}</li>
            </ul>
        </div>
    </div>
</template>
