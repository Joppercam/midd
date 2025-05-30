<template>
  <div>
    <dl :class="[
      'grid gap-5',
      columns === 2 ? 'grid-cols-1 sm:grid-cols-2' :
      columns === 3 ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3' :
      columns === 4 ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4' :
      'grid-cols-1'
    ]">
      <div
        v-for="(stat, index) in stats"
        :key="index"
        :class="[
          'relative overflow-hidden rounded-lg px-4 py-5 shadow sm:px-6',
          stat.bgColor || 'bg-white'
        ]"
      >
        <dt>
          <div :class="[
            'absolute rounded-md p-3',
            stat.iconBgColor || 'bg-indigo-500'
          ]">
            <component
              v-if="stat.iconComponent"
              :is="stat.iconComponent"
              class="h-6 w-6 text-white"
              aria-hidden="true"
            />
            <svg
              v-else-if="stat.icon"
              class="h-6 w-6 text-white"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                :d="stat.icon"
              />
            </svg>
          </div>
          <p :class="[
            'ml-16 truncate text-sm font-medium',
            stat.labelColor || 'text-gray-500'
          ]">
            {{ stat.label }}
          </p>
        </dt>
        <dd class="ml-16 flex items-baseline">
          <p :class="[
            'text-2xl font-semibold',
            stat.valueColor || 'text-gray-900'
          ]">
            {{ stat.prefix }}{{ formatValue(stat.value) }}{{ stat.suffix }}
          </p>
          <p
            v-if="stat.change"
            :class="[
              'ml-2 flex items-baseline text-sm font-semibold',
              stat.change > 0 ? 'text-green-600' : 'text-red-600'
            ]"
          >
            <ArrowUpIcon
              v-if="stat.change > 0"
              class="h-5 w-5 flex-shrink-0 self-center text-green-500"
              aria-hidden="true"
            />
            <ArrowDownIcon
              v-else
              class="h-5 w-5 flex-shrink-0 self-center text-red-500"
              aria-hidden="true"
            />
            <span class="sr-only">
              {{ stat.change > 0 ? 'Aumentó' : 'Disminuyó' }}
            </span>
            {{ Math.abs(stat.change) }}%
          </p>
          <p v-if="stat.changeLabel" class="ml-2 text-sm text-gray-500">
            {{ stat.changeLabel }}
          </p>
        </dd>
        <div v-if="stat.link" class="absolute inset-0">
          <Link :href="stat.link" class="focus:outline-none">
            <span class="sr-only">Ver {{ stat.label }}</span>
          </Link>
        </div>
      </div>
    </dl>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowDownIcon, ArrowUpIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  stats: {
    type: Array,
    required: true
  },
  columns: {
    type: Number,
    default: 4,
    validator: (value) => [1, 2, 3, 4].includes(value)
  },
  formatter: {
    type: Function,
    default: null
  }
})

const formatValue = (value) => {
  if (props.formatter) {
    return props.formatter(value)
  }
  
  if (typeof value === 'number') {
    return new Intl.NumberFormat('es-CL').format(value)
  }
  
  return value
}
</script>