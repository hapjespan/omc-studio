<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import { ICON_BUTTON_CLASS } from '../../components/ui/IconButton.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import type { EntryRowData } from './entry';

const props = defineProps<{ canvasId: number; row: EntryRowData }>();
const buttons = { up: ref<HTMLButtonElement>(), down: ref<HTMLButtonElement>() };

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
        <button :ref="buttons.up" type="button" :disabled="row.first" :aria-label="t('entry.up_named', { name: row.element.name })" :class="ICON_BUTTON_CLASS" @click="move('up')">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="shrink-0"><path d="m4.2 9.8 3.8-3.8 3.8 3.8" /></svg>
        </button>
        <button :ref="buttons.down" type="button" :disabled="row.last" :aria-label="t('entry.down_named', { name: row.element.name })" :class="ICON_BUTTON_CLASS" @click="move('down')">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="shrink-0"><path d="m4.2 6.2 3.8 3.8 3.8-3.8" /></svg>
        </button>
    </div>
</template>
