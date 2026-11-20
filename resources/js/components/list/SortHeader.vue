<script setup lang="ts">
import { t } from '../../i18n';

defineProps<{ label: string; direction: 'asc' | 'desc' | null; align?: 'right' }>();
defineEmits<{ sort: [] }>();
</script>

<template>
    <th scope="col" :aria-sort="direction === null ? undefined : direction === 'asc' ? 'ascending' : 'descending'" :class="[align === 'right' ? 'text-right' : 'text-left', 'px-3 py-2.5 text-[11.5px] font-semibold text-secondary']">
        <button type="button" class="rounded-control font-semibold" @click="$emit('sort')">
            {{ label }}<span v-if="direction" class="sr-only">, {{ t(direction === 'asc' ? 'list.sort_ascending' : 'list.sort_descending') }}</span>
            <span v-if="direction" aria-hidden="true">{{ direction === 'asc' ? ' ↑' : ' ↓' }}</span>
        </button>
    </th>
</template>
