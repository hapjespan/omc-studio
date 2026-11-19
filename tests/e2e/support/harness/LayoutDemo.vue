<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { createBrowserMeasurer } from '../../../../resources/js/layout/browserMeasurer';

const result = ref('');

onMounted(async () => {
    const requested: string[] = [];
    const original = document.fonts.load.bind(document.fonts);
    document.fonts.load = (font: string, text?: string) => {
        requested.push(font);
        return original(font, text);
    };
    const measurer = await createBrowserMeasurer();
    const widths = (['Inter', 'Space Grotesk'] as const).map((family) =>
        [1, 2].map(() => measurer.width('Onbekende allocatie', { family, size: 14, weight: 600, italic: false })),
    );
    const loaded = ['Inter', 'Space Grotesk'].map((family) => document.fonts.check(`600 14px "${family}"`));
    result.value = JSON.stringify({ widths, loaded, requested });
});
</script>

<template>
    <main>
        <p id="result">{{ result }}</p>
    </main>
</template>
