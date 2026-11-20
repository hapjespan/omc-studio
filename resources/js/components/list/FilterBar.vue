<script setup lang="ts">
import { computed, useId } from 'vue';
import { t } from '../../i18n';
import AppButton from '../ui/AppButton.vue';

export type FilterDef = { name: string; label: string; options: { value: string; label: string }[] };

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
        <div class="flex flex-wrap items-center gap-3">
            <div v-for="filter in filters" :key="filter.name" class="flex flex-col gap-1">
                <label :for="`${prefix}-${filter.name}`" class="text-[13px] font-semibold">{{ filter.label }}</label>
                <select :id="`${prefix}-${filter.name}`" :value="active[filter.name] ?? ''" class="h-control rounded-control border border-edge bg-white px-2.5 text-[13px]" @change="set(filter.name, ($event.target as HTMLSelectElement).value)">
                    <option value="">{{ t('list.filter_all') }}</option>
                    <option v-for="option in filter.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
        </div>
        <ul v-if="chips.length" class="flex flex-wrap gap-2">
            <li v-for="chip in chips" :key="chip.name">
                <AppButton size="small" @click="set(chip.name, '')">{{ chip.label }}: {{ chip.value }}<span class="sr-only">. {{ t('list.filter_remove', { filter: chip.label, value: chip.value }) }}</span><span aria-hidden="true"> ×</span></AppButton>
            </li>
        </ul>
    </div>
</template>
