<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import { t } from '../../i18n';
import { routes } from '../../routes';
import type { EntryRowData } from './entry';

const props = defineProps<{ canvasId: number; row: EntryRowData }>();
const buttons = { up: ref<HTMLButtonElement>(), down: ref<HTMLButtonElement>() };
const classes = 'rounded-md border border-[#848B95] px-2 py-1.5 text-[12.5px] disabled:opacity-60';

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
        <button :ref="buttons.up" type="button" :disabled="row.first" :aria-label="t('entry.up_named', { name: row.element.name })" :class="classes" @click="move('up')">{{ t('entry.up') }}</button>
        <button :ref="buttons.down" type="button" :disabled="row.last" :aria-label="t('entry.down_named', { name: row.element.name })" :class="classes" @click="move('down')">{{ t('entry.down') }}</button>
    </div>
</template>
