<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ id: string; label: string; hint?: string; error?: string }>();

const describedby = computed(() => [props.hint ? `${props.id}-hint` : null, props.error ? `${props.id}-error` : null].filter(Boolean).join(' ') || undefined);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="id" class="text-[13px] font-semibold text-[#171B21]">{{ label }}</label>
        <p v-if="hint" :id="`${id}-hint`" class="text-[11.5px] text-[#5F6772]">{{ hint }}</p>
        <slot :describedby="describedby" :invalid="Boolean(error)" />
        <p v-if="error" :id="`${id}-error`" class="text-[12.5px] font-medium text-[#A12A1E]">{{ error }}</p>
    </div>
</template>
