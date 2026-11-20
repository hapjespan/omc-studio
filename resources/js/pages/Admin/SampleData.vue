<script setup lang="ts">
import Panel from '../../components/ui/Panel.vue';
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import AppButton from '../../components/ui/AppButton.vue';
import Callout from '../../components/ui/Callout.vue';
import Dialog from '../../components/overlays/Dialog.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

defineProps<{ present: boolean; name: string | null }>();
const confirming = ref(false);
const page = usePage<{ errors: Record<string, string>; flash: { status: string | null } }>();

function remove(): void {
    confirming.value = false;
    router.post(routes.sampleDataRemove());
}
</script>

<template>
    <Head :title="t('sample.page_title')" />
    <div class="flex max-w-3xl flex-col gap-4">
        <h1 class="font-display text-[20px] font-semibold tracking-[-0.2px]">{{ t('sample.page_title') }}</h1>
        <div v-if="page.props.errors.rule" role="alert"><ErrorSummary :errors="{ rule: page.props.errors.rule }" /></div>
        <Callout v-if="page.props.flash.status" tone="success"><span role="status">{{ page.props.flash.status }}</span></Callout>
        <Panel :padding="24">
            <template v-if="present">
                <p class="text-[13px]">{{ t('sample.page_present', { name: name ?? '' }) }}</p>
                <AppButton class="mt-4" variant="danger" @click="confirming = true">{{ t('sample.remove') }}</AppButton>
                <Dialog v-model:open="confirming" :title="t('sample.remove_title')" :description="t('sample.remove_consequence', { name: name ?? '' })">
                    <div class="flex gap-2">
                        <AppButton @click="confirming = false">{{ t('list.cancel') }}</AppButton>
                        <AppButton variant="danger" @click="remove">{{ t('sample.remove') }}</AppButton>
                    </div>
                </Dialog>
            </template>
            <p v-else class="text-[13px] text-muted">{{ t('sample.page_absent') }}</p>
        </Panel>
    </div>
</template>
