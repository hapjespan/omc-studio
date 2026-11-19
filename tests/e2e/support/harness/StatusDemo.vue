<script setup lang="ts">
import defaults from '../../../../resources/data/default-colours.json';
import StatusRegion from '../../../../resources/js/components/feedback/StatusRegion.vue';
import { announce } from '../../../../resources/js/components/feedback/status';

const colours = [...new Set(Object.values(defaults.areas).flatMap((levels) => Object.values(levels)).filter((value): value is string => value !== null))];
</script>

<template>
    <main class="p-4">
        <h1>Status</h1>
        <StatusRegion />
        <button id="post" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5" @click="announce('Alles opgeslagen')">Meld</button>
        <ul class="mt-3 flex flex-col gap-2">
            <li v-for="colour in colours" :key="colour" :style="{ background: colour }" class="p-2">
                <button type="button" class="swatch rounded-md border border-[#848B95] bg-white px-3 py-1.5">{{ colour }}</button>
            </li>
        </ul>
    </main>
</template>
