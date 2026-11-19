<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { t } from '../../i18n';
import AppButton from '../ui/AppButton.vue';
import { Countdown } from './countdown';

const UNDO_WINDOW_MS = 10_000;

const props = defineProps<{ message: string; refusal?: string | null }>();
const emit = defineEmits<{ undo: []; close: [] }>();
const button = ref<InstanceType<typeof AppButton>>();
const countdown = new Countdown(UNDO_WINDOW_MS, () => emit('close'));

onMounted(() => {
    button.value?.focus();
    if (props.refusal) return;
    countdown.start();
});
watch(() => props.refusal, async (refusal) => {
    if (!refusal) return;
    countdown.stop();
    await nextTick();
    button.value?.focus();
});
onBeforeUnmount(() => countdown.stop());
</script>

<template>
    <div class="flex flex-wrap items-center gap-3 rounded-md border border-edge bg-white p-3 text-[13px]" @mouseenter="countdown.hold('pointer', true)" @mouseleave="countdown.hold('pointer', false)" @focusin="countdown.hold('focus', true)" @focusout="countdown.hold('focus', false)">
        <p v-if="refusal" role="alert" class="font-medium text-danger">{{ refusal }}</p>
        <p v-else id="undo-message" role="status">{{ message }}</p>
        <AppButton v-if="refusal" ref="button" @click="emit('close')">{{ t('dialog.close') }}</AppButton>
        <AppButton v-else ref="button" aria-describedby="undo-message" @click="emit('undo')">{{ t('list.undo') }}</AppButton>
    </div>
</template>
