<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import ErrorSummary from '../../../../resources/js/components/fields/ErrorSummary.vue';
import TextField from '../../../../resources/js/components/fields/TextField.vue';

const props = defineProps<{ initial: string }>();
const emit = defineEmits<{ save: [name: string]; dirty: [value: boolean] }>();
const name = ref(props.initial);
const errors = reactive<Record<string, string>>({});

watch(name, (value) => emit('dirty', value !== props.initial));

function submit(): void {
    delete errors.name;
    if (name.value.trim() === '') {
        errors.name = 'Naam: vul een naam in';
        return;
    }
    emit('save', name.value.trim());
}
</script>

<template>
    <form novalidate class="flex flex-col gap-3" @submit.prevent="submit">
        <ErrorSummary :errors="errors" />
        <TextField id="name" v-model="name" label="Naam" :error="errors.name" />
        <button type="submit" class="rounded-md border border-[#848B95] px-3 py-1.5">Opslaan</button>
    </form>
</template>
