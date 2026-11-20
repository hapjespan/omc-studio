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
import { computed, reactive } from 'vue';
import AppButton from '../../components/ui/AppButton.vue';
import ChipSelect from '../../components/ui/ChipSelect.vue';
import { visitOverlay } from './query';

const props = defineProps<{ filters: OverlayFilters; options: FilterOptions }>();
const applied = computed(() => ({ cost_kind: props.filters.costKind ?? '', owner: props.filters.ownerId === null ? '' : String(props.filters.ownerId), application: props.filters.applicationId === null ? '' : String(props.filters.applicationId) }));
const draft = reactive({ ...applied.value });
const dirty = computed(() => draft.cost_kind !== applied.value.cost_kind || draft.owner !== applied.value.owner || draft.application !== applied.value.application);
const choices = (entries: { value: string; label: string }[]): { value: string; label: string }[] => [{ value: '', label: t('overlay.filter.all') }, ...entries];
const apply = (): void => visitOverlay({ ...draft });
</script>

<template>
    <div data-overlay-filters>
        <form class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <ChipSelect id="filter-cost-kind" v-model="draft.cost_kind" :label="t('overlay.filter.cost_kind_short')" :name="t('overlay.filter.cost_kind')" :options="choices(options.costKinds.map((kind) => ({ value: kind, label: t(`cost_kind.${kind}`) })))" />
            <ChipSelect id="filter-owner" v-model="draft.owner" :label="t('overlay.filter.owner')" :options="choices(options.owners.map((owner) => ({ value: String(owner.id), label: owner.name })))" />
            <ChipSelect id="filter-application" v-model="draft.application" :label="t('overlay.filter.application')" :options="choices(options.applications.map((application) => ({ value: String(application.id), label: application.name })))" />
            <AppButton v-if="dirty" type="submit">{{ t('overlay.filter.apply') }}</AppButton>
        </form>
        <p v-if="activeFilters(props.filters, options).length" role="status" class="sr-only" data-active-filters>{{ t('overlay.filter.active') }}: {{ activeFilters(props.filters, options).join('; ') }}</p>
    </div>
</template>
