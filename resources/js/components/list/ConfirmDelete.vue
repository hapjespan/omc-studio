<script lang="ts">
export const matchesName = (typed: string, name: string): boolean => typed.trim() !== '' && typed.trim() === name;
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { t } from '../../i18n';
import TextField from '../fields/TextField.vue';
import Dialog from '../overlays/Dialog.vue';

const props = defineProps<{ name: string; consequence: string | null; requireName?: boolean }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: [] }>();
const typed = ref('');
const allowed = computed(() => !props.requireName || matchesName(typed.value, props.name));

watch(open, () => (typed.value = ''));
</script>

<template>
    <Dialog v-model:open="open" :title="t('list.delete_title', { name })" :description="consequence ?? t('list.delete_none')">
        <TextField v-if="requireName" id="confirm-delete-name" v-model="typed" :label="t('list.delete_name_field')" :hint="t('list.delete_type_name', { name })" autocomplete="off" />
        <div class="mt-3 flex gap-2">
            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="open = false">{{ t('list.cancel') }}</button>
            <button type="button" :disabled="!allowed" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E] disabled:opacity-60" @click="emit('confirm'); open = false">{{ t('list.delete') }}</button>
        </div>
    </Dialog>
</template>
