<script setup lang="ts">
import { computed, nextTick, ref, useId } from 'vue';
import { t } from '../../i18n';
import AppButton from './AppButton.vue';

const props = withDefaults(defineProps<{ size?: 'control' | 'dense'; titleId?: string }>(), { size: 'control', titleId: undefined });
const emit = defineEmits<{ keep: []; discard: [] }>();
const keep = ref<InstanceType<typeof AppButton>>();
const ownId = useId();
const titleId = computed(() => props.titleId ?? ownId);
const asker = document.activeElement as HTMLElement | null;

function keepEditing(): void {
    emit('keep');
    void nextTick(() => {
        if (asker && asker !== document.body && asker.isConnected) asker.focus();
    });
}

defineExpose({ focus: () => keep.value?.focus() });
</script>

<template>
    <div role="alertdialog" :aria-labelledby="titleId" class="mt-3 rounded-card border border-edge p-3">
        <p :id="titleId" class="text-[13px] font-semibold">{{ t('dialog.discard_title') }}</p>
        <div class="mt-2 flex gap-2">
            <AppButton ref="keep" :size="props.size" @click="keepEditing">{{ t('dialog.keep_editing') }}</AppButton>
            <AppButton variant="danger" :size="props.size" @click="emit('discard')">{{ t('dialog.discard') }}</AppButton>
        </div>
    </div>
</template>
