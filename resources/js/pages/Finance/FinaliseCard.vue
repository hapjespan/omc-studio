<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { t } from '../../i18n';
import AppButton from '../../components/ui/AppButton.vue';
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
    <section role="region" aria-labelledby="finalise-card-title" class="rounded-panel border border-hairline bg-white px-4 py-3.5 text-[12.5px]">
        <h2 id="finalise-card-title" class="sr-only">{{ t('finalise.card_title') }}</h2>
        <p>{{ text }}</p>
        <p v-if="refusal" role="alert" class="mt-1 text-danger">{{ refusal }}</p>
        <AppButton v-if="!finalised && writable" class="mt-2.5" icon="lock" :disabled="remaining > 0" @click="finalise">{{ t('finalise.card_button') }}</AppButton>
    </section>
</template>
