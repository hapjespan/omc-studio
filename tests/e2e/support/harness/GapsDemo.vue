<script setup lang="ts">
import { ref } from 'vue';
import InlineField from '../../../../resources/js/components/fields/InlineField.vue';
import NativeSelect from '../../../../resources/js/components/fields/NativeSelect.vue';
import ReportTable from '../../../../resources/js/components/ui/ReportTable.vue';

const share = ref('40,0');
const kind = ref('');
const columns = [{ key: 'name', label: 'Status' }, { key: 'count', label: 'Aantal', align: 'right' as const }];
</script>

<template>
    <main class="flex max-w-2xl flex-col gap-3 p-4">
        <h2>Hiaten</h2>
        <InlineField id="share" v-model="share" label="Percentage regel 1" suffix="%" hint="Samen 100%" inputmode="decimal" />
        <InlineField id="share-bad" label="Percentage regel 2" suffix="%" error="Te veel" />
        <p id="share-value">{{ share }}</p>
        <NativeSelect id="kind" v-model="kind" label="Soort" empty-label="Alle soorten" :options="[{ value: 'a', label: 'Eerste' }, { value: 'b', label: 'Tweede' }]" />
        <NativeSelect id="kind-bad" label="Soort verplicht" error="Kies een soort" :options="[{ value: 'a', label: 'Eerste' }]" />
        <p id="kind-value">[{{ kind }}]</p>
        <ReportTable caption="Samenvatting import" :columns="columns" :rows="[{ name: 'Nieuw', count: 12 }, { name: 'Fouten', count: 1234 }]" empty="Geen regels" row-header="name" />
        <ReportTable caption="Lege tabel" :columns="columns" :rows="[]" empty="Geen regels" />
    </main>
</template>
