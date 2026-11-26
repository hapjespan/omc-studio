<script lang="ts">
export const matchesName = (typed: string, name: string): boolean => typed.trim() !== '' && typed.trim() === name;
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { t } from '../../i18n';
import TextField from '../fields/TextField.vue';
import Dialog from '../overlays/Dialog.vue';
import AppButton from '../ui/AppButton.vue';

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
            <AppButton @click="open = false">{{ t('list.cancel') }}</AppButton>
            <AppButton variant="danger" :disabled="!allowed" @click="emit('confirm'); open = false">{{ t('list.delete') }}</AppButton>
        </div>
    </Dialog>
</template>
