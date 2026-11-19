<script setup lang="ts">
import { nextTick } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { announce } from '../components/feedback/status';
import ErrorSummary from '../components/fields/ErrorSummary.vue';
import TextField from '../components/fields/TextField.vue';
import { focusFirstInvalid } from '../components/fields/focusFirstInvalid';
import Dialog from '../components/overlays/Dialog.vue';
import { t } from '../i18n';
import { routes } from '../routes';
import { reauth } from './session';

const page = usePage<{ auth: { secondFactor: boolean } }>();
const form = useForm({ password: '', code: '' });

function submit(): void {
    form.post(routes.reauthenticate(), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            reauth.value = false;
            void announce(t('reauth.renewed'));
        },
        onError: () => nextTick(() => focusFirstInvalid()),
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Dialog v-model:open="reauth" :title="t('reauth.title')" :description="t('reauth.intro')" :dirty="form.isDirty">
        <ErrorSummary :errors="form.errors" prefix="reauth-" />
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <TextField id="reauth-password" v-model="form.password" :label="t('fields.password')" type="password" autocomplete="current-password" :error="form.errors.password" />
            <TextField v-if="page.props.auth.secondFactor" id="reauth-code" v-model="form.code" :label="t('sign_in.second_factor.code_label')" autocomplete="one-time-code" :error="form.errors.code" />
            <button type="submit" :disabled="form.processing" class="rounded-md bg-[#1F3A5F] px-3 py-2 text-[13px] font-semibold text-white">{{ t('reauth.submit') }}</button>
        </form>
    </Dialog>
</template>
