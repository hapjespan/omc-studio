<script setup lang="ts">
import { ref } from 'vue';
import Callout from '../../components/ui/Callout.vue';
import { directionText } from '../../canvas/HoverContent.vue';
import { t } from '../../i18n';
import type { DrawnCanvas, LayoutStatement } from '../../layout';

defineProps<{ drawn: DrawnCanvas; statements: LayoutStatement[] }>();
const emit = defineEmits<{ peek: [text: string | null] }>();
const closed = ref<string | null>(null);
const full = (statements: LayoutStatement[], key: 'mission' | 'vision' | 'goals'): string => directionText(statements, key).join('\n');
</script>

<template>
    <section :aria-label="t('areas.direction.name')" class="rounded-card border border-edge bg-white p-2">
        <h2 class="flex items-center gap-1.5 text-[13px] font-semibold">{{ t('areas.direction.name') }}</h2>
        <div class="mt-1 grid gap-2 md:grid-cols-3">
            <template v-for="column in drawn.direction.filter((candidate) => candidate.key !== 'logo')" :key="column.key">
                <div
                    class="group relative rounded-control border border-hairline p-2 text-[13px]"
                    :tabindex="column.truncated ? 0 : undefined"
                    :role="column.truncated ? 'group' : undefined"
                    :aria-label="column.truncated ? (column.label ?? undefined) : undefined"
                    :data-direction="column.key"
                    :data-truncated="column.truncated"
                    @keydown.escape="closed = column.key"
                    @pointerenter="closed = null; column.truncated && emit('peek', full(statements, column.key as 'mission'))"
                    @pointerleave="column.truncated && emit('peek', null)"
                    @focus="closed = null; column.truncated && emit('peek', full(statements, column.key as 'mission'))"
                    @blur="column.truncated && emit('peek', null)"
                >
                    <p class="text-[11px] font-semibold uppercase text-secondary">{{ column.label }}</p>
                    <p v-for="(line, index) in column.lines" :key="index" class="whitespace-pre">{{ line }}</p>
                    <p v-if="column.truncated" class="absolute left-0 top-full z-10 mt-1 hidden max-w-[420px] whitespace-pre-line rounded-md border border-edge bg-white px-3 py-2 shadow-float group-focus-within:block group-hover:block" :class="{ '!hidden': closed === column.key }" role="note" data-direction-full>{{ full(statements, column.key as 'mission') }}</p>
                </div>
            </template>
        </div>
        <Callout v-if="drawn.warnings.some((warning) => warning.kind === 'directionTextCut')" tone="warning" class="mt-2">{{ t('drawing.direction_cut') }}</Callout>
    </section>
</template>
