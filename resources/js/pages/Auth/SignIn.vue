<script setup lang="ts">
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
    <main class="mx-auto flex max-w-sm flex-col gap-4 p-6">
        <h1 class="text-[20px] font-semibold text-ink">{{ t('auth.sign_in_title') }}</h1>
        <p v-if="flash?.status" role="status" class="rounded-md bg-accent-soft px-3 py-2 text-[13px] text-ink">{{ flash.status }}</p>
        <ErrorSummary :errors="form.errors" />
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <TextField id="email" v-model="form.email" :label="t('fields.email')" type="email" autocomplete="username" :error="form.errors.email" />
            <TextField id="password" v-model="form.password" :label="t('fields.password')" type="password" autocomplete="current-password" :error="form.errors.password" />
            <AppButton variant="primary" type="submit" :disabled="form.processing">{{ t('auth.sign_in_submit') }}</AppButton>
        </form>
    </main>
</template>
