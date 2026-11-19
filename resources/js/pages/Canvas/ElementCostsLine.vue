<script setup lang="ts">
import { computed } from 'vue';
import { formatAmount } from '../../format';
import { plural, t } from '../../i18n';
import type { ElementCost } from './entry';

const props = defineProps<{ year: number | null; cost?: ElementCost }>();
const text = computed(() => {
    const { year, cost } = props;
    if (!cost) return year === null ? t('entry_costs.none_no_year') : t('entry_costs.none', { year });
    if (year === null || cost.records === 0) return t('entry_costs.other_years', { year: year ?? '', years: cost.years.join(', ') });

    return t('entry_costs.allocated', { amount: formatAmount(cost.cents), year, records: plural('entry_costs.records', cost.records) });
});
</script>

<template>
    <p data-testid="element-costs" class="text-[12.5px] text-[#454C57]">{{ text }}</p>
</template>
