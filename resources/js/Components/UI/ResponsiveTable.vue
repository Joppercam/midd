<template>
  <div>
    <!-- Vista Desktop: Tabla tradicional -->
    <div class="hidden sm:block overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
      <table class="min-w-full divide-y divide-gray-300">
        <thead class="bg-gray-50">
          <tr>
            <th v-for="column in columns" :key="column.key" 
                :class="[
                  'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider',
                  column.align === 'center' ? 'text-center' : '',
                  column.align === 'right' ? 'text-right' : ''
                ]">
              {{ column.label }}
            </th>
            <th v-if="hasActions" class="relative px-6 py-3">
              <span class="sr-only">Acciones</span>
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="(item, index) in items" :key="item.id || index" class="hover:bg-gray-50">
            <td v-for="column in columns" :key="column.key" 
                :class="[
                  'px-6 py-4 whitespace-nowrap text-sm',
                  column.align === 'center' ? 'text-center' : '',
                  column.align === 'right' ? 'text-right' : ''
                ]">
              <slot :name="`cell-${column.key}`" :item="item" :value="getNestedProperty(item, column.key)">
                <span :class="column.class">{{ formatValue(getNestedProperty(item, column.key), column.format) }}</span>
              </slot>
            </td>
            <td v-if="hasActions" class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <slot name="actions" :item="item">
                <!-- Default actions -->
              </slot>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td :colspan="columns.length + (hasActions ? 1 : 0)" class="px-6 py-4 text-center text-sm text-gray-500">
              {{ emptyMessage }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Vista Móvil: Cards -->
    <div class="sm:hidden space-y-3">
      <div v-for="(item, index) in items" :key="item.id || index" 
           class="bg-white shadow rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
        
        <!-- Card Header (primeras 2 columnas como título) -->
        <div class="flex justify-between items-start mb-3">
          <div class="flex-1">
            <slot :name="`mobile-header`" :item="item">
              <div v-if="columns[0]" class="font-medium text-gray-900">
                <slot :name="`cell-${columns[0].key}`" :item="item" :value="getNestedProperty(item, columns[0].key)">
                  {{ formatValue(getNestedProperty(item, columns[0].key), columns[0].format) }}
                </slot>
              </div>
              <div v-if="columns[1]" class="text-sm text-gray-500 mt-1">
                <slot :name="`cell-${columns[1].key}`" :item="item" :value="getNestedProperty(item, columns[1].key)">
                  {{ formatValue(getNestedProperty(item, columns[1].key), columns[1].format) }}
                </slot>
              </div>
            </slot>
          </div>
          
          <!-- Badge o estado si existe -->
          <div v-if="statusColumn" class="ml-2">
            <slot :name="`cell-${statusColumn.key}`" :item="item" :value="getNestedProperty(item, statusColumn.key)">
              <span :class="[
                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                getStatusClass(getNestedProperty(item, statusColumn.key))
              ]">
                {{ formatValue(getNestedProperty(item, statusColumn.key), statusColumn.format) }}
              </span>
            </slot>
          </div>
        </div>

        <!-- Card Body (resto de columnas) -->
        <div class="space-y-2 text-sm">
          <div v-for="column in mobileColumns" :key="column.key" 
               class="flex justify-between items-center">
            <span class="text-gray-500">{{ column.label }}:</span>
            <span :class="['text-gray-900', column.class]">
              <slot :name="`cell-${column.key}`" :item="item" :value="getNestedProperty(item, column.key)">
                {{ formatValue(getNestedProperty(item, column.key), column.format) }}
              </slot>
            </span>
          </div>
        </div>

        <!-- Card Actions -->
        <div v-if="hasActions" class="mt-4 pt-3 border-t border-gray-200 flex justify-end space-x-3">
          <slot name="mobile-actions" :item="item">
            <slot name="actions" :item="item">
              <!-- Default actions -->
            </slot>
          </slot>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!items.length" class="bg-white shadow rounded-lg p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="mt-2 text-sm text-gray-500">{{ emptyMessage }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  columns: {
    type: Array,
    required: true
  },
  items: {
    type: Array,
    default: () => []
  },
  hasActions: {
    type: Boolean,
    default: false
  },
  emptyMessage: {
    type: String,
    default: 'No hay datos para mostrar'
  },
  statusColumn: {
    type: Object,
    default: null
  },
  mobileColumnsCount: {
    type: Number,
    default: 2 // Cuántas columnas mostrar en el header del card
  }
});

// Columnas para mostrar en el body del card móvil (excluyendo las primeras 2 y status)
const mobileColumns = computed(() => {
  return props.columns.filter((col, index) => {
    if (index < props.mobileColumnsCount) return false;
    if (props.statusColumn && col.key === props.statusColumn.key) return false;
    return true;
  });
});

// Obtener valor anidado de un objeto (soporta notación con punto)
const getNestedProperty = (obj, path) => {
  return path.split('.').reduce((acc, part) => acc && acc[part], obj);
};

// Formatear valores según el tipo
const formatValue = (value, format) => {
  if (value === null || value === undefined) return '-';
  
  switch (format) {
    case 'currency':
      return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP'
      }).format(value);
    
    case 'number':
      return new Intl.NumberFormat('es-CL').format(value);
    
    case 'date':
      if (!value) return '-';
      return new Date(value).toLocaleDateString('es-CL');
    
    case 'datetime':
      if (!value) return '-';
      return new Date(value).toLocaleString('es-CL');
    
    case 'percentage':
      return `${value}%`;
    
    case 'boolean':
      return value ? 'Sí' : 'No';
    
    default:
      return value;
  }
};

// Clases para badges de estado
const getStatusClass = (status) => {
  const statusClasses = {
    'active': 'bg-green-100 text-green-800',
    'inactive': 'bg-gray-100 text-gray-800',
    'pending': 'bg-yellow-100 text-yellow-800',
    'paid': 'bg-green-100 text-green-800',
    'overdue': 'bg-red-100 text-red-800',
    'sent': 'bg-blue-100 text-blue-800',
    'draft': 'bg-gray-100 text-gray-800',
    'cancelled': 'bg-red-100 text-red-800',
    'completed': 'bg-green-100 text-green-800',
    'processing': 'bg-blue-100 text-blue-800',
    'failed': 'bg-red-100 text-red-800'
  };
  
  return statusClasses[status?.toLowerCase()] || 'bg-gray-100 text-gray-800';
};
</script>