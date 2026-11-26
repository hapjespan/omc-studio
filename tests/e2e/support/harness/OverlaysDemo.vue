<script setup lang="ts">
import { ref } from 'vue';
import Dialog from '../../../../resources/js/components/overlays/Dialog.vue';
import SidePanel from '../../../../resources/js/components/overlays/SidePanel.vue';

const dialogOpen = ref(false);
const panelOpen = ref(false);
const text = ref('');
</script>

<template>
    <main class="flex flex-col gap-3 p-4">
        <h1>Overlays</h1>
        <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5" @click="dialogOpen = true">Open dialoog</button>
        <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5" @click="panelOpen = true">Open paneel</button>
        <Dialog v-model:open="dialogOpen" title="Dialoog" description="Een dialoog" :dirty="text !== ''">
            <label for="dialog-text">Tekst</label>
            <input id="dialog-text" v-model="text" class="ml-2 border border-[#848B95]" />
        </Dialog>
        <SidePanel v-model:open="panelOpen" title="Paneel" :dirty="text !== ''">
            <label for="panel-text">Tekst</label>
            <input id="panel-text" v-model="text" class="ml-2 border border-[#848B95]" />
        </SidePanel>
    </main>
</template>
