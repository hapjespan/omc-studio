<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import IconButton from '../../components/ui/IconButton.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import type { EntryRowData } from './entry';

const props = defineProps<{ canvasId: number; row: EntryRowData }>();
const buttons = { up: ref<InstanceType<typeof IconButton>>(), down: ref<InstanceType<typeof IconButton>>() };

function move(direction: 'up' | 'down'): void {
    router.post(routes.elementReorder(props.canvasId, props.row.element.id), { direction }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            await nextTick();
            const ended = direction === 'up' ? props.row.first : props.row.last;
            buttons[ended ? (direction === 'up' ? 'down' : 'up') : direction].value?.focus();
            void announce(t(direction === 'up' ? 'entry.moved_up' : 'entry.moved_down', { name: props.row.element.name }));
        },
    });
}
</script>

<template>
    <div class="flex gap-1">
        <IconButton :ref="buttons.up" icon="chevron-up" :disabled="row.first" :label="t('entry.up_named', { name: row.element.name })" @click="move('up')" />
        <IconButton :ref="buttons.down" icon="chevron-down" :disabled="row.last" :label="t('entry.down_named', { name: row.element.name })" @click="move('down')" />
    </div>
</template>
