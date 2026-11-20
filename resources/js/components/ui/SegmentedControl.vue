<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export interface SegmentItem {
    key: string;
    label: string;
    href?: string;
}

withDefaults(defineProps<{ items: SegmentItem[]; active?: string; label: string; role?: 'group' | 'toolbar' }>(), { active: undefined, role: 'group' });
const emit = defineEmits<{ select: [key: string] }>();

const segment = 'inline-flex items-center rounded-[5px] px-3 font-medium text-secondary aria-[current=page]:bg-ink aria-[current=page]:font-semibold aria-[current=page]:text-white aria-pressed:bg-ink aria-pressed:font-semibold aria-pressed:text-white';
</script>

<template>
    <div :role="role" :aria-label="label" class="inline-flex h-control items-stretch rounded-control border border-edge bg-white p-px text-[13px]">
        <template v-for="item in items" :key="item.key">
            <Link v-if="item.href" :href="item.href" :aria-current="item.key === active ? 'page' : undefined" :class="segment">{{ item.label }}</Link>
            <button v-else type="button" :aria-pressed="item.key === active" :class="segment" @click="emit('select', item.key)">{{ item.label }}</button>
        </template>
    </div>
</template>
