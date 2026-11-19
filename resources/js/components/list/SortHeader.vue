<script setup lang="ts">
import { t } from '../../i18n';

defineProps<{ label: string; direction: 'asc' | 'desc' | null }>();
defineEmits<{ sort: [] }>();
</script>

<template>
    <th scope="col" :aria-sort="direction === null ? undefined : direction === 'asc' ? 'ascending' : 'descending'" class="px-2 py-1.5 text-left text-[12.5px] font-semibold">
        <button type="button" class="rounded-md px-1 py-0.5 font-semibold" @click="$emit('sort')">
            {{ label }}<span v-if="direction" class="sr-only">, {{ t(direction === 'asc' ? 'list.sort_ascending' : 'list.sort_descending') }}</span>
            <span v-if="direction" aria-hidden="true">{{ direction === 'asc' ? ' ↑' : ' ↓' }}</span>
        </button>
    </th>
</template>
