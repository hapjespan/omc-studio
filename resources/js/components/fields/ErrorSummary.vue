<script setup lang="ts">
import { t } from '../../i18n';

const props = defineProps<{ errors: Record<string, string>; prefix?: string }>();

const target = (field: string): string => `#${props.prefix ?? ''}${field}`;
</script>

<template>
    <section v-if="Object.keys(errors).length" :aria-label="t('form.error_summary')" class="rounded-md border border-danger-edge bg-white p-3">
        <h2 class="text-[13px] font-semibold text-danger">{{ t('form.error_summary') }}</h2>
        <ul class="mt-1 list-disc pl-5 text-[12.5px] text-danger">
            <li v-for="(message, field) in errors" :key="field">
                <a v-if="field !== 'rule'" :href="target(field)" class="inline-block py-1.5 underline">{{ message }}</a>
                <span v-else class="inline-block py-1.5">{{ message }}</span>
            </li>
        </ul>
    </section>
</template>
