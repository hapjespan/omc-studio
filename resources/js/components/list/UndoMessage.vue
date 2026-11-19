<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { t } from '../../i18n';
import { Countdown } from './countdown';

const UNDO_WINDOW_MS = 10_000;

const props = defineProps<{ message: string; refusal?: string | null }>();
const emit = defineEmits<{ undo: []; close: [] }>();
const button = ref<HTMLButtonElement>();
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
    <div role="status" class="flex flex-wrap items-center gap-3 rounded-md border border-[#848B95] bg-white p-3 text-[13px]" @mouseenter="countdown.hold('pointer', true)" @mouseleave="countdown.hold('pointer', false)" @focusin="countdown.hold('focus', true)" @focusout="countdown.hold('focus', false)">
        <p v-if="refusal" role="alert" class="font-medium text-[#A12A1E]">{{ refusal }}</p>
        <p v-else id="undo-message">{{ message }}</p>
        <button v-if="refusal" ref="button" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 font-semibold" @click="emit('close')">{{ t('dialog.close') }}</button>
        <button v-else ref="button" type="button" aria-describedby="undo-message" class="rounded-md border border-[#848B95] px-3 py-1.5 font-semibold" @click="emit('undo')">{{ t('list.undo') }}</button>
    </div>
</template>
