<script setup lang="ts">
import { ref, watch } from 'vue';
import { t } from '../../i18n';
import Field from './Field.vue';

defineProps<{ id: string; label: string; accept?: string; hint?: string; error?: string }>();
const emit = defineEmits<{ pick: [file: File | null] }>();
const model = defineModel<File | null>({ default: null });
const input = ref<HTMLInputElement>();

watch(model, (file) => {
    if (file === null && input.value) input.value.value = '';
});

function pick(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    model.value = file;
    emit('pick', file);
}
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <div class="relative">
            <input :id="id" ref="input" type="file" :accept="accept" :aria-describedby="describedby" :aria-invalid="invalid || undefined" class="peer absolute inset-0 z-10 size-full cursor-pointer opacity-0" @change="pick" />
            <div class="flex min-h-control items-center gap-3 rounded-control border border-edge bg-white py-[3px] pl-1 pr-2.5 text-[13px] peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ink peer-focus-visible:shadow-[0_0_0_2px_var(--color-white)]">
                <span class="inline-flex min-h-mark shrink-0 items-center rounded-badge border border-edge bg-surface px-2.5 text-[12.5px] font-medium text-ink">{{ t('fields.file_choose') }}</span>
                <span :class="['min-w-0 truncate', model ? 'text-ink' : 'text-muted']">{{ model?.name ?? t('fields.file_none') }}</span>
            </div>
        </div>
    </Field>
</template>
