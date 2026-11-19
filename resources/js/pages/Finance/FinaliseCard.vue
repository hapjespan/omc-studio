<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { t } from '../../i18n';
import { routes } from '../../routes';

const props = defineProps<{ canvasId: number; year: number; remaining: number; finalised: boolean; writable: boolean }>();
const page = usePage();
const refusal = computed(() => (page.props.errors as Record<string, string>).rule ?? null);
const text = computed(() => (props.finalised ? t('finalise.card_final', { year: props.year }) : props.remaining > 0 ? t('finalise.card_text', { count: props.remaining }) : t('finalise.card_ready')));

function finalise(): void {
    router.post(routes.finaliseYear(props.canvasId, props.year), {}, { preserveScroll: true });
}
</script>

<template>
    <section role="region" aria-labelledby="finalise-card-title" class="rounded-md border border-[#848B95] p-3 text-[13px]">
        <h2 id="finalise-card-title" class="text-[14px] font-semibold">{{ t('finalise.card_title') }}</h2>
        <p class="mt-1">{{ text }}</p>
        <p v-if="refusal" role="alert" class="mt-1 text-[#A12A1E]">{{ refusal }}</p>
        <button v-if="!finalised && writable" type="button" :disabled="remaining > 0" class="mt-2 rounded-md border border-[#848B95] px-3 py-1.5 font-semibold disabled:opacity-60" @click="finalise">{{ t('finalise.card_button') }}</button>
    </section>
</template>
