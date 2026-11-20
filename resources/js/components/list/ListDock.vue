<script setup lang="ts">
import { nextTick, onMounted, ref, useId, watch } from 'vue';
import { t } from '../../i18n';
import AppButton from '../ui/AppButton.vue';
import IconButton from '../ui/IconButton.vue';

const props = defineProps<{ title: string; eyebrow?: string | null; confirming: boolean }>();
const emit = defineEmits<{ requestClose: []; discard: []; keep: [] }>();
const titleId = useId();
const heading = ref<HTMLElement>();
const keepEditing = ref<InstanceType<typeof AppButton>>();

onMounted(() => heading.value?.focus());
watch(() => props.confirming, async (asking) => {
    if (!asking) return;
    await nextTick();
    keepEditing.value?.focus();
});
</script>

<template>
    <section role="dialog" :aria-labelledby="titleId" class="rounded-panel border border-hairline bg-white p-4" @keydown.esc.stop="emit('requestClose')">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p v-if="eyebrow" class="text-[11.5px] text-muted">{{ eyebrow }}</p>
                <h2 :id="titleId" ref="heading" tabindex="-1" class="font-display text-[18px] font-semibold tracking-[-0.2px]">{{ title }}</h2>
            </div>
            <IconButton icon="close" :label="t('dialog.close')" @click="emit('requestClose')" />
        </div>
        <div class="mt-3"><slot /></div>
        <div v-if="confirming" role="alertdialog" :aria-label="t('dialog.discard_title')" class="mt-3 rounded-card border border-edge p-3">
            <p class="text-[13px] font-semibold">{{ t('dialog.discard_title') }}</p>
            <div class="mt-2 flex gap-2">
                <AppButton ref="keepEditing" @click="emit('keep')">{{ t('dialog.keep_editing') }}</AppButton>
                <AppButton variant="danger" @click="emit('discard')">{{ t('dialog.discard') }}</AppButton>
            </div>
        </div>
    </section>
</template>
