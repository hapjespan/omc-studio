<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ChipSelect from '../components/ui/ChipSelect.vue';
import { t } from '../i18n';

type YearOption = { year: number; state: 'open' | 'final' | null };

const page = usePage<{ fiscalYears?: { selected: number | null; options: YearOption[] } }>();
const years = computed(() => page.props.fiscalYears ?? null);
const label = (option: YearOption): string => (option.state ? `${option.year} (${t(`shell.year.${option.state}`)})` : String(option.year));
const options = computed(() => (years.value?.options ?? []).map((option) => ({ value: String(option.year), label: label(option) })));
const chosen = computed({
    get: () => String(years.value?.selected ?? ''),
    set: (year: string) => {
        const params = Object.fromEntries(new URLSearchParams(window.location.search));
        router.get(window.location.pathname, { ...params, year }, { preserveScroll: true, preserveState: false });
    },
});
</script>

<template>
    <ChipSelect v-if="years && years.options.length" id="year-selector" v-model="chosen" :label="t('shell.year.label')" :options="options" />
</template>
