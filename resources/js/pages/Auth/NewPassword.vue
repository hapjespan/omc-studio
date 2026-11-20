<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import { nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { t } from '../../i18n';
import { routes } from '../../routes';

const form = useForm({ password: '', password_confirmation: '' });

function submit(): void {
    form.put(routes.newPassword(), {
        onFinish: () => form.reset(),
        onError: () => nextTick(() => focusFirstInvalid()),
    });
}
</script>

<template>
    <main class="mx-auto flex max-w-sm flex-col gap-4 p-6">
        <h1 class="text-[20px] font-semibold text-ink">{{ t('auth.new_password_title') }}</h1>
        <p class="text-[13px] text-ink">{{ t('auth.new_password_intro') }}</p>
        <ErrorSummary :errors="form.errors" />
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <TextField id="password" v-model="form.password" :label="t('fields.password')" type="password" autocomplete="new-password" :error="form.errors.password" />
            <TextField id="password_confirmation" v-model="form.password_confirmation" :label="t('fields.password_confirmation')" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" />
            <AppButton variant="primary" type="submit" :disabled="form.processing">{{ t('auth.new_password_submit') }}</AppButton>
        </form>
    </main>
</template>
