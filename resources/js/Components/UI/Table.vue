<template>
  <div class="flex flex-col">
    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
        <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th
                  v-for="column in columns"
                  :key="column.key"
                  scope="col"
                  :class="[
                    'px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider',
                    column.align === 'center' ? 'text-center' : column.align === 'right' ? 'text-right' : 'text-left'
                  ]"
                >
                  <div
                    v-if="column.sortable"
                    class="group inline-flex cursor-pointer"
                    @click="$emit('sort', column.key)"
                  >
                    {{ column.label }}
                    <span
                      :class="[
                        'ml-2 flex-none rounded',
                        sortBy === column.key
                          ? 'bg-gray-200 text-gray-900 group-hover:bg-gray-300'
                          : 'invisible text-gray-400 group-hover:visible group-focus:visible'
                      ]"
                    >
                      <ChevronDownIcon
                        v-if="sortBy === column.key && sortOrder === 'desc'"
                        class="h-5 w-5"
                        aria-hidden="true"
                      />
                      <ChevronUpIcon
                        v-else
                        class="h-5 w-5"
                        aria-hidden="true"
                      />
                    </span>
                  </div>
                  <span v-else>{{ column.label }}</span>
                </th>
                <th v-if="hasActions" scope="col" class="relative px-6 py-3">
                  <span class="sr-only">Acciones</span>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr
                v-for="(row, index) in rows"
                :key="row.id || index"
                :class="{ 'hover:bg-gray-50': hoverable }"
              >
                <td
                  v-for="column in columns"
                  :key="column.key"
                  :class="[
                    'px-6 py-4 whitespace-nowrap',
                    column.class || 'text-sm text-gray-900',
                    column.align === 'center' ? 'text-center' : column.align === 'right' ? 'text-right' : 'text-left'
                  ]"
                >
                  <slot :name="`cell-${column.key}`" :row="row" :value="getCellValue(row, column.key)">
                    {{ getCellValue(row, column.key) }}
                  </slot>
                </td>
                <td v-if="hasActions" class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <slot name="actions" :row="row" />
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td :colspan="columns.length + (hasActions ? 1 : 0)" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                  <slot name="empty">
                    No hay datos para mostrar
                  </slot>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  columns: {
    type: Array,
    required: true
  },
  rows: {
    type: Array,
    default: () => []
  },
  sortBy: String,
  sortOrder: {
    type: String,
    default: 'asc',
    validator: (value) => ['asc', 'desc'].includes(value)
  },
  hoverable: {
    type: Boolean,
    default: true
  }
})

defineEmits(['sort'])

const hasActions = computed(() => !!props.$slots.actions)

const getCellValue = (row, key) => {
  const keys = key.split('.')
  return keys.reduce((value, k) => value?.[k], row)
}
</script>