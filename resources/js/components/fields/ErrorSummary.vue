<script setup lang="ts">
import { t } from '../../i18n';

defineProps<{ errors: Record<string, string> }>();
</script>

<template>
    <section v-if="Object.keys(errors).length" aria-labelledby="error-summary-title" class="rounded-md border border-[#C0645A] bg-white p-3">
        <h2 id="error-summary-title" class="text-[13px] font-semibold text-[#A12A1E]">{{ t('form.error_summary') }}</h2>
        <ul class="mt-1 list-disc pl-5 text-[12.5px] text-[#A12A1E]">
            <li v-for="(message, field) in errors" :key="field">
                <a :href="`#${field}`" class="inline-block py-1.5 underline">{{ message }}</a>
            </li>
        </ul>
    </section>
</template>
