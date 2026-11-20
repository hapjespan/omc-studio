<script setup lang="ts">
import { computed } from 'vue';
import { t } from '../../i18n';
import type { RefusalReason, ShiftVerdictEvent } from '../../edit-engine';

const props = defineProps<{ event: ShiftVerdictEvent | null }>();

const refusalKey = (reason: RefusalReason): string => `drawing.refusal_${reason}`;

const text = computed(() => {
    const verdict = props.event?.verdict;
    if (!verdict) return '';
    if (!verdict.valid) return t('drawing.drag_refused', { reason: t(refusalKey(verdict.reason)) });

    return t(verdict.narrowed ? 'drawing.drag_to_narrower' : 'drawing.drag_to', { target: verdict.targetName });
});
const place = computed(() => ({ left: `${(props.event?.x ?? 0) + 16}px`, top: `${(props.event?.y ?? 0) + 16}px` }));
</script>

<template>
    <div v-if="event" :style="place" class="pointer-events-none fixed z-50 rounded-control border border-edge bg-white px-2 py-1 text-[13px] font-medium text-ink shadow" data-drag-label :data-valid="event.verdict.valid">{{ text }}</div>
</template>
