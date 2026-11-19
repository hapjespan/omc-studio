<script setup lang="ts">
import { computed, nextTick } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import StatusRegion from '../../components/feedback/StatusRegion.vue';
import TextField from '../../components/fields/TextField.vue';
import { announce } from '../../components/feedback/status';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { t } from '../../i18n';
import { routes } from '../../routes';

interface Enrolment {
    secret: string;
    qr: string;
}

defineProps<{ active: boolean; remainingLabel: string; enrolment?: Enrolment | null }>();

const page = usePage();
const codes = computed(() => (page.flash as { recoveryCodes?: string[] }).recoveryCodes ?? null);

const password = useForm({ current_password: '' });
const confirmation = useForm({ code: '' });

const failed = (): void => {
    nextTick(() => focusFirstInvalid());
};

const moveTo = (id: string) => () => {
    nextTick(() => document.getElementById(id)?.focus());
};

function begin(): void {
    password.post(routes.accountSecondFactor(), { onFinish: () => password.reset(), onSuccess: moveTo('second_factor_code'), onError: failed });
}

function confirm(): void {
    confirmation.post(routes.accountSecondFactorConfirm(), { onSuccess: () => { confirmation.reset(); moveTo('second_factor_codes_title')(); }, onError: failed });
}

function renew(): void {
    password.post(routes.accountRecoveryCodes(), { onFinish: () => password.reset(), onSuccess: moveTo('second_factor_codes_title'), onError: failed });
}

function turnOff(): void {
    password.delete(routes.accountSecondFactor(), { onFinish: () => password.reset(), onSuccess: moveTo('second_factor_current_password'), onError: failed });
}

async function copyCodes(): Promise<void> {
    try {
        await navigator.clipboard.writeText((codes.value ?? []).join('\n'));
        await announce(t('second_factor.copied'));
    } catch {
        await announce(t('second_factor.copy_failed'));
    }
}

function downloadCodes(): void {
    const file = new Blob([(codes.value ?? []).join('\n')], { type: 'text/plain' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(file);
    link.download = t('second_factor.download_name');
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href));
}
</script>

<template>
    <section aria-labelledby="second_factor_title" class="flex flex-col gap-4">
        <h2 id="second_factor_title" class="text-[16px] font-semibold text-[#171B21]">{{ t('second_factor.title') }}</h2>
        <p class="text-[13px] text-[#454C57]">{{ active ? t('second_factor.active') : t('second_factor.inactive') }}</p>
        <StatusRegion />

        <div v-if="codes?.length" class="flex flex-col gap-2">
            <h3 id="second_factor_codes_title" tabindex="-1" class="text-[14px] font-semibold text-[#171B21]">{{ t('second_factor.codes_title') }}</h3>
            <p class="text-[13px] text-[#454C57]">{{ t('second_factor.codes_intro') }}</p>
            <ul data-testid="recovery-codes" class="flex flex-col gap-1 rounded-md bg-[#EEF2F7] px-3 py-2 font-mono text-[13px] text-[#171B21]">
                <li v-for="code in codes" :key="code">{{ code }}</li>
            </ul>
            <div class="flex gap-2">
                <button type="button" class="rounded-md border border-[#848B95] px-3 py-2 text-[13px] font-semibold text-[#171B21]" @click="copyCodes">{{ t('second_factor.copy') }}</button>
                <button type="button" class="rounded-md border border-[#848B95] px-3 py-2 text-[13px] font-semibold text-[#171B21]" @click="downloadCodes">{{ t('second_factor.download') }}</button>
            </div>
        </div>

        <form v-if="enrolment" class="flex flex-col gap-4" novalidate @submit.prevent="confirm">
            <p class="text-[13px] text-[#454C57]">{{ t('second_factor.scan_intro') }}</p>
            <img :src="enrolment.qr" :alt="t('second_factor.qr_alt')" width="200" height="200" />
            <p class="text-[13px] text-[#171B21]">
                {{ t('second_factor.key_label') }}: <span data-testid="second-factor-key" class="font-mono">{{ enrolment.secret }}</span>
            </p>
            <ErrorSummary :errors="confirmation.errors" prefix="second_factor_" />
            <TextField id="second_factor_code" v-model="confirmation.code" :label="t('second_factor.code_label')" autocomplete="one-time-code" :error="confirmation.errors.code" />
            <button type="submit" :disabled="confirmation.processing" class="rounded-md bg-[#1F3A5F] px-3 py-2 text-[13px] font-semibold text-white">{{ t('second_factor.confirm') }}</button>
        </form>

        <form class="flex flex-col gap-4" novalidate @submit.prevent="active ? renew() : begin()">
            <p v-if="active" class="text-[13px] text-[#454C57]">{{ remainingLabel }}</p>
            <ErrorSummary :errors="password.errors" prefix="second_factor_" />
            <TextField id="second_factor_current_password" v-model="password.current_password" :label="t('fields.current_password')" type="password" autocomplete="current-password" :error="password.errors.current_password" />
            <div class="flex gap-2">
                <button v-if="!active" type="submit" :disabled="password.processing" class="rounded-md bg-[#1F3A5F] px-3 py-2 text-[13px] font-semibold text-white">{{ t('second_factor.enable') }}</button>
                <button v-if="active" type="submit" :disabled="password.processing" class="rounded-md bg-[#1F3A5F] px-3 py-2 text-[13px] font-semibold text-white">{{ t('second_factor.new_codes') }}</button>
                <button v-if="active" type="button" :disabled="password.processing" class="rounded-md border border-[#848B95] px-3 py-2 text-[13px] font-semibold text-[#171B21]" @click="turnOff">{{ t('second_factor.disable') }}</button>
            </div>
        </form>
    </section>
</template>
