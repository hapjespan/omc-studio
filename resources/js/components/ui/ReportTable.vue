<script setup lang="ts">
export interface ReportColumn {
    key: string;
    label: string;
    align?: 'left' | 'right';
}

defineProps<{ caption: string; columns: ReportColumn[]; rows: Record<string, unknown>[]; empty: string; rowHeader?: string }>();

const alignment = (column: ReportColumn): string => (column.align === 'right' ? 'text-right tabular-nums' : 'text-left');
</script>

<template>
    <div class="overflow-x-auto rounded-card border border-hairline bg-white">
        <table class="w-full border-collapse text-[13px] text-ink">
            <caption class="sr-only">{{ caption }}</caption>
            <thead>
                <tr><th v-for="column in columns" :key="column.key" scope="col" :class="['whitespace-nowrap px-3 py-2.5 text-[11.5px] font-semibold text-secondary', alignment(column)]">{{ column.label }}</th></tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in rows" :key="index" class="border-t border-hairline align-top">
                    <template v-for="column in columns" :key="column.key">
                        <th v-if="column.key === rowHeader" scope="row" :class="['px-3 py-2.5 font-normal', alignment(column)]"><slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">{{ row[column.key] }}</slot></th>
                        <td v-else :class="['px-3 py-2.5', alignment(column)]"><slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">{{ row[column.key] }}</slot></td>
                    </template>
                </tr>
                <tr v-if="rows.length === 0" class="border-t border-hairline"><td :colspan="columns.length" class="px-3 py-2.5 text-muted">{{ empty }}</td></tr>
            </tbody>
        </table>
    </div>
</template>
