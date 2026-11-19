<script setup lang="ts">
import AppButton from '../components/ui/AppButton.vue';
import { nextTick, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import CanvasDialogs from './Overview/CanvasDialogs.vue';
import TemplateDialogs from './Overview/TemplateDialogs.vue';
import Templates from './Overview/Templates.vue';
import type { DialogKind } from './Overview/CanvasDialogs.vue';
import CanvasCard from '../canvas/CanvasCard.vue';
import type { CardCanvas } from '../canvas/CanvasCard.vue';
import type { CardAction } from '../canvas/CardMenu.vue';
import { announce } from '../components/feedback/status';
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

const props = defineProps<{
    organisations: OverviewOrganisation[];
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    showArchived: boolean;
    canWrite: boolean;
    templates: { id: number; name: string }[];
    templateCards: CardCanvas[];
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
const templateDialogs = ref<InstanceType<typeof TemplateDialogs>>();
const openTemplateDialog = (action: CardAction, template: CardCanvas): void => {
    const target = { id: template.id, name: template.name, counts: template.counts };
    if (action === 'rename' || action === 'delete') templateDialogs.value?.show(action, target);
};
const openDialog = (kind: DialogKind | 'restore' | 'save_template', canvas: CardCanvas | null, organisationId: number | null): void => {
    if (kind === 'save_template' && canvas) templateDialogs.value?.show('save', { id: canvas.id, name: canvas.name, counts: null });
    else if (kind === 'restore' && canvas) router.post(routes.canvasRestore(canvas.id), {}, { preserveScroll: true, onSuccess: settle });
    else if (kind !== 'restore' && kind !== 'save_template') dialogs.value?.show(kind, canvas && organisationId ? { id: canvas.id, name: canvas.name, organisationId, counts: canvas.counts } : null);
};

const toggle = (archived: boolean): void => router.get(routes.overview(), archived ? { archived: 1 } : {}, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <Head :title="t('shell.overview')" />
    <h1 ref="heading" tabindex="-1" class="text-[20px] font-semibold">{{ t('shell.overview') }}</h1>
    <div class="mt-3 flex items-center gap-4">
        <AppButton v-if="canWrite" @click="openDialog('create', null, null)">{{ t('overview.new_canvas') }}</AppButton>
        <ArchiveToggle :model-value="showArchived" @update:model-value="toggle" />
    </div>
    <section role="region" v-for="organisation in organisations" :key="organisation.id" :aria-labelledby="`organisation-${organisation.id}`" class="mt-6">
        <h2 :id="`organisation-${organisation.id}`" class="font-display text-base font-semibold">{{ organisation.name }}</h2>
        <p v-if="organisation.canvases.length === 0" class="mt-2 text-[13px] text-secondary">{{ t('overview.empty_organisation') }}</p>
        <ul v-else class="mt-2 flex flex-wrap gap-4">
            <li v-for="canvas in organisation.canvases" :key="canvas.id">
                <CanvasCard :canvas="canvas" :layout="layout" :measurer="measurer" :can-write="canWrite" @action="(action, picked) => openDialog(action, picked, organisation.id)" />
            </li>
        </ul>
    </section>
    <Templates v-if="canWrite" :cards="templateCards" :layout="layout" :measurer="measurer" @action="openTemplateDialog" />
    <TemplateDialogs v-if="canWrite" ref="templateDialogs" @done="settle" />
    <CanvasDialogs v-if="canWrite" ref="dialogs" @done="settle" :organisations="organisationOptions()" :templates="templates" />
</template>
