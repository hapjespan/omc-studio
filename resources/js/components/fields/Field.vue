<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ id: string; label: string; hint?: string; error?: string; hideLabel?: boolean; alert?: boolean }>();

const describedby = computed(() => [props.hint ? `${props.id}-hint` : null, props.error ? `${props.id}-error` : null].filter(Boolean).join(' ') || undefined);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="id" :class="hideLabel ? 'sr-only' : 'text-[13px] font-semibold text-ink'">{{ label }}</label>
        <slot :describedby="describedby" :invalid="Boolean(error)" />
        <p v-if="hint" :id="`${id}-hint`" class="text-[11.5px] text-muted">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" :role="alert ? 'alert' : undefined" class="text-[12.5px] font-medium text-danger">{{ error }}</p>
    </div>
</template>
