<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { formatDate } from '../format';
import { t } from '../i18n';
import { computeCanvasLayout } from '../layout';
import type { CanvasLayoutInput, TextMeasurer } from '../layout';
import { routes } from '../routes';
import CardMenu from './CardMenu.vue';
import type { CardAction } from './CardMenu.vue';
import Miniature from './Miniature.vue';

export interface CardCanvas {
    id: number;
    name: string;
    updated_at: string | null;
    archived: boolean;
    counts: { elements: number; statements: number } | null;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
}

const props = defineProps<{ canvas: CardCanvas; layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>; measurer: TextMeasurer | null; canWrite?: boolean; template?: boolean }>();
const emit = defineEmits<{ action: [action: CardAction, canvas: CardCanvas] }>();
const drawn = computed(() => (props.measurer ? computeCanvasLayout({ ...props.layout, elements: props.canvas.elements, statements: props.canvas.statements }, props.measurer) : null));
</script>

<template>
    <article class="flex flex-col gap-1 rounded-md border border-edge p-2">
        <Link :href="routes.canvas(canvas.id)" class="flex flex-col gap-1 font-semibold">
            <Miniature v-if="drawn" :canvas="drawn" :width="240" />
            <span v-else class="block h-[170px] w-[240px]" aria-hidden="true" />
            <span>{{ canvas.name }}</span>
        </Link>
        <p class="text-[12.5px] text-secondary">
            <span v-if="canvas.archived" class="font-semibold">{{ t('overview.archived_badge') }}. </span>
            <span v-if="canvas.updated_at">{{ t('overview.updated', { date: formatDate(canvas.updated_at) }) }}</span>
        </p>
        <CardMenu v-if="canWrite" :name="canvas.name" :archived="canvas.archived" :template="template" @select="emit('action', $event, canvas)" />
    </article>
</template>
