<script setup lang="ts">
import AppButton from '../components/ui/AppButton.vue';
import { onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { announce } from '../components/feedback/status';
import { t } from '../i18n';
import { routes } from '../routes';
import { startIdleTimer } from './idle';
import { inReauthWindow, reauth } from './session';

const warning = ref(false);

const idle = startIdleTimer(() => {
    if (inReauthWindow()) {
        reauth.value = true;
        return;
    }
    warning.value = true;
    void announce(t('session.warning'));
});

const stopListening = router.on('start', () => {
    warning.value = false;
    idle.touch();
});

async function extend(): Promise<void> {
    const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
    const response = await fetch(routes.keepAlive(), { method: 'POST', credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' } });
    if (!response.ok) {
        window.location.reload();
        return;
    }
    warning.value = false;
    idle.touch();
    void announce(t('session.extended'));
}

onBeforeUnmount(() => {
    stopListening();
    idle.stop();
});
</script>

<template>
    <div v-if="warning && !reauth" class="fixed bottom-4 right-4 z-40 flex items-center gap-3 rounded-md border border-edge bg-white px-4 py-3 text-[13px] text-ink shadow-float">
        <span>{{ t('session.warning') }}</span>
        <AppButton variant="primary" @click="extend">{{ t('session.extend') }}</AppButton>
    </div>
</template>
