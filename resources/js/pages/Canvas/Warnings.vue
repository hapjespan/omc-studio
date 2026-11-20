<script setup lang="ts">
import Callout from '../../components/ui/Callout.vue';
import { t } from '../../i18n';
import type { EntryWarning } from './entry';

defineProps<{ warnings: EntryWarning[] }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null] }>();

const parts = (warning: EntryWarning): [string, string] => {
    const text = t(warning.key, { name: warning.name, other: warning.other ?? '' });
    const cut = text.indexOf(': ');

    return cut < 0 ? ['', text] : [text.slice(0, cut + 1), text.slice(cut + 2)];
};
</script>

<template>
    <section v-if="warnings.length" :aria-label="`${t('entry.warnings')} (${warnings.length})`" class="mt-3">
        <h3 :aria-label="`${t('entry.warnings')} (${warnings.length})`" class="flex items-baseline justify-between text-[13px] font-semibold"><span>{{ t('entry.warnings') }}</span><span class="font-normal text-secondary">{{ warnings.length }}</span></h3>
        <ul class="mt-2 flex flex-col gap-2">
            <li v-for="warning in warnings" :key="`${warning.key}-${warning.id}`">
                <button type="button" class="block w-full rounded-control text-left" @click="(event) => $emit('select', warning.id, event.currentTarget as HTMLElement)"><Callout tone="warning" :label="parts(warning)[0] || undefined">{{ parts(warning)[1] }}</Callout></button>
            </li>
        </ul>
    </section>
</template>
