<script setup lang="ts">
import AuthPanel from './AuthPanel.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { t } from '../../i18n';
import { routes } from '../../routes';

const form = useForm({ code: '' });

function submit(): void {
    form.post(routes.secondFactor(), {
        onSuccess: () => form.reset(),
        onError: () => nextTick(() => focusFirstInvalid()),
    });
}
</script>

<template>
    <AuthPanel :title="t('sign_in.second_factor.title')">
        <Head :title="t('sign_in.second_factor.title')" />
        <p class="text-[13px] text-secondary">{{ t('sign_in.second_factor.intro') }}</p>
        <ErrorSummary :errors="form.errors" />
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <TextField id="code" v-model="form.code" :label="t('sign_in.second_factor.code_label')" autocomplete="one-time-code" :error="form.errors.code" />
            <AppButton variant="primary" type="submit" :disabled="form.processing">{{ t('sign_in.second_factor.submit') }}</AppButton>
        </form>
    </AuthPanel>
</template>
