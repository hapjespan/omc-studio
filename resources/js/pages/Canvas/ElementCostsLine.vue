<script setup lang="ts">
import { computed } from 'vue';
import { formatAmount } from '../../format';
import { plural, t } from '../../i18n';
import type { ElementCost } from './entry';

const props = defineProps<{ year: number | null; cost?: ElementCost; loaded: boolean }>();
const text = computed(() => {
    const { year, cost } = props;
    if (!props.loaded) return t('entry_costs.loading');
    if (!cost) return year === null ? t('entry_costs.none_no_year') : t('entry_costs.none', { year });
    const others = cost.years.filter((other) => other !== year).join(', ');
    if (cost.cents === null) return t('entry_costs.no_amount', { years: cost.years.join(', ') });
    if (cost.records === 0) return others ? t('entry_costs.other_years', { year: year ?? '', years: others }) : t('entry_costs.no_amount', { years: cost.years.join(', ') });

    return t('entry_costs.allocated', { amount: formatAmount(cost.cents), year: year ?? '', records: plural('entry_costs.records', cost.records) });
});
</script>

<template>
    <p data-testid="element-costs" class="text-[12.5px] text-secondary" :aria-busy="loaded ? undefined : 'true'">{{ text }}</p>
</template>
