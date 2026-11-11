<script setup lang="ts">
defineProps<{ large?: boolean }>();
</script>

<template>
    <div class="flex min-w-0 flex-1 items-center">
        <img
            src="/logo.png"
            alt="Consucal"
            class="w-auto object-contain object-left"
            :class="large ? 'h-12 max-w-full' : 'h-8 max-w-[164px]'"
        />
    </div>
</template>
