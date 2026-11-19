<script setup lang="ts">
import { nextTick, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import CanvasDialogs from './Overview/CanvasDialogs.vue';
import type { DialogKind } from './Overview/CanvasDialogs.vue';
import CanvasCard from '../canvas/CanvasCard.vue';
import type { CardCanvas } from '../canvas/CanvasCard.vue';
import { announce } from '../components/feedback/status';
import ArchiveToggle from '../components/list/ArchiveToggle.vue';
import { t } from '../i18n';
import { createBrowserMeasurer } from '../layout/browserMeasurer';
import type { CanvasLayoutInput, TextMeasurer } from '../layout';
import { routes } from '../routes';

interface OverviewOrganisation {
    id: number;
    name: string;
    is_sample?: boolean;
    canvases: CardCanvas[];
}

const props = defineProps<{
    organisations: OverviewOrganisation[];
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    showArchived: boolean;
    canWrite: boolean;
    templates: { id: number; name: string }[];
}>();

const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));

const page = usePage();
watch(() => page.props.flash, (flash) => (flash as { status?: string | null } | undefined)?.status && announce((flash as { status: string }).status));
const heading = ref<HTMLElement>();
const settle = async (): Promise<void> => {
    await nextTick();
    heading.value?.focus();
};
const dialogs = ref<InstanceType<typeof CanvasDialogs>>();
const organisationOptions = (): { id: number; name: string }[] => props.organisations.map(({ id, name }) => ({ id, name }));
const openDialog = (kind: DialogKind | 'restore', canvas: CardCanvas | null, organisationId: number | null): void => {
    if (kind === 'restore' && canvas) router.post(routes.canvasRestore(canvas.id), {}, { preserveScroll: true, onSuccess: settle });
    else if (kind !== 'restore') dialogs.value?.show(kind, canvas && organisationId ? { id: canvas.id, name: canvas.name, organisationId, counts: canvas.counts } : null);
};

const toggle = (archived: boolean): void => router.get(routes.overview(), archived ? { archived: 1 } : {}, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <Head :title="t('shell.overview')" />
    <h1 ref="heading" tabindex="-1" class="text-[20px] font-semibold">{{ t('shell.overview') }}</h1>
    <div class="mt-3 flex items-center gap-4">
        <button v-if="canWrite" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="openDialog('create', null, null)">{{ t('overview.new_canvas') }}</button>
        <ArchiveToggle :model-value="showArchived" @update:model-value="toggle" />
    </div>
    <section role="region" v-for="organisation in organisations" :key="organisation.id" :aria-labelledby="`organisation-${organisation.id}`" class="mt-6">
        <h2 :id="`organisation-${organisation.id}`" class="font-[Space_Grotesk] text-base font-semibold">{{ organisation.name }} <span v-if="organisation.is_sample" class="rounded border border-[#848B95] px-1.5 text-[12px] font-normal">{{ t('sample.label') }}</span></h2>
        <Link v-if="canWrite" :href="routes.costRecords(organisation.id)" class="mt-1 inline-block text-[13px] underline">{{ t('overview.cost_records') }}</Link>
        <p v-if="organisation.canvases.length === 0" class="mt-2 text-[13px] text-[#454C57]">{{ t('overview.empty_organisation') }}</p>
        <ul v-else class="mt-2 flex flex-wrap gap-4">
            <li v-for="canvas in organisation.canvases" :key="canvas.id">
                <CanvasCard :canvas="canvas" :layout="layout" :measurer="measurer" :can-write="canWrite" @action="(action, picked) => openDialog(action, picked, organisation.id)" />
            </li>
        </ul>
    </section>
    <CanvasDialogs v-if="canWrite" ref="dialogs" @done="settle" :organisations="organisationOptions()" :templates="templates" />
</template>
