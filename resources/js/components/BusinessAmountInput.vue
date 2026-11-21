<script setup lang="ts">
withDefaults(
    defineProps<{
        id?: string;
        modelValue: number | null;
        divisor?: number;
        disabled?: boolean;
    }>(),
    { divisor: 100 },
);
const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>();
function update(event: Event, divisor: number) {
    const value = (event.target as HTMLInputElement).value;
    emit(
        'update:modelValue',
        value === '' ? null : Math.round(Number(value) * divisor),
    );
}
</script>

<template>
    <input
        :id="id"
        type="number"
        min="0"
        step="0.01"
        :disabled="disabled"
        :value="modelValue === null ? '' : modelValue / divisor"
        placeholder="Pendiente"
        @input="update($event, divisor)"
    />
</template>
