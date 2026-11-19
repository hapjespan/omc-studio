<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ConfirmDelete from '../../components/list/ConfirmDelete.vue';
import Dialog from '../../components/overlays/Dialog.vue';
import { announce } from '../../components/feedback/status';
import { t } from '../../i18n';
import { routes } from '../../routes';
import { descendantNames, NAME_LIMIT, type EntryElement } from './entry';

const props = defineProps<{ canvasId: number; element: EntryElement; elements: EntryElement[]; gone: (kind: 'archived' | 'deleted', name: string) => void }>();
const refusal = ref('');
const archiving = ref(false);
const deleting = ref(false);
const choosing = ref(false);
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
const startDelete = (): void => void (cluster.value ? (choosing.value = true) : (deleting.value = true));
const choose = (choice: 'delete' | 'lift'): void => {
    choosing.value = false;
    remove(choice);
};
const button = 'rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]';
</script>

<template>
    <p v-if="refusal" role="alert" class="mb-2 text-[12.5px] font-medium text-[#A12A1E]">{{ refusal }}</p>
    <div class="flex flex-wrap gap-2">
        <button type="button" :class="button" @click="archiving = true">{{ t('entry.archive') }}</button>
        <button type="button" :class="[button, 'border-[#C0645A] text-[#A12A1E]']" @click="startDelete">{{ t('entry.delete') }}</button>
    </div>
    <Dialog v-model:open="archiving" :title="t('entry.archive_title', { name: element.name })" :description="names.length ? t('entry.archive_with', { names: listed }) : t('entry.archive_alone')">
        <div class="flex gap-2">
            <button type="button" :class="button" @click="archiving = false">{{ t('list.cancel') }}</button>
            <button type="button" :class="button" @click="archive(); archiving = false">{{ t('entry.archive') }}</button>
        </div>
    </Dialog>
    <ConfirmDelete v-model:open="deleting" :name="element.name" :consequence="names.length ? t('entry.delete_with', { names: listed }) : null" @confirm="remove()" />
    <Dialog v-model:open="choosing" :title="t('entry.cluster_title', { name: element.name })" :description="t('entry.cluster_description', { names: listed })">
        <div class="flex flex-wrap gap-2">
            <button type="button" :class="button" @click="choosing = false">{{ t('list.cancel') }}</button>
            <button type="button" :class="button" @click="choose('lift')">{{ t('entry.cluster_lift') }}</button>
            <button type="button" :class="[button, 'border-[#C0645A] text-[#A12A1E]']" @click="choose('delete')">{{ t('entry.cluster_delete') }}</button>
        </div>
    </Dialog>
</template>
