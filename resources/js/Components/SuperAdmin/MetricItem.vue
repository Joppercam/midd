<template>
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-gray-900">{{ label }}</span>
        <span class="text-sm text-gray-500">
            {{ formattedValue }} {{ unit }}
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: String,
    value: [String, Number],
    unit: String,
})

const formattedValue = computed(() => {
    if (typeof props.value === 'number') {
        if (props.value >= 1000000) {
            return (props.value / 1000000).toFixed(1) + 'M'
        } else if (props.value >= 1000) {
            return (props.value / 1000).toFixed(1) + 'K'
        } else {
            return props.value.toLocaleString()
        }
    }
    return props.value
})
</script>