<script setup lang="ts">
import CanvasCard from '../../canvas/CanvasCard.vue';
import type { CardCanvas } from '../../canvas/CanvasCard.vue';
import type { CardAction } from '../../canvas/CardMenu.vue';
import type { CanvasLayoutInput, TextMeasurer } from '../../layout';
import { t } from '../../i18n';

defineProps<{ cards: CardCanvas[]; layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>; measurer: TextMeasurer | null }>();
const emit = defineEmits<{ action: [action: CardAction, template: CardCanvas] }>();
</script>

<template>
    <section aria-labelledby="templates-heading" class="mt-6">
        <h2 id="templates-heading" class="font-[Space_Grotesk] text-base font-semibold">{{ t('templates.heading') }}</h2>
        <p v-if="cards.length === 0" class="mt-2 text-[13px] text-secondary">{{ t('templates.empty') }}</p>
        <ul v-else class="mt-2 flex flex-wrap gap-4">
            <li v-for="card in cards" :key="card.id">
                <CanvasCard :canvas="card" :layout="layout" :measurer="measurer" :can-write="true" :template="true" @action="(action, picked) => emit('action', action, picked)" />
            </li>
        </ul>
    </section>
</template>
