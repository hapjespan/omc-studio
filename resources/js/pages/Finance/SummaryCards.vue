<script setup lang="ts">
import { formatShare, formatAmount } from '../../format';
import { plural, t } from '../../i18n';
import Panel from '../../components/ui/Panel.vue';

export type Cards = { budgetCents: number; forecastCents: number; allocated: { budget: number; count: number }; unknown: { budget: number; count: number } };

defineProps<{ cards: Cards }>();
</script>

<template>
    <section role="region" :aria-label="t('cost_records.cards')" class="grid gap-3 sm:grid-cols-3">
        <Panel>
            <p class="text-[11.5px] text-muted">{{ t('cost_records.card_budget') }}</p>
            <p class="mt-1 font-display text-[22px] font-bold tracking-[-0.22px]">{{ formatAmount(cards.budgetCents) }}</p>
            <p class="mt-1 text-[12px] text-secondary">{{ t('fields.forecast') }} {{ formatAmount(cards.forecastCents) }}</p>
        </Panel>
        <Panel>
            <p class="text-[11.5px] text-muted">{{ t('allocation_page.status_allocated') }}</p>
            <p class="mt-1 font-display text-[22px] font-bold tracking-[-0.22px]">{{ formatAmount(cards.allocated.budget) }}</p>
            <p class="mt-1 text-[12px] text-secondary">{{ t('cost_records.card_of', { done: cards.allocated.count, total: cards.allocated.count + cards.unknown.count }) }}</p>
        </Panel>
        <Panel>
            <p class="text-[11.5px] text-muted">{{ t('allocation_page.status_unallocated') }}</p>
            <p class="mt-1 font-display text-[22px] font-bold tracking-[-0.22px]">{{ formatAmount(cards.unknown.budget) }}</p>
            <p class="mt-1 text-[12px] text-secondary">{{ plural('cost_records.card_count', cards.unknown.count) }}, {{ formatShare(cards.unknown.budget, cards.budgetCents) }}</p>
        </Panel>
    </section>
</template>
