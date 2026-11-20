<script lang="ts">
import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { formatPercentage } from '../../allocation/helpers';
import { formatAmount, formatBasisPoints, formatWholeEuros } from '../../format';
import type { Contribution } from './figures';

const cache = new Map<string, Promise<Contribution[]>>();
if (typeof window !== 'undefined') router.on('success', () => cache.clear());

export function loadContributions(pageUrl: string, elementId: string, version: string | null, fetcher: typeof fetch = fetch, component = 'Canvas/View'): Promise<Contribution[]> {
    const [path, query = ''] = pageUrl.split('?');
    const params = new URLSearchParams(query);
    params.set('element', elementId);
    const url = `${path}?${params}`;
    const key = `${version}|${component}|${url}`;
    if (!cache.has(key)) {
        cache.set(key, fetcher(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Inertia': 'true', 'X-Inertia-Version': version ?? '', 'X-Inertia-Partial-Component': component, 'X-Inertia-Partial-Data': 'contributions' } })
            .then((response) => response.json())
            .then((body: { props: { contributions: Contribution[] } }) => body.props.contributions)
            .catch(() => {
                cache.delete(key);
                return [];
            }));
    }
    return cache.get(key)!;
}

export function useContributions(elementId: () => string, pageUrl: () => string, version: () => string | null, fetcher: typeof fetch = fetch, component: () => string = () => 'Canvas/View'): Ref<Contribution[]> {
    const loaded = ref<Contribution[]>([]);
    watch([elementId, pageUrl], async ([id, url], _previous, onCleanup) => {
        let current = true;
        onCleanup(() => (current = false));
        loaded.value = [];
        const rows = await loadContributions(url, id, version(), fetcher, component());
        if (current) loaded.value = rows;
    }, { immediate: true });
    return loaded;
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
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { t } from '../../i18n';

const props = defineProps<{ elementId: string; cents?: boolean }>();
const page = usePage();
const loaded = useContributions(() => props.elementId, () => page.url, () => page.version, fetch, () => page.component);
const rows = computed(() => breakdownRows(loaded.value, props.cents ?? false));
</script>

<template>
    <div v-if="rows.length" class="mt-2 flex flex-col gap-1" data-breakdown>
        <p class="text-[11px] font-semibold leading-[15.4px] text-secondary">{{ t('overlay.breakdown') }}</p>
        <ul class="flex flex-col gap-1 text-[12px] leading-[16.8px]">
            <li v-for="row in rows" :key="row.id" class="flex justify-between gap-4">
                <span class="min-w-0">{{ row.name }}</span>
                <span class="whitespace-nowrap"><span class="text-secondary">{{ row.percentage }}</span> · <span class="font-semibold">{{ row.amount }}</span></span>
            </li>
        </ul>
    </div>
</template>
