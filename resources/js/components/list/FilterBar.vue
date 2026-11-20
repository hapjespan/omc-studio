<script setup lang="ts">
import { computed, useId } from 'vue';
import { t } from '../../i18n';
import AppButton from '../ui/AppButton.vue';
import ChipSelect from '../ui/ChipSelect.vue';

export type FilterDef = { name: string; label: string; short?: string; options: { value: string; label: string }[] };

const props = defineProps<{ filters: FilterDef[]; active: Record<string, string> }>();
const emit = defineEmits<{ change: [filters: Record<string, string>] }>();

const prefix = useId();
const chips = computed(() =>
    props.filters.flatMap((filter) => {
        const option = filter.options.find((candidate) => candidate.value === props.active[filter.name]);
        return option ? [{ name: filter.name, label: filter.label, value: option.label }] : [];
    }),
);

function set(name: string, value: string): void {
    const next = { ...props.active };
    if (value === '') delete next[name];
    else next[name] = value;
    emit('change', next);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-2">
            <ChipSelect v-for="filter in filters" :id="`${prefix}-${filter.name}`" :key="filter.name" :label="filter.short ?? filter.label" :model-value="active[filter.name] ?? ''" :options="[{ value: '', label: t('list.filter_all') }, ...filter.options]" @update:model-value="set(filter.name, $event)" />
        </div>
        <ul v-if="chips.length" class="flex flex-wrap gap-2">
            <li v-for="chip in chips" :key="chip.name">
                <AppButton size="small" @click="set(chip.name, '')">{{ chip.label }}: {{ chip.value }}<span class="sr-only">. {{ t('list.filter_remove', { filter: chip.label, value: chip.value }) }}</span><span aria-hidden="true"> ×</span></AppButton>
            </li>
        </ul>
    </div>
</template>
