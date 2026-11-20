<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { t } from '../../i18n';
import AppButton from '../ui/AppButton.vue';
import IconButton from '../ui/IconButton.vue';

defineProps<{ title: string; eyebrow?: string | null }>();
const emit = defineEmits<{ close: [] }>();
const titleId = useId();
const dirty = ref(false);
const confirming = ref(false);
const heading = ref<HTMLElement>();
const keepEditing = ref<InstanceType<typeof AppButton>>();
const opener = document.activeElement as HTMLElement | null;

onMounted(() => heading.value?.focus());
onBeforeUnmount(() => opener?.isConnected && opener.focus());

async function requestClose(): Promise<void> {
    if (!dirty.value) return emit('close');
    confirming.value = true;
    await nextTick();
    keepEditing.value?.focus();
}
</script>

<template>
    <section role="dialog" :aria-labelledby="titleId" class="rounded-panel border border-hairline bg-white p-4" @keydown.esc.stop="requestClose">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p v-if="eyebrow" class="text-[11.5px] text-muted">{{ eyebrow }}</p>
                <h2 :id="titleId" ref="heading" tabindex="-1" class="font-display text-[18px] font-semibold tracking-[-0.2px]">{{ title }}</h2>
            </div>
            <IconButton icon="close" :label="t('dialog.close')" @click="requestClose" />
        </div>
        <div class="mt-3"><slot :close="() => emit('close')" :set-dirty="(value: boolean) => (dirty = value)" /></div>
        <div v-if="confirming" role="alertdialog" :aria-label="t('dialog.discard_title')" class="mt-3 rounded-card border border-edge p-3">
            <p class="text-[13px] font-semibold">{{ t('dialog.discard_title') }}</p>
            <div class="mt-2 flex gap-2">
                <AppButton ref="keepEditing" @click="confirming = false">{{ t('dialog.keep_editing') }}</AppButton>
                <AppButton variant="danger" @click="emit('close')">{{ t('dialog.discard') }}</AppButton>
            </div>
        </div>
    </section>
</template>
