<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { t } from '../i18n';
import { routes } from '../routes';
</script>

<template>
    <main>
        <h1>OMC Studio</h1>
        <button type="button" @click="router.post(routes.signOut())">{{ t('auth.sign_out') }}</button>
    </main>
</template>
