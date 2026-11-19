<script lang="ts">
import { formatPercentage } from '../../allocation/helpers';
import { formatAmount, formatBasisPoints, formatWholeEuros } from '../../format';
import type { Contribution } from './figures';

const cache = new Map<string, Promise<Contribution[]>>();

export function loadContributions(search: string, elementId: string, version: string | null, fetcher: typeof fetch = fetch): Promise<Contribution[]> {
    const params = new URLSearchParams(search);
    params.set('element', elementId);
    const url = `${window.location.pathname}?${params}`;
    const key = `${version}|${url}`;
    if (!cache.has(key)) {
        cache.set(key, fetcher(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Inertia': 'true', 'X-Inertia-Version': version ?? '', 'X-Inertia-Partial-Component': 'Canvas/View', 'X-Inertia-Partial-Data': 'contributions' } })
            .then((response) => response.json())
            .then((body: { props: { contributions: Contribution[] } }) => body.props.contributions)
            .catch(() => {
                cache.delete(key);
                return [];
            }));
    }
    return cache.get(key)!;
}

export function breakdownRows(contributions: Contribution[], cents: boolean): { id: string; name: string; percentage: string; amount: string }[] {
    return [...contributions].sort((a, b) => b.amountCents - a.amountCents).map((row) => ({
        id: row.id,
        name: row.name,
        percentage: cents ? formatPercentage(row.basisPoints) : formatBasisPoints(row.basisPoints),
        amount: cents ? formatAmount(row.amountCents) : formatWholeEuros(row.amountCents),
    }));
}
</script>

<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { t } from '../../i18n';

const props = defineProps<{ elementId: string; cents?: boolean }>();
const page = usePage();
const loaded = ref<Contribution[]>([]);
watchEffect(async (onCleanup) => {
    let current = true;
    onCleanup(() => (current = false));
    const rows = await loadContributions(window.location.search, props.elementId, page.version);
    if (current) loaded.value = rows;
});
const rows = computed(() => breakdownRows(loaded.value, props.cents ?? false));
</script>

<template>
    <div v-if="rows.length" class="mt-2" data-breakdown>
        <p class="font-semibold">{{ t('overlay.breakdown') }}</p>
        <ul>
            <li v-for="row in rows" :key="row.id" class="flex justify-between gap-4">
                <span>{{ row.name }}</span>
                <span class="whitespace-nowrap">{{ row.percentage }} · {{ row.amount }}</span>
            </li>
        </ul>
    </div>
</template>
