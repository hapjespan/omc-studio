<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { t } from '../i18n';

type YearOption = { year: number; state: 'open' | 'final' | null };

const page = usePage<{ fiscalYears?: { selected: number | null; options: YearOption[] } }>();
const years = computed(() => page.props.fiscalYears ?? null);
const label = (option: YearOption): string => (option.state ? `${option.year} (${t(`shell.year.${option.state}`)})` : String(option.year));

function change(event: Event): void {
    const params = Object.fromEntries(new URLSearchParams(window.location.search));
    router.get(window.location.pathname, { ...params, year: (event.target as HTMLSelectElement).value }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <div v-if="years && years.options.length" class="flex items-center gap-2 text-[13px]">
        <label for="year-selector" class="font-semibold">{{ t('shell.year.label') }}</label>
        <select id="year-selector" :value="years.selected ?? ''" class="rounded-md border border-[#848B95] bg-white px-2 py-1" @change="change">
            <option v-for="option in years.options" :key="option.year" :value="option.year">{{ label(option) }}</option>
        </select>
    </div>
</template>
