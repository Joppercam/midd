<template>
  <Head title="Historial de Ventas POS" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Historial de Ventas POS
        </h2>
        <div class="flex space-x-2">
          <Link
            :href="route('pos.terminal.index')"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Ir al Terminal
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Estadísticas de ventas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-green-100 rounded-md">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Ventas de Hoy</p>
                <p class="text-2xl font-semibold text-gray-900">15</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-blue-100 rounded-md">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total de Hoy</p>
                <p class="text-2xl font-semibold text-gray-900">$1,245,000</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-purple-100 rounded-md">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Productos Vendidos</p>
                <p class="text-2xl font-semibold text-gray-900">87</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-yellow-100 rounded-md">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2-2z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Venta Promedio</p>
                <p class="text-2xl font-semibold text-gray-900">$83,000</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
              <input
                v-model="filters.date_from"
                type="date"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
              <input
                v-model="filters.date_to"
                type="date"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago</label>
              <select
                v-model="filters.payment_method"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
              </select>
            </div>
            <div class="flex items-end space-x-2">
              <button
                @click="applyFilters"
                class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium"
              >
                Filtrar
              </button>
              <button
                @click="clearFilters"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm font-medium"
              >
                Limpiar
              </button>
            </div>
          </div>
        </div>

        <!-- Lista de ventas -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <!-- Desktop -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Número de Venta
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Fecha
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Cliente
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Método de Pago
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Total
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Estado
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="sale in mockSales" :key="sale.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ sale.sale_number }}</div>
                    <div class="text-sm text-gray-500">{{ sale.cashier }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDate(sale.date) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ sale.customer || 'Cliente General' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span :class="[
                      'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                      sale.payment_method === 'cash' ? 'bg-green-100 text-green-800' :
                      sale.payment_method === 'card' ? 'bg-blue-100 text-blue-800' :
                      'bg-purple-100 text-purple-800'
                    ]">
                      {{ getPaymentMethodLabel(sale.payment_method) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                    ${{ formatPrice(sale.total) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span :class="[
                      'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                      sale.status === 'completed' ? 'bg-green-100 text-green-800' :
                      sale.status === 'refunded' ? 'bg-red-100 text-red-800' :
                      'bg-yellow-100 text-yellow-800'
                    ]">
                      {{ getStatusLabel(sale.status) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                      <button
                        @click="viewSale(sale)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Ver
                      </button>
                      <button
                        @click="printReceipt(sale)"
                        class="text-gray-600 hover:text-gray-900"
                      >
                        Imprimir
                      </button>
                      <button
                        v-if="sale.status === 'completed'"
                        @click="refundSale(sale)"
                        class="text-red-600 hover:text-red-900"
                      >
                        Reembolsar
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile -->
          <div class="sm:hidden">
            <div class="space-y-3 p-4">
              <div v-for="sale in mockSales" :key="sale.id" 
                   class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-3">
                  <div>
                    <h3 class="text-base font-medium text-gray-900">{{ sale.sale_number }}</h3>
                    <p class="text-sm text-gray-500">{{ sale.cashier }}</p>
                  </div>
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    sale.status === 'completed' ? 'bg-green-100 text-green-800' :
                    sale.status === 'refunded' ? 'bg-red-100 text-red-800' :
                    'bg-yellow-100 text-yellow-800'
                  ]">
                    {{ getStatusLabel(sale.status) }}
                  </span>
                </div>

                <!-- Info -->
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Fecha:</span>
                    <span class="text-gray-900">{{ formatDate(sale.date) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Cliente:</span>
                    <span class="text-gray-900">{{ sale.customer || 'Cliente General' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Pago:</span>
                    <span class="text-gray-900">{{ getPaymentMethodLabel(sale.payment_method) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Total:</span>
                    <span class="text-gray-900 font-bold">${{ formatPrice(sale.total) }}</span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 pt-3 border-t border-gray-200 flex justify-end space-x-3">
                  <button
                    @click="viewSale(sale)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                  >
                    Ver detalles
                  </button>
                  <button
                    @click="printReceipt(sale)"
                    class="text-sm font-medium text-gray-600 hover:text-gray-500"
                  >
                    Imprimir
                  </button>
                </div>
              </div>

              <!-- Empty state -->
              <div v-if="!mockSales.length" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay ventas</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron ventas con los filtros aplicados.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Filtros reactivos
const filters = ref({
  date_from: '',
  date_to: '',
  payment_method: '',
});

// Datos de ejemplo para mostrar en la interfaz
const mockSales = ref([
  {
    id: 1,
    sale_number: 'POS-20231201001',
    date: '2023-12-01 14:30:00',
    cashier: 'Juan Pérez',
    customer: 'María González',
    payment_method: 'cash',
    total: 125990,
    status: 'completed',
  },
  {
    id: 2,
    sale_number: 'POS-20231201002',
    date: '2023-12-01 15:45:00',
    cashier: 'Juan Pérez',
    customer: null,
    payment_method: 'card',
    total: 89990,
    status: 'completed',
  },
  {
    id: 3,
    sale_number: 'POS-20231201003',
    date: '2023-12-01 16:20:00',
    cashier: 'Juan Pérez',
    customer: 'Carlos López',
    payment_method: 'transfer',
    total: 299990,
    status: 'completed',
  },
  {
    id: 4,
    sale_number: 'POS-20231201004',
    date: '2023-12-01 17:10:00',
    cashier: 'Juan Pérez',
    customer: null,
    payment_method: 'cash',
    total: 45990,
    status: 'refunded',
  },
]);

// Métodos
const applyFilters = () => {
  // TODO: Implementar filtros cuando se conecte con el backend
  console.log('Aplicando filtros:', filters.value);
};

const clearFilters = () => {
  filters.value = {
    date_from: '',
    date_to: '',
    payment_method: '',
  };
};

const viewSale = (sale) => {
  // TODO: Implementar vista detallada de venta
  alert(`Ver detalles de venta: ${sale.sale_number}`);
};

const printReceipt = (sale) => {
  // TODO: Implementar impresión de recibo
  alert(`Imprimir recibo de venta: ${sale.sale_number}`);
};

const refundSale = (sale) => {
  if (confirm(`¿Está seguro de reembolsar la venta ${sale.sale_number}?`)) {
    // TODO: Implementar reembolso
    alert(`Reembolso procesado para venta: ${sale.sale_number}`);
    sale.status = 'refunded';
  }
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-CL', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatPrice = (price) => {
  return parseFloat(price || 0).toLocaleString('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
};

const getPaymentMethodLabel = (method) => {
  const labels = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    transfer: 'Transferencia',
  };
  return labels[method] || method;
};

const getStatusLabel = (status) => {
  const labels = {
    completed: 'Completada',
    refunded: 'Reembolsada',
    pending: 'Pendiente',
  };
  return labels[status] || status;
};
</script>