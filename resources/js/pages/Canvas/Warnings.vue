<script setup lang="ts">
import Callout from '../../components/ui/Callout.vue';
import { t } from '../../i18n';
import type { EntryWarning } from './entry';

defineProps<{ warnings: EntryWarning[] }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null] }>();
</script>

<template>
    <section v-if="warnings.length" :aria-label="`${t('entry.warnings')} (${warnings.length})`" class="mt-3">
        <h3 class="text-[13px] font-semibold">{{ t('entry.warnings') }} ({{ warnings.length }})</h3>
        <ul class="mt-1 flex flex-col gap-1">
            <li v-for="warning in warnings" :key="`${warning.key}-${warning.id}`">
                <button type="button" class="block w-full rounded-control text-left" @click="(event) => $emit('select', warning.id, event.currentTarget as HTMLElement)"><Callout tone="warning">{{ t(warning.key, { name: warning.name }) }}</Callout></button>
            </li>
        </ul>
    </section>
</template>
