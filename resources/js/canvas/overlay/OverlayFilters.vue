<script lang="ts">
import { t } from '../../i18n';
import type { OverlayFilters } from './figures';

export interface FilterOptions {
    costKinds: string[];
    owners: { id: number; name: string }[];
    applications: { id: number; name: string }[];
}

export function activeFilters(filters: OverlayFilters, options: FilterOptions): string[] {
    const active: string[] = [];
    if (filters.costKind) active.push(`${t('overlay.filter.cost_kind')}: ${t(`cost_kind.${filters.costKind}`)}`);
    const owner = options.owners.find((candidate) => String(candidate.id) === String(filters.ownerId));
    if (owner) active.push(`${t('overlay.filter.owner')}: ${owner.name}`);
    const application = options.applications.find((candidate) => String(candidate.id) === String(filters.applicationId));
    if (application) active.push(`${t('overlay.filter.application')}: ${application.name}`);
    return active;
}
</script>

<script setup lang="ts">
import { reactive } from 'vue';
import { visitOverlay } from './query';

const props = defineProps<{ filters: OverlayFilters; options: FilterOptions }>();
const select = 'rounded-md border border-[#848B95] bg-white px-2 py-1.5';
const draft = reactive({ cost_kind: props.filters.costKind ?? '', owner: props.filters.ownerId === null ? '' : String(props.filters.ownerId), application: props.filters.applicationId === null ? '' : String(props.filters.applicationId) });
const apply = (): void => visitOverlay({ ...draft });
</script>

<template>
    <div class="mt-3" data-overlay-filters>
        <form class="flex flex-wrap items-center gap-4 text-[13px]" @submit.prevent="apply">
            <label for="filter-cost-kind" class="font-semibold">{{ t('overlay.filter.cost_kind') }}</label>
            <select id="filter-cost-kind" v-model="draft.cost_kind" :class="select">
                <option value="">{{ t('overlay.filter.all') }}</option>
                <option v-for="kind in options.costKinds" :key="kind" :value="kind">{{ t(`cost_kind.${kind}`) }}</option>
            </select>
            <label for="filter-owner" class="font-semibold">{{ t('overlay.filter.owner') }}</label>
            <select id="filter-owner" v-model="draft.owner" :class="select">
                <option value="">{{ t('overlay.filter.all') }}</option>
                <option v-for="owner in options.owners" :key="owner.id" :value="String(owner.id)">{{ owner.name }}</option>
            </select>
            <label for="filter-application" class="font-semibold">{{ t('overlay.filter.application') }}</label>
            <select id="filter-application" v-model="draft.application" :class="select">
                <option value="">{{ t('overlay.filter.all') }}</option>
                <option v-for="application in options.applications" :key="application.id" :value="String(application.id)">{{ application.name }}</option>
            </select>
            <button type="submit" class="rounded-md border border-[#848B95] px-3 py-1.5 font-semibold">{{ t('overlay.filter.apply') }}</button>
        </form>
        <p v-if="activeFilters(props.filters, options).length" role="status" class="mt-2 text-[13px]" data-active-filters>{{ t('overlay.filter.active') }}: {{ activeFilters(props.filters, options).join('; ') }}</p>
    </div>
</template>
