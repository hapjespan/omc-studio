<script setup lang="ts">
import { computed, ref } from 'vue';
import { t } from '../../i18n';
import type { TargetRow } from '../../allocation/types';
import TargetNode from './TargetNode.vue';

const props = defineProps<{ targets: TargetRow[]; inSet: string[]; disabled: boolean; figuresAvailable: boolean }>();
const emit = defineEmits<{ add: [id: string] }>();
const search = ref('');
const set = computed(() => new Set(props.inSet));
</script>

<template>
    <section role="region" aria-labelledby="allocation-targets" class="min-w-0">
        <h2 id="allocation-targets" class="text-[15px] font-semibold">{{ t('allocation_page.targets') }}</h2>
        <p class="mt-1 text-[13px]">{{ t('allocation_page.drag_hint') }}</p>
        <p v-if="!figuresAvailable" role="status" class="mt-1 text-[13px] text-[#8A5A00]">{{ t('allocation_page.total_hidden') }}</p>
        <label for="allocation-targets-search" class="mt-2 block text-[13px]">{{ t('allocation_page.targets_search') }}</label>
        <input id="allocation-targets-search" v-model="search" type="search" class="mt-1 w-full rounded-md border border-[#848B95] bg-white px-2 py-1.5 text-[13px]" />
        <div role="tree" :aria-label="t('allocation_page.targets')" class="mt-2">
            <TargetNode :rows="targets" :in-set="set" :needle="search.trim().toLowerCase()" :disabled="disabled" :hide-totals="!figuresAvailable" @add="emit('add', $event)" />
        </div>
    </section>
</template>
