<template>
    <span :class="badgeClasses">
        {{ badgeText }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: String,
    isActive: Boolean,
})

const badgeClasses = computed(() => {
    const baseClasses = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium'
    
    if (!props.isActive) {
        return `${baseClasses} bg-red-100 text-red-800`
    }
    
    switch (props.status) {
        case 'active':
            return `${baseClasses} bg-green-100 text-green-800`
        case 'trial':
            return `${baseClasses} bg-yellow-100 text-yellow-800`
        case 'cancelled':
            return `${baseClasses} bg-gray-100 text-gray-800`
        default:
            return `${baseClasses} bg-gray-100 text-gray-800`
    }
})

const badgeText = computed(() => {
    if (!props.isActive) {
        return 'Suspended'
    }
    
    switch (props.status) {
        case 'active':
            return 'Active'
        case 'trial':
            return 'Trial'
        case 'cancelled':
            return 'Cancelled'
        default:
            return 'Unknown'
    }
})
</script>