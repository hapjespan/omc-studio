<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ConfirmDelete from '../../components/list/ConfirmDelete.vue';
import Dialog from '../../components/overlays/Dialog.vue';
import { announce } from '../../components/feedback/status';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import type { TargetRow } from '../../allocation/types';
import { descendantNames, NAME_LIMIT, type ElementCost, type EntryElement } from './entry';
import RedistributionDialog from './RedistributionDialog.vue';

const props = defineProps<{ canvasId: number; element: EntryElement; elements: EntryElement[]; cost?: ElementCost; targets: TargetRow[]; loaded: boolean; gone: (kind: 'archived' | 'deleted', name: string) => void }>();
const refusal = ref('');
const archiving = ref(false);
const deleting = ref(false);
const choosing = ref(false);
const redistributing = ref(false);
const lines = computed(() => (props.cost?.years.length ?? 0) > 0);
const names = computed(() => descendantNames(props.element, props.elements));
const listed = computed(() => (names.value.length > NAME_LIMIT ? t('entry.n_elements', { count: names.value.length }) : names.value.join(', ')));
const cluster = computed(() => props.element.kind === 'cluster' && names.value.length > 0);
const refused = (errors: Record<string, string>): void => {
    refusal.value = errors.rule ?? Object.values(errors)[0] ?? '';
    void announce(refusal.value);
};
const outcome = (key: string) => () => {
    refusal.value = '';
    props.gone(key === 'deleted' ? 'deleted' : 'archived', props.element.name);
    void announce(t(`entry.${key}`, { name: props.element.name }));
};

const archive = (): void => router.post(routes.elementArchive(props.canvasId, props.element.id), {}, { preserveScroll: true, preserveState: true, onSuccess: outcome('archived'), onError: refused });
const remove = (choice?: 'delete' | 'lift'): void => router.delete(routes.element(props.canvasId, props.element.id), { data: choice ? { cluster_children: choice } : {}, preserveScroll: true, preserveState: true, onSuccess: outcome('deleted'), onError: refused });
const startArchive = (): void => void (lines.value ? (redistributing.value = true) : (archiving.value = true));
const startDelete = (): void => void (lines.value && !(cluster.value && !props.cost?.own) ? (redistributing.value = true) : cluster.value ? (choosing.value = true) : (deleting.value = true));
const redistributed = (): void => {
    refusal.value = '';
    props.gone('archived', props.element.name);
    void announce(t('redistribute.done', { name: props.element.name }));
};
const choose = (choice: 'delete' | 'lift'): void => {
    choosing.value = false;
    if (choice === 'delete' && lines.value) redistributing.value = true;
    else remove(choice);
};
</script>

<template>
    <p v-if="refusal" role="alert" class="mb-2 text-[12.5px] font-medium text-danger">{{ refusal }}</p>
    <div class="flex flex-wrap gap-2">
        <slot />
        <AppButton :disabled="!loaded" :aria-describedby="loaded ? undefined : 'element-costs-status'" @click="startArchive">{{ t('entry.archive') }}</AppButton>
        <AppButton variant="danger" :disabled="!loaded" :aria-describedby="loaded ? undefined : 'element-costs-status'" @click="startDelete">{{ t('entry.delete') }}</AppButton>
    </div>
    <Dialog v-model:open="archiving" :title="t('entry.archive_title', { name: element.name })" :description="names.length ? t('entry.archive_with', { names: listed }) : t('entry.archive_alone')">
        <div class="flex gap-2">
            <AppButton @click="archiving = false">{{ t('list.cancel') }}</AppButton>
            <AppButton @click="archive(); archiving = false">{{ t('entry.archive') }}</AppButton>
        </div>
    </Dialog>
    <RedistributionDialog v-model:open="redistributing" :canvas-id="canvasId" :element="element" :targets="targets" :done="redistributed" />
    <ConfirmDelete v-model:open="deleting" :name="element.name" :consequence="names.length ? t('entry.delete_with', { names: listed }) : null" @confirm="remove()" />
    <Dialog v-model:open="choosing" :title="t('entry.cluster_title', { name: element.name })" :description="t('entry.cluster_description', { names: listed })">
        <div class="flex flex-wrap gap-2">
            <AppButton @click="choosing = false">{{ t('list.cancel') }}</AppButton>
            <AppButton @click="choose('lift')">{{ t('entry.cluster_lift') }}</AppButton>
            <AppButton variant="danger" @click="choose('delete')">{{ t('entry.cluster_delete') }}</AppButton>
        </div>
    </Dialog>
</template>
