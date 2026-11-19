<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatAmount } from '../../format';
import { t } from '../../i18n';
import type { CostRecordRow } from '../../allocation/types';

const props = defineProps<{ records: CostRecordRow[]; chosen: string | null }>();
const emit = defineEmits<{ choose: [id: string] }>();
const search = ref('');
const shown = computed(() => props.records.filter((record) => `${record.code ?? ''} ${record.name}`.toLowerCase().includes(search.value.trim().toLowerCase())));
</script>

<template>
    <section aria-labelledby="allocation-records" class="min-w-0">
        <h2 id="allocation-records" class="text-[15px] font-semibold">{{ t('allocation_page.records') }}</h2>
        <label for="allocation-records-search" class="mt-2 block text-[13px]">{{ t('list.search') }}</label>
        <input id="allocation-records-search" v-model="search" type="search" class="mt-1 w-full rounded-md border border-[#848B95] bg-white px-2 py-1.5 text-[13px]" />
        <ul class="mt-2 space-y-1">
            <li v-for="record in shown" :key="record.id">
                <button type="button" :aria-current="record.id === chosen ? 'true' : undefined" class="w-full rounded-md border px-2 py-1.5 text-left text-[13px]" :class="record.id === chosen ? 'border-[#171B21] bg-[#EEF1F5]' : 'border-[#848B95]'" @click="emit('choose', record.id)">
                    <span class="block font-semibold">{{ record.name }}</span>
                    <span class="block">{{ formatAmount(record.budgetCents) }}</span>
                    <span class="flex items-center gap-1.5" :data-status="record.status">
                        <span aria-hidden="true" class="inline-block h-2.5 w-2.5 rounded-full" :class="record.status === 'allocated' ? 'bg-[#1F7A3D]' : 'bg-[#8A5A00]'" />
                        {{ t(`allocation_page.status_${record.status}`) }}
                    </span>
                </button>
            </li>
        </ul>
    </section>
</template>
