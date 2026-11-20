<script setup lang="ts">
import { ref } from 'vue';
import StatusRegion from '../../../../resources/js/components/feedback/StatusRegion.vue';
import { announce } from '../../../../resources/js/components/feedback/status';
import FileField from '../../../../resources/js/components/fields/FileField.vue';
import ActionMenu from '../../../../resources/js/components/ui/ActionMenu.vue';
import AppButton from '../../../../resources/js/components/ui/AppButton.vue';
import Callout from '../../../../resources/js/components/ui/Callout.vue';
import CheckBox from '../../../../resources/js/components/ui/CheckBox.vue';
import ChipSelect from '../../../../resources/js/components/ui/ChipSelect.vue';
import IconButton from '../../../../resources/js/components/ui/IconButton.vue';
import Panel from '../../../../resources/js/components/ui/Panel.vue';
import Pill from '../../../../resources/js/components/ui/Pill.vue';
import SegmentedControl from '../../../../resources/js/components/ui/SegmentedControl.vue';
import HeaderSlot from '../../../../resources/js/shell/HeaderSlot.vue';
import { shellLayout, useQuietStatus, useWideMain } from '../../../../resources/js/shell/layout';
import PageHeader from '../../../../resources/js/shell/PageHeader.vue';

const overlay = ref('none');
const kind = ref('');
const checked = ref(false);
const picked = ref('');
const chosenAction = ref('');
const params = new URLSearchParams(location.search);
const withHeader = params.get('header') === '1';

if (withHeader) useWideMain();
if (params.get('quiet') === '1') useQuietStatus();
</script>

<template>
    <main :class="['flex max-w-2xl flex-col gap-3', shellLayout.widePadding ? 'p-6' : 'p-4']">
        <PageHeader v-if="shellLayout.headerSlots > 0" />
        <HeaderSlot v-if="withHeader" to="title"><h1 id="page-heading">Bekijken</h1></HeaderSlot>
        <HeaderSlot v-if="withHeader" to="actions"><AppButton>Exporteren</AppButton></HeaderSlot>
        <h2 v-if="!withHeader">Fundament</h2>
        <StatusRegion />
        <button id="post" type="button" @click="announce('Alles opgeslagen')">Meld</button>
        <SegmentedControl :items="[{ key: 'none', label: 'Geen' }, { key: 'modality', label: 'Modaliteit' }]" :active="overlay" label="Laag" @select="overlay = $event" />
        <ChipSelect id="kind" v-model="kind" label="Soort" :options="[{ value: '', label: 'Alle' }, { value: 'a', label: 'Eerste' }]" />
        <Panel id="panel" :padding="24" heading="Exporteren"><p>Inhoud</p></Panel>
        <FileField id="upload" label="Bestand" hint="Alleen JSON" accept=".json" @pick="picked = $event?.name ?? ''" />
        <p id="picked">{{ picked }}</p>
        <AppButton id="link" href="/export.json" download="export.json">Alles exporteren</AppButton>
        <AppButton id="plain">Gewoon</AppButton>
        <div class="flex gap-2">
            <Pill id="pill" tone="danger">fout</Pill>
            <Callout id="callout" tone="danger" label="Mislukt:">Het bestand is niet gelezen</Callout>
        </div>
        <CheckBox v-model="checked" aria-label="Rij kiezen" />
        <IconButton label="Verwijderen" icon="close" :size="36" />
        <IconButton label="Passend maken" icon="fit" />
        <ActionMenu label="Meer acties" :items="[{ key: 'copy', label: 'Kopiëren' }, { key: 'delete', label: 'Verwijderen' }]" @select="chosenAction = $event" />
        <p id="action">{{ chosenAction }}</p>
        <div class="flex gap-2">
            <span id="swatch-over" class="inline-block h-6 w-6 bg-over-budget" />
            <span id="swatch-drop" class="inline-block h-6 w-6 bg-drop-zone" />
        </div>
    </main>
</template>
