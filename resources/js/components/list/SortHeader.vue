<script setup lang="ts">
import { t } from '../../i18n';
import Icon from '../ui/Icon.vue';

defineProps<{ label: string; direction: 'asc' | 'desc' | null; align?: 'right' }>();
defineEmits<{ sort: [] }>();
</script>

<template>
    <th scope="col" :aria-sort="direction === null ? undefined : direction === 'asc' ? 'ascending' : 'descending'" class="px-3 py-2.5 text-[12px] font-medium text-muted" :class="align === 'right' ? 'text-right' : 'text-left'">
        <button type="button" class="inline-flex items-center gap-1 rounded-control font-medium" @click="$emit('sort')">
            {{ label }}<span v-if="direction" class="sr-only">, {{ t(direction === 'asc' ? 'list.sort_ascending' : 'list.sort_descending') }}</span>
            <Icon v-if="direction" :name="direction === 'asc' ? 'chevron-up' : 'chevron-down'" :size="12" />
        </button>
    </th>
</template>
