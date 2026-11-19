<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import Dialog from '../../components/overlays/Dialog.vue';
import { refusalOf } from '../../components/upload';
import { formatPercentage } from '../../allocation/helpers';
import { indexTargets, type TargetRow } from '../../allocation/types';
import { plural, t } from '../../i18n';
import { routes } from '../../routes';
import TargetNode from '../Finance/TargetNode.vue';
import { withoutSubtree, type EntryElement, type RedistributionChoice, type RedistributionYear } from './entry';

const props = defineProps<{ canvasId: number; element: EntryElement; targets: TargetRow[]; done: () => void }>();
const open = defineModel<boolean>('open', { default: false });
const years = ref<RedistributionYear[]>([]);
const choices = ref<Record<number, RedistributionChoice>>({});
const refusal = ref('');
const loaded = ref(false);
const outside = computed(() => withoutSubtree(props.targets, String(props.element.id)));
const info = computed(() => indexTargets(outside.value));
const noMatch = new Set<string>();
const ready = computed(() => loaded.value && years.value.length > 0 && years.value.every(({ year }) => choices.value[year]?.mode === 'unknown' || (choices.value[year]?.mode === 'move' && choices.value[year].target !== null)));

watch(open, async (opened) => {
    if (!opened) return;
    years.value = [];
    choices.value = {};
    refusal.value = '';
    loaded.value = false;
    const response = await fetch(routes.elementRedistribution(props.canvasId, props.element.id), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!response.ok) {
        refusal.value = await refusalOf(response);
        void announce(refusal.value);
        return;
    }
    years.value = (await response.json()).years;
    loaded.value = true;
});

const mode = (year: number, next: 'move' | 'unknown'): void => void (choices.value = { ...choices.value, [year]: { mode: next, target: next === 'move' ? (choices.value[year]?.target ?? null) : null } });
const pick = (year: number, id: string): void => void (choices.value = { ...choices.value, [year]: { mode: 'move', target: id } });

function confirm(): void {
    refusal.value = '';
    router.post(routes.elementRedistribution(props.canvasId, props.element.id), { choices: choices.value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            open.value = false;
            props.done();
        },
        onError: (errors) => {
            refusal.value = errors.rule ?? Object.values(errors)[0] ?? '';
            void announce(refusal.value);
        },
    });
}
const button = 'rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]';
</script>

<template>
    <Dialog v-model:open="open" :title="t('redistribute.title', { name: element.name })" :description="t('redistribute.description')">
        <p v-if="refusal" role="alert" class="mb-2 text-[12.5px] font-medium text-[#A12A1E]">{{ refusal }}</p>
        <p v-if="!loaded && !refusal" role="status" class="text-[13px]">{{ t('redistribute.loading') }}</p>
        <form class="flex flex-col gap-4" novalidate @submit.prevent="confirm">
            <fieldset v-for="item in years" :key="item.year" class="rounded-md border border-[#848B95] p-3">
                <legend class="px-1 text-[13px] font-semibold">{{ t('redistribute.year', { year: item.year, records: plural('redistribute.records', item.records), percentage: formatPercentage(item.basis_points) }) }}</legend>
                <label class="flex items-center gap-2 text-[13px]"><input type="radio" :name="`redistribute-${item.year}`" :checked="choices[item.year]?.mode === 'move'" @change="mode(item.year, 'move')" />{{ t('redistribute.move') }}</label>
                <label class="mt-1 flex items-center gap-2 text-[13px]"><input type="radio" :name="`redistribute-${item.year}`" :checked="choices[item.year]?.mode === 'unknown'" @change="mode(item.year, 'unknown')" />{{ t('redistribute.unknown') }}</label>
                <div v-if="choices[item.year]?.mode === 'move'" class="mt-2">
                    <p class="text-[13px]" role="status">{{ choices[item.year].target ? t('redistribute.chosen_target', { path: info[choices[item.year].target as string]?.path ?? '' }) : t('redistribute.no_target') }}</p>
                    <div role="tree" :aria-label="t('redistribute.targets', { year: item.year })" class="mt-1 max-h-56 overflow-y-auto">
                        <TargetNode :rows="outside" :in-set="noMatch" needle="" :disabled="false" :hide-totals="true" :chosen="choices[item.year].target ?? ''" @add="pick(item.year, $event)" />
                    </div>
                </div>
            </fieldset>
            <div class="flex gap-2">
                <button type="button" :class="button" @click="open = false">{{ t('list.cancel') }}</button>
                <button type="submit" :disabled="!ready" :class="[button, 'border-[#C0645A] text-[#A12A1E] disabled:opacity-60']">{{ t('redistribute.confirm') }}</button>
            </div>
        </form>
    </Dialog>
</template>
