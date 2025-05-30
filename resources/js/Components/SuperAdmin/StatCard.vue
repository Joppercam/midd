<template>
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div :class="iconClasses">
                        <component :is="iconComponent" class="h-6 w-6" />
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">
                            {{ title }}
                        </dt>
                        <dd class="text-lg font-medium text-gray-900">
                            {{ value }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import {
    BuildingOfficeIcon,
    CheckCircleIcon,
    UsersIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    title: String,
    value: [String, Number],
    icon: String,
    color: {
        type: String,
        default: 'blue'
    }
})

const iconComponent = computed(() => {
    const icons = {
        'building': BuildingOfficeIcon,
        'check-circle': CheckCircleIcon,
        'users': UsersIcon,
        'currency-dollar': CurrencyDollarIcon,
        'chart-bar': ChartBarIcon,
        'clock': ClockIcon,
    }
    
    return icons[props.icon] || ChartBarIcon
})

const iconClasses = computed(() => {
    const colorClasses = {
        blue: 'bg-blue-500',
        green: 'bg-green-500',
        indigo: 'bg-indigo-500',
        emerald: 'bg-emerald-500',
        red: 'bg-red-500',
        yellow: 'bg-yellow-500',
    }
    
    return [
        'flex items-center justify-center h-10 w-10 rounded-md text-white',
        colorClasses[props.color] || colorClasses.blue
    ]
})
</script>