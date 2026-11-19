<script setup lang="ts">
import { onMounted, shallowRef } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import CanvasCard from '../canvas/CanvasCard.vue';
import type { CardCanvas } from '../canvas/CanvasCard.vue';
import ArchiveToggle from '../components/list/ArchiveToggle.vue';
import { t } from '../i18n';
import { createBrowserMeasurer } from '../layout/browserMeasurer';
import type { CanvasLayoutInput, TextMeasurer } from '../layout';
import { routes } from '../routes';

interface OverviewOrganisation {
    id: number;
    name: string;
    canvases: CardCanvas[];
}

defineProps<{
    organisations: OverviewOrganisation[];
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    showArchived: boolean;
    canWrite: boolean;
}>();

const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));

const toggle = (archived: boolean): void => router.get(routes.overview(), archived ? { archived: 1 } : {}, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <Head :title="t('shell.overview')" />
    <h1 class="text-[20px] font-semibold">{{ t('shell.overview') }}</h1>
    <div class="mt-3"><ArchiveToggle :model-value="showArchived" @update:model-value="toggle" /></div>
    <section v-for="organisation in organisations" :key="organisation.id" :aria-labelledby="`organisation-${organisation.id}`" class="mt-6">
        <h2 :id="`organisation-${organisation.id}`" class="font-[Space_Grotesk] text-base font-semibold">{{ organisation.name }}</h2>
        <p v-if="organisation.canvases.length === 0" class="mt-2 text-[13px] text-[#454C57]">{{ t('overview.empty_organisation') }}</p>
        <ul v-else class="mt-2 flex flex-wrap gap-4">
            <li v-for="canvas in organisation.canvases" :key="canvas.id">
                <CanvasCard :canvas="canvas" :layout="layout" :measurer="measurer" :can-write="canWrite" />
            </li>
        </ul>
    </section>
</template>
