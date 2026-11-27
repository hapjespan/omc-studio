<script setup lang="ts">
import AppButton from './ui/AppButton.vue';
import { keysWithPrefix, t } from '../i18n';

const props = defineProps<{ group: string; url: string }>();
const prefix = `exchange.sample.${props.group}.`;
const columns = keysWithPrefix(prefix).map((key) => ({ key, name: t(`exchange.${props.group}.${key.slice(prefix.length)}`) }));
</script>

<template>
    <div class="flex flex-wrap items-start gap-4">
        <AppButton :href="url">{{ t('csvx.sample_download') }}</AppButton>
        <dl class="flex min-w-0 flex-1 flex-col gap-1 text-[13px]">
            <div v-for="column in columns" :key="column.key" class="flex gap-2">
                <dt class="w-32 shrink-0 font-semibold">{{ column.name }}</dt>
                <dd>{{ t(column.key) }}</dd>
            </div>
        </dl>
    </div>
</template>
