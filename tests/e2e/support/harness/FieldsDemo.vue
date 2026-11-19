<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue';
import ErrorSummary from '../../../../resources/js/components/fields/ErrorSummary.vue';
import SelectField from '../../../../resources/js/components/fields/SelectField.vue';
import TextArea from '../../../../resources/js/components/fields/TextArea.vue';
import TextField from '../../../../resources/js/components/fields/TextField.vue';
import { focusFirstInvalid } from '../../../../resources/js/components/fields/focusFirstInvalid';

const form = reactive({ budget: '', kind: undefined as string | undefined, remark: 'blijft staan' });
const errors = ref<Record<string, string>>({});
const root = ref<HTMLElement>();

async function save(): Promise<void> {
    errors.value = {};
    if (form.budget === '') errors.value.budget = 'Budget: vul een bedrag in van € 0,00 tot € 999.999.999,99, met een komma voor de centen';
    if (form.kind === undefined) errors.value.kind = 'Soort: kies een soort';
    await nextTick();
    focusFirstInvalid(root.value);
}
</script>

<template>
    <main ref="root" class="flex max-w-md flex-col gap-3 p-4">
        <h1>Velden</h1>
        <ErrorSummary :errors="errors" />
        <TextField id="budget" v-model="form.budget" label="Budget" hint="Met een komma voor de centen" :error="errors.budget" />
        <SelectField id="kind" v-model="form.kind" label="Soort" placeholder="Kies" :options="[{ value: 'a', label: 'Eerste' }, { value: 'b', label: 'Tweede' }]" :error="errors.kind" />
        <TextArea id="remark" v-model="form.remark" label="Opmerking" />
        <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5" @click="save">Opslaan</button>
    </main>
</template>
