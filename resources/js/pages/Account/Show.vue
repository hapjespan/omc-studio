<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import { nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import SecondFactor from './SecondFactor.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { t } from '../../i18n';
import { routes } from '../../routes';

interface SecondFactorState {
    active: boolean;
    remainingLabel: string;
    enrolment?: { secret: string; qr: string } | null;
}

defineProps<{ flash?: { status?: string | null }; secondFactor: SecondFactorState }>();

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

function submit(): void {
    form.put(routes.accountPassword(), {
        onFinish: () => form.reset(),
        onError: () => nextTick(() => focusFirstInvalid()),
    });
}
</script>

<template>
    <Head :title="t('account.title')" />
    <div class="mx-auto flex max-w-sm flex-col gap-4 p-6">
        <h1 class="text-[20px] font-semibold text-ink">{{ t('account.title') }}</h1>
        <p v-if="flash?.status" role="status" class="rounded-md bg-accent-soft px-3 py-2 text-[13px] text-ink">{{ flash.status }}</p>
        <h2 class="text-[16px] font-semibold text-ink">{{ t('account.password_title') }}</h2>
        <ErrorSummary :errors="form.errors" />
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <TextField id="current_password" v-model="form.current_password" :label="t('fields.current_password')" type="password" autocomplete="current-password" :error="form.errors.current_password" />
            <TextField id="password" v-model="form.password" :label="t('fields.password')" type="password" autocomplete="new-password" :error="form.errors.password" />
            <TextField id="password_confirmation" v-model="form.password_confirmation" :label="t('fields.password_confirmation')" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" />
            <AppButton variant="primary" type="submit" :disabled="form.processing">{{ t('auth.new_password_submit') }}</AppButton>
        </form>
        <SecondFactor v-bind="secondFactor" />
    </div>
</template>
