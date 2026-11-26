<script setup lang="ts">
import AuthPanel from './AuthPanel.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { t } from '../../i18n';
import { routes } from '../../routes';

defineProps<{ flash?: { status?: string | null } }>();

const form = useForm({ email: '', password: '' });

function submit(): void {
    form.post(routes.signIn(), {
        onFinish: () => form.reset('password'),
        onError: () => nextTick(() => focusFirstInvalid()),
    });
}
</script>

<template>
    <AuthPanel :title="t('auth.sign_in_title')">
        <p v-if="flash?.status" role="status" class="rounded-control bg-accent-soft px-3 py-2 text-[13px] text-ink">{{ flash.status }}</p>
        <ErrorSummary :errors="form.errors" />
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <TextField id="email" v-model="form.email" :label="t('fields.email')" type="email" autocomplete="username" :error="form.errors.email" />
            <TextField id="password" v-model="form.password" :label="t('fields.password')" type="password" autocomplete="current-password" :error="form.errors.password" />
            <AppButton variant="primary" type="submit" :disabled="form.processing">{{ t('auth.sign_in_submit') }}</AppButton>
        </form>
    </AuthPanel>
</template>
