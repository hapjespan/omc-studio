<script setup lang="ts">
import '../../../../resources/css/shell.css';
import DetailPanel from '../../../../resources/js/shell/DetailPanel.vue';
import { detail, openDetail } from '../../../../resources/js/shell/detail';

const rows = ['Eerste rij', 'Tweede rij'];
</script>

<template>
    <div class="shell">
    <main class="shell-main p-4">
        <h1>Shell</h1>
        <ul>
            <li v-for="row in rows" :key="row"><button type="button" class="row rounded-md border border-[#848B95] px-3 py-1.5" @click="(event) => openDetail(row, event.currentTarget as HTMLElement)">{{ row }}</button></li>
        </ul>
    </main>
    <DetailPanel />
    </div>
    <Teleport to="#detail-body" defer>
        <label>Notitie <input id="note" type="text" @input="detail.dirty = true" /></label>
    </Teleport>
</template>
