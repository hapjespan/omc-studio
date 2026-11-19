<script setup lang="ts">
import { computed, ref } from 'vue';
import { activitiesOf } from '../../allocation/helpers';
import type { TargetRow } from '../../allocation/types';
import { t } from '../../i18n';

const props = defineProps<{ count: number; domains: TargetRow[]; disabled: boolean }>();
const emit = defineEmits<{ even: []; domain: [id: string] }>();
const open = ref(false);
const picked = ref('');
const empty = computed(() => picked.value !== '' && activitiesOf(props.domains.find((domain) => String(domain.id) === picked.value) ?? { id: '', kind: 'domain' }).length === 0);

function apply(): void {
    if (picked.value === '' || empty.value) return;
    emit('domain', picked.value);
    open.value = false;
}
</script>

<template>
    <div class="relative">
        <button type="button" :disabled="disabled" :aria-expanded="open" aria-controls="allocation-quick" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] disabled:opacity-60" @click="open = !open" @keydown.esc="open = false">{{ t('allocation_page.quick') }}</button>
        <div v-if="open" id="allocation-quick" role="group" :aria-label="t('allocation_page.quick')" class="absolute z-10 mt-1 w-72 rounded-md border border-[#848B95] bg-white p-3 text-[13px]" @keydown.esc="open = false">
            <button type="button" :disabled="count < 1" class="block w-full rounded-md border border-[#848B95] px-2 py-1.5 text-left disabled:opacity-60" @click="emit('even'); open = false">{{ t('allocation_page.even', { count }) }}</button>
            <p class="mt-3 font-semibold">{{ t('allocation_page.over_domain') }}</p>
            <label for="allocation-quick-domain" class="mt-1 block">{{ t('allocation_page.pick_domain') }}</label>
            <select id="allocation-quick-domain" v-model="picked" class="mt-1 w-full rounded-md border border-[#848B95] bg-white px-2 py-1.5">
                <option value="" />
                <option v-for="domain in domains" :key="domain.id" :value="String(domain.id)">{{ domain.name }}</option>
            </select>
            <p v-if="empty" role="status" class="mt-1 text-[#8A5A00]">{{ t('allocation_page.domain_empty') }}</p>
            <button type="button" :disabled="picked === '' || empty" class="mt-2 rounded-md border border-[#848B95] px-2 py-1.5 font-semibold disabled:opacity-60" @click="apply">{{ t('allocation_page.over_domain') }}</button>
        </div>
    </div>
</template>
