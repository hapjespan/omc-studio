<script setup lang="ts">
import { nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { t } from '../../i18n';
import { routes } from '../../routes';

defineProps<{ flash?: { status?: string | null } }>();

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
        <h1 class="text-[20px] font-semibold text-[#171B21]">{{ t('account.title') }}</h1>
        <p v-if="flash?.status" role="status" class="rounded-md bg-[#EEF2F7] px-3 py-2 text-[13px] text-[#171B21]">{{ flash.status }}</p>
        <h2 class="text-[16px] font-semibold text-[#171B21]">{{ t('account.password_title') }}</h2>
        <ErrorSummary :errors="form.errors" />
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <TextField id="current_password" v-model="form.current_password" :label="t('fields.current_password')" type="password" autocomplete="current-password" :error="form.errors.current_password" />
            <TextField id="password" v-model="form.password" :label="t('fields.password')" type="password" autocomplete="new-password" :error="form.errors.password" />
            <TextField id="password_confirmation" v-model="form.password_confirmation" :label="t('fields.password_confirmation')" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" />
            <button type="submit" :disabled="form.processing" class="rounded-md bg-[#1F3A5F] px-3 py-2 text-[13px] font-semibold text-white">{{ t('auth.new_password_submit') }}</button>
        </form>
    </div>
</template>
