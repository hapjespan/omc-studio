<script setup lang="ts">
import { computed, ref } from 'vue';
import { activitiesOf } from '../../allocation/helpers';
import type { TargetRow } from '../../allocation/types';
import SelectField from '../../components/fields/SelectField.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';

const props = defineProps<{ count: number; domains: TargetRow[]; disabled: boolean }>();
const emit = defineEmits<{ even: []; domain: [id: string] }>();
const open = ref(false);
const picked = ref('');
const options = computed(() => props.domains.map((domain) => ({ value: String(domain.id), label: domain.name })));
const empty = computed(() => picked.value !== '' && activitiesOf(props.domains.find((domain) => String(domain.id) === picked.value) ?? { id: '', kind: 'domain' }).length === 0);

function apply(): void {
    if (picked.value === '' || empty.value) return;
    emit('domain', picked.value);
    open.value = false;
}
</script>

<template>
    <div class="relative flex flex-wrap items-center gap-2" @keydown.esc="open = false">
        <span class="text-[13px] text-muted">{{ t('allocation_page.quick') }}</span>
        <AppButton :disabled="disabled || count < 1" @click="emit('even')">{{ t('allocation_page.even', { count }) }}</AppButton>
        <AppButton :disabled="disabled" :aria-expanded="open" aria-controls="allocation-quick" @click="open = !open">{{ t('allocation_page.over_domain') }}</AppButton>
        <div v-if="open" id="allocation-quick" role="group" :aria-label="t('allocation_page.quick')" class="absolute left-0 top-full z-10 mt-1 w-72 rounded-card border border-hairline bg-white p-3 text-[13px] shadow-[0_1px_3px_rgba(0,0,0,.12)]">
            <SelectField id="allocation-quick-domain" v-model="picked" :label="t('allocation_page.pick_domain')" :options="options" />
            <p v-if="empty" role="status" class="mt-1 text-warning">{{ t('allocation_page.domain_empty') }}</p>
            <AppButton class="mt-2" variant="primary" :disabled="picked === '' || empty" @click="apply">{{ t('allocation_page.over_domain') }}</AppButton>
        </div>
    </div>
</template>
