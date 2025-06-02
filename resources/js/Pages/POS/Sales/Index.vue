<template>
  <Head title="Ventas POS" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Historial de Ventas POS
        </h2>
        <div class="flex space-x-2">
          <Link
            :href="route('pos.terminal.index')"
            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva Venta
          </Link>
          <button
            @click="exportSales"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Exportar
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Filtros -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
              <input
                type="date"
                v-model="filters.date_from"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
              <input
                type="date"
                v-model="filters.date_to"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago</label>
              <select
                v-model="filters.payment_method"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
              <select
                v-model="filters.user"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                  {{ user.name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Resúmenes -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-blue-100 rounded-md">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Ventas Hoy</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.today_sales || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-green-100 rounded-md">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Ingresos Hoy</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatCurrency(stats.today_revenue || 0) }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-purple-100 rounded-md">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Ticket Promedio</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatCurrency(stats.average_ticket || 0) }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-yellow-100 rounded-md">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Artículos Vendidos</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.items_sold || 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabla de ventas -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <!-- Desktop -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    # Venta
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Fecha
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Cliente
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Artículos
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Subtotal
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    IVA
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Total
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Pago
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Usuario
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    #{{ sale.sale_number || sale.id }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDateTime(sale.created_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ sale.customer ? sale.customer.name : 'Cliente General' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                    {{ sale.items_count || sale.items?.length || 0 }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                    ${{ formatCurrency(sale.subtotal) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                    ${{ formatCurrency(sale.tax) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                    ${{ formatCurrency(sale.total) }}
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
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ sale.user?.name || '-' }}
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
                        class="text-green-600 hover:text-green-900"
                      >
                        Imprimir
                      </button>
                      <button
                        v-if="canRefund(sale)"
                        @click="refundSale(sale)"
                        class="text-red-600 hover:text-red-900"
                      >
                        Devolver
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
              <div v-for="sale in sales.data" :key="sale.id" 
                   class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-3">
                  <div>
                    <h3 class="text-base font-medium text-gray-900">
                      Venta #{{ sale.sale_number || sale.id }}
                    </h3>
                    <p class="text-sm text-gray-500">{{ formatDateTime(sale.created_at) }}</p>
                    <p class="text-sm text-gray-500">{{ sale.customer ? sale.customer.name : 'Cliente General' }}</p>
                  </div>
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    sale.payment_method === 'cash' ? 'bg-green-100 text-green-800' :
                    sale.payment_method === 'card' ? 'bg-blue-100 text-blue-800' :
                    'bg-purple-100 text-purple-800'
                  ]">
                    {{ getPaymentMethodLabel(sale.payment_method) }}
                  </span>
                </div>

                <!-- Detalles -->
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Artículos:</span>
                    <span class="text-gray-900 font-medium">{{ sale.items_count || 0 }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Subtotal:</span>
                    <span class="text-gray-900">${{ formatCurrency(sale.subtotal) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">IVA:</span>
                    <span class="text-gray-900">${{ formatCurrency(sale.tax) }}</span>
                  </div>
                  <div class="flex justify-between font-medium pt-2 border-t">
                    <span class="text-gray-700">Total:</span>
                    <span class="text-gray-900 text-lg">${{ formatCurrency(sale.total) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Usuario:</span>
                    <span class="text-gray-900">{{ sale.user?.name || '-' }}</span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 pt-3 border-t border-gray-200 flex justify-end space-x-3">
                  <button
                    @click="viewSale(sale)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                  >
                    Ver Detalle
                  </button>
                  <button
                    @click="printReceipt(sale)"
                    class="text-sm font-medium text-green-600 hover:text-green-500"
                  >
                    Imprimir
                  </button>
                </div>
              </div>

              <!-- Empty state -->
              <div v-if="!sales.data.length" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay ventas</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron ventas para el período seleccionado.</p>
              </div>
            </div>
          </div>

          <!-- Paginación -->
          <div v-if="sales.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Mostrando {{ sales.from }} a {{ sales.to }} de {{ sales.total }} ventas
              </div>
              <div class="flex space-x-2">
                <Link
                  v-for="link in sales.links"
                  :key="link.label"
                  :href="link.url"
                  :class="[
                    'px-3 py-2 text-sm rounded-md',
                    link.active
                      ? 'bg-indigo-600 text-white'
                      : 'bg-white text-gray-700 hover:bg-gray-50',
                    !link.url && 'opacity-50 cursor-not-allowed'
                  ]"
                  :disabled="!link.url"
                  v-html="link.label"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  sales: Object,
  users: Array,
  stats: Object,
  filters: Object,
});

const filters = ref({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  payment_method: props.filters.payment_method || '',
  user: props.filters.user || '',
});

const applyFilter = () => {
  router.get(route('pos.sales.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const viewSale = (sale) => {
  router.get(route('pos.sales.show', sale));
};

const printReceipt = (sale) => {
  window.open(route('pos.sales.receipt', sale), '_blank');
};

const refundSale = (sale) => {
  if (confirm('¿Está seguro de procesar la devolución de esta venta?')) {
    router.post(route('pos.sales.refund', sale), {}, {
      preserveScroll: true,
      onSuccess: () => {
        alert('Devolución procesada exitosamente');
      }
    });
  }
};

const canRefund = (sale) => {
  // Lógica para determinar si se puede hacer devolución
  // Por ejemplo, dentro de 30 días y no procesada previamente
  const saleDate = new Date(sale.created_at);
  const now = new Date();
  const daysDiff = (now - saleDate) / (1000 * 60 * 60 * 24);
  return daysDiff <= 30 && !sale.refunded;
};

const exportSales = () => {
  window.location.href = route('pos.sales.export', filters.value);
};

const getPaymentMethodLabel = (method) => {
  const labels = {
    'cash': 'Efectivo',
    'card': 'Tarjeta',
    'transfer': 'Transferencia'
  };
  return labels[method] || method;
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-CL').format(amount);
};

const formatDateTime = (datetime) => {
  if (!datetime) return '-';
  return new Date(datetime).toLocaleString('es-CL', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>