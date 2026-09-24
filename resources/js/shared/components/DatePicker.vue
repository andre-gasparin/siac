<script setup lang="ts">
defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

function onInput(event: Event) {
    const val = (event.target as HTMLInputElement).value;
    emit('update:modelValue', val);
}
</script>

<template>
    <div class="relative w-full sm:w-36">
        <input
            type="date"
            :value="modelValue"
            @input="onInput"
            class="flex h-9 w-full cursor-pointer rounded-md border border-input bg-background px-2.5 py-1 text-xs font-medium text-foreground ring-offset-background transition-colors hover:bg-accent/50 focus:ring-2 focus:ring-ring focus:ring-offset-1 focus:outline-none dark:scheme-dark [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-70 hover:[&::-webkit-calendar-picker-indicator]:opacity-100"
        />
    </div>
</template>
