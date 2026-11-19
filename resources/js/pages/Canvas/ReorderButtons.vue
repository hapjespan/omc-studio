<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import Icon from '../../components/ui/Icon.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import type { EntryRowData } from './entry';

const props = defineProps<{ canvasId: number; row: EntryRowData }>();
const buttons = { up: ref<HTMLButtonElement>(), down: ref<HTMLButtonElement>() };
const classes = 'inline-flex h-mark w-mark items-center justify-center rounded-badge border border-edge bg-white text-ink disabled:border-hairline disabled:text-muted';

function move(direction: 'up' | 'down'): void {
    router.post(routes.elementReorder(props.canvasId, props.row.element.id), { direction }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            await nextTick();
            const same = buttons[direction].value;
            (same?.disabled ? buttons[direction === 'up' ? 'down' : 'up'].value : same)?.focus();
            void announce(t(direction === 'up' ? 'entry.moved_up' : 'entry.moved_down', { name: props.row.element.name }));
        },
    });
}
</script>

<template>
    <div class="flex gap-1">
        <button :ref="buttons.up" type="button" :disabled="row.first" :aria-label="t('entry.up_named', { name: row.element.name })" :class="classes" @click="move('up')"><Icon name="chevron-up" /></button>
        <button :ref="buttons.down" type="button" :disabled="row.last" :aria-label="t('entry.down_named', { name: row.element.name })" :class="classes" @click="move('down')"><Icon name="chevron-down" /></button>
    </div>
</template>
