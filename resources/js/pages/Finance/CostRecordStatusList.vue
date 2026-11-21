<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '../../components/ui/AppButton.vue';
import SearchField from '../../components/ui/SearchField.vue';
import { formatAmount } from '../../format';
import { t } from '../../i18n';
import type { CostRecordRow } from '../../allocation/types';

const props = defineProps<{ records: CostRecordRow[]; chosen: string | null; year: number | null; draft: string | null }>();
const emit = defineEmits<{ choose: [id: string] }>();
const search = ref('');
const unallocatedOnly = ref(false);
const done = computed(() => props.records.filter((record) => record.status === 'allocated').length);
const shown = computed(() => props.records.filter((record) => (!unallocatedOnly.value || record.status === 'unallocated') && `${record.code ?? ''} ${record.name}`.toLowerCase().includes(search.value.trim().toLowerCase())));
const FIRST_CHUNK = 60;
const NEXT_CHUNK = 200;
const count = ref(FIRST_CHUNK);
const visible = computed(() => shown.value.slice(0, count.value));
let growing: ReturnType<typeof setTimeout> | undefined;
function grow(): void {
    clearTimeout(growing);
    if (count.value >= shown.value.length) return;
    growing = setTimeout(() => {
        count.value += NEXT_CHUNK;
        grow();
    }, count.value === FIRST_CHUNK ? 400 : 40);
}
watch(shown, (rows) => {
    count.value = Math.max(FIRST_CHUNK, Math.min(count.value, rows.length));
    grow();
}, { immediate: true });
onBeforeUnmount(() => clearTimeout(growing));
const statusText = (record: CostRecordRow): string => (record.id === props.chosen && props.draft !== null ? t('allocation_page.draft', { percentage: props.draft }) : t(`allocation_page.status_${record.status}`));
const allocated = (record: CostRecordRow): boolean => record.status === 'allocated' && !(record.id === props.chosen && props.draft !== null);
</script>

<template>
    <section role="region" aria-labelledby="allocation-records" class="min-w-0">
        <div class="flex items-baseline justify-between gap-2">
            <h2 id="allocation-records" class="font-display text-[13px] font-semibold">{{ year === null ? t('allocation_page.records') : t('allocation_page.records_year', { year }) }}</h2>
            <span class="text-[12px] text-muted">{{ t('allocation_page.progress', { done, total: records.length }) }}</span>
        </div>
        <div class="mt-2.5 flex gap-2">
            <div class="min-w-0 flex-1"><SearchField id="allocation-records-search" v-model="search" :label="t('list.search')" :placeholder="t('allocation_page.records_search')" /></div>
            <AppButton :variant="unallocatedOnly ? 'primary' : 'secondary'" :aria-pressed="unallocatedOnly" aria-describedby="allocation-filter-hint" :title="t('allocation_page.filter_hint')" @click="unallocatedOnly = !unallocatedOnly">{{ t('allocation_page.filter') }}</AppButton>
        </div>
        <p id="allocation-filter-hint" class="sr-only">{{ t('allocation_page.filter_hint') }}</p>
        <ul class="mt-2.5 space-y-1.5">
            <li v-for="record in visible" :key="record.id">
                <button type="button" :aria-current="record.id === chosen ? 'true' : undefined" class="block w-full rounded-card bg-white text-left" :class="record.id === chosen ? 'border-2 border-ink px-[11px] py-[9px]' : 'border border-hairline px-3 py-2.5'" @click="emit('choose', record.id)">
                    <span class="flex items-baseline justify-between gap-2 text-[13px]">
                        <span class="min-w-0 font-semibold text-ink">{{ record.name }}</span>
                        <span class="shrink-0 text-secondary">{{ formatAmount(record.budgetCents) }}</span>
                    </span>
                    <span class="mt-0.5 flex items-center justify-between gap-2 text-[12px]">
                        <span class="min-w-0 text-muted">{{ record.costKind ? t(`cost_kind.${record.costKind}`) : '' }}</span>
                        <span class="flex shrink-0 items-center gap-1 font-semibold" :class="allocated(record) ? 'text-success' : 'text-warning'" :data-status="record.status">
                            <span aria-hidden="true" class="inline-block h-2 w-2 rounded-full" :class="allocated(record) ? 'bg-success' : 'border-2 border-warning'" />
                            {{ statusText(record) }}
                        </span>
                    </span>
                </button>
            </li>
        </ul>
    </section>
</template>
