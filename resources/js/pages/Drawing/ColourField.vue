<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import TextField from '../../components/fields/TextField.vue';
import { HEX_PATTERN } from '../../colour';

const props = defineProps<{ id: string; caption: string; label: string; swatchLabel: string; value: string; invalidMessage: string; error?: string }>();
const emit = defineEmits<{ commit: [hex: string]; invalid: [text: string] }>();

const draft = ref(props.value);
const shape = ref('');
const field = ref<HTMLElement>();
const shown = computed(() => props.error || shape.value);
const swatch = computed(() => (HEX_PATTERN.test(draft.value) ? draft.value : props.value || '#FFFFFF'));

watch(
    () => props.value,
    (value) => {
        draft.value = value;
        shape.value = '';
    },
);

function commit(): void {
    const typed = draft.value.trim();
    if (typed === props.value) return (shape.value = '');
    if (!HEX_PATTERN.test(typed)) {
        shape.value = props.invalidMessage;
        emit('invalid', typed);
        return;
    }
    shape.value = '';
    emit('commit', typed.toUpperCase());
}

defineExpose({ focus: () => field.value?.querySelector<HTMLInputElement>('input[type=text]')?.focus() });
</script>

<template>
    <div class="flex flex-col gap-1">
        <span class="text-[13px] font-semibold text-ink" aria-hidden="true">{{ caption }}</span>
        <div ref="field" class="flex items-start gap-2">
            <input
                :id="`${id}-swatch`"
                type="color"
                :value="swatch.toLowerCase()"
                :aria-label="swatchLabel"
                class="h-control w-control shrink-0 cursor-pointer rounded-control border border-edge bg-white p-0.5"
                @change="draft = ($event.target as HTMLInputElement).value.toUpperCase(); commit()"
            />
            <TextField :id="id" v-model="draft" class="min-w-0 flex-1" :label="label" hide-label :error="shown || undefined" @change="commit" />
        </div>
    </div>
</template>
