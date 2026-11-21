<script setup lang="ts">
import { ref } from 'vue';
import { t } from '../../i18n';
import AppButton from './AppButton.vue';

withDefaults(defineProps<{ size?: 'control' | 'dense' }>(), { size: 'control' });
const emit = defineEmits<{ keep: []; discard: [] }>();
const keep = ref<InstanceType<typeof AppButton>>();

defineExpose({ focus: () => keep.value?.focus() });
</script>

<template>
    <div role="alertdialog" :aria-label="t('dialog.discard_title')" class="mt-3 rounded-card border border-edge p-3">
        <p class="text-[13px] font-semibold">{{ t('dialog.discard_title') }}</p>
        <div class="mt-2 flex gap-2">
            <AppButton ref="keep" :size="size" @click="emit('keep')">{{ t('dialog.keep_editing') }}</AppButton>
            <AppButton variant="danger" :size="size" @click="emit('discard')">{{ t('dialog.discard') }}</AppButton>
        </div>
    </div>
</template>
