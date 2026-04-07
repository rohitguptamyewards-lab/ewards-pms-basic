<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: String,
    value: [String, Number],
    color: { type: String, default: 'blue' },
});

const palette = {
    blue: { bg: 'bg-[#f5f0ff]', border: 'border-[#ddd0f7]', text: 'text-[#4e1a77]' },
    green: { bg: 'bg-green-50', border: 'border-green-100', text: 'text-green-700' },
    red: { bg: 'bg-red-50', border: 'border-red-100', text: 'text-red-700' },
    yellow: { bg: 'bg-yellow-50', border: 'border-yellow-100', text: 'text-yellow-700' },
    gray: { bg: 'bg-gray-50', border: 'border-gray-100', text: 'text-gray-700' },
};

const p = computed(() => palette[props.color] || palette.blue);
</script>

<template>
    <div :class="[p.bg, p.border]" class="rounded-xl border px-4 py-3">
        <p class="text-xs font-medium text-gray-500">{{ label }}</p>
        <p :class="p.text" class="mt-1 text-2xl font-bold">{{ value }}</p>
    </div>
</template>
