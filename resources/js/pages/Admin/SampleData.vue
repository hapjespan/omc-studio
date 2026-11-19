<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Dialog from '../../components/overlays/Dialog.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

defineProps<{ present: boolean; name: string | null }>();
const confirming = ref(false);

function remove(): void {
    confirming.value = false;
    router.post(routes.sampleDataRemove());
}
</script>

<template>
    <Head :title="t('sample.page_title')" />
    <h1 class="text-[20px] font-semibold">{{ t('sample.page_title') }}</h1>
    <template v-if="present">
        <p class="mt-3 text-[13px]">{{ t('sample.page_present', { name: name ?? '' }) }}</p>
        <button type="button" class="mt-3 rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E]" @click="confirming = true">{{ t('sample.remove') }}</button>
        <Dialog v-model:open="confirming" :title="t('sample.remove_title')" :description="t('sample.remove_consequence', { name: name ?? '' })">
            <div class="flex gap-2">
                <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="confirming = false">{{ t('list.cancel') }}</button>
                <button type="button" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] font-semibold text-[#A12A1E]" @click="remove">{{ t('sample.remove') }}</button>
            </div>
        </Dialog>
    </template>
    <p v-else class="mt-3 text-[13px]">{{ t('sample.page_absent') }}</p>
</template>
