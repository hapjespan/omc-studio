<script setup lang="ts">
import '../../../../resources/css/shell.css';
import MenuButton from '../../../../resources/js/shell/MenuButton.vue';
import Tabs from '../../../../resources/js/shell/Tabs.vue';
import TopBar from '../../../../resources/js/shell/TopBar.vue';
import { ref } from 'vue';

const open = ref(false);
const canvas = {
    id: 1,
    name: 'Voorbeeldcanvasmetheellangenaamzonderspaties-Voorbeeldcanvasmetheellangenaamzonderspaties',
    kind: 'canvas',
    archived: false,
    organisation: { id: 1, name: 'Stichting-Kinderopvang-en-Onderwijs-Stichting-Kinderopvang-en-Onderwijs', is_sample: true },
};
</script>

<template>
    <div class="shell bg-page text-ink">
        <header class="shell-top flex min-h-bar items-center border-b border-hairline bg-white px-4">
            <TopBar app-name="OMC Studio" role="administrator" :canvas="canvas" :fiscal-year="{ label: 'Boekjaar 2026', state: 'Concept' }">
                <template #tabs>
                    <div id="primary-tabs" class="shell-tabs" :data-open="open || undefined">
                        <Tabs role="administrator" :canvas="canvas" url="/" />
                    </div>
                </template>
                <template #menu><MenuButton v-model="open" /></template>
            </TopBar>
        </header>
        <main class="shell-main p-4"><h1>Top bar</h1></main>
    </div>
</template>
