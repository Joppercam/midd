<template>
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div :class="statusClasses">
                <div :class="['h-2', 'w-2', 'rounded-full', dotClasses]"></div>
            </div>
            <span class="text-sm font-medium text-gray-900">{{ label }}</span>
        </div>
        <div class="text-sm text-gray-500">
            {{ value }} {{ unit }}
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: String,
    status: String,
    value: [String, Number],
    unit: String,
})

const statusClasses = computed(() => {
    const baseClasses = 'flex items-center justify-center h-4 w-4'
    
    switch (props.status) {
        case 'healthy':
        case 'active':
            return `${baseClasses} bg-green-100`
        case 'warning':
            return `${baseClasses} bg-yellow-100`
        case 'critical':
        case 'unhealthy':
            return `${baseClasses} bg-red-100`
        default:
            return `${baseClasses} bg-gray-100`
    }
})

const dotClasses = computed(() => {
    switch (props.status) {
        case 'healthy':
        case 'active':
            return 'bg-green-600'
        case 'warning':
            return 'bg-yellow-600'
        case 'critical':
        case 'unhealthy':
            return 'bg-red-600'
        default:
            return 'bg-gray-400'
    }
})
</script>

