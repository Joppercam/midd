<template>
  <Head title="Nómina" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Gestión de Nómina
        </h2>
        <button
          @click="processPayroll"
          class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Procesar Nómina
        </button>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Filtros y período -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
              <input
                type="month"
                v-model="filters.period"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
              <select
                v-model="filters.department"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                  {{ dept.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
              <select
                v-model="filters.status"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option value="pending">Pendiente</option>
                <option value="processed">Procesado</option>
                <option value="paid">Pagado</option>
              </select>
            </div>
            <div class="flex items-end">
              <button
                @click="exportPayroll"
                class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium"
              >
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Exportar
              </button>
            </div>
          </div>
        </div>

        <!-- Resúmenes -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-blue-100 rounded-md">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Empleados</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.total_employees || 0 }}</p>
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
                <p class="text-sm font-medium text-gray-500">Total Bruto</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatCurrency(summary.gross_total || 0) }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-red-100 rounded-md">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Descuentos</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatCurrency(summary.deductions_total || 0) }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-indigo-100 rounded-md">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Neto</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatCurrency(summary.net_total || 0) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabla de nómina -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <!-- Desktop -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Empleado
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Salario Base
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Bonos
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Total Bruto
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    AFP
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Salud
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Otros Desc.
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Total Neto
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
                <tr v-for="payroll in payrolls.data" :key="payroll.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div class="text-sm font-medium text-gray-900">
                        {{ payroll.employee.first_name }} {{ payroll.employee.last_name }}
                      </div>
                      <div class="text-sm text-gray-500">
                        {{ payroll.employee.rut }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                    ${{ formatCurrency(payroll.base_salary) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                    ${{ formatCurrency(payroll.bonuses) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                    ${{ formatCurrency(payroll.gross_total) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                    -${{ formatCurrency(payroll.afp_deduction) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                    -${{ formatCurrency(payroll.health_deduction) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                    -${{ formatCurrency(payroll.other_deductions) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-green-600">
                    ${{ formatCurrency(payroll.net_total) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span :class="[
                      'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                      payroll.status === 'paid' ? 'bg-green-100 text-green-800' :
                      payroll.status === 'processed' ? 'bg-blue-100 text-blue-800' :
                      'bg-gray-100 text-gray-800'
                    ]">
                      {{ payroll.status === 'paid' ? 'Pagado' : 
                         payroll.status === 'processed' ? 'Procesado' : 'Pendiente' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                      <button
                        @click="viewPayslip(payroll)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Ver
                      </button>
                      <button
                        v-if="payroll.status !== 'paid'"
                        @click="editPayroll(payroll)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Editar
                      </button>
                      <button
                        v-if="payroll.status === 'processed'"
                        @click="markAsPaid(payroll)"
                        class="text-green-600 hover:text-green-900"
                      >
                        Marcar Pagado
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
              <div v-for="payroll in payrolls.data" :key="payroll.id" 
                   class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-3">
                  <div>
                    <h3 class="text-base font-medium text-gray-900">
                      {{ payroll.employee.first_name }} {{ payroll.employee.last_name }}
                    </h3>
                    <p class="text-sm text-gray-500">{{ payroll.employee.rut }}</p>
                  </div>
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    payroll.status === 'paid' ? 'bg-green-100 text-green-800' :
                    payroll.status === 'processed' ? 'bg-blue-100 text-blue-800' :
                    'bg-gray-100 text-gray-800'
                  ]">
                    {{ payroll.status === 'paid' ? 'Pagado' : 
                       payroll.status === 'processed' ? 'Procesado' : 'Pendiente' }}
                  </span>
                </div>

                <!-- Detalles de pago -->
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Salario Base:</span>
                    <span class="text-gray-900">${{ formatCurrency(payroll.base_salary) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Bonos:</span>
                    <span class="text-gray-900">${{ formatCurrency(payroll.bonuses) }}</span>
                  </div>
                  <div class="flex justify-between font-medium pt-2 border-t">
                    <span class="text-gray-700">Total Bruto:</span>
                    <span class="text-gray-900">${{ formatCurrency(payroll.gross_total) }}</span>
                  </div>
                  <div class="flex justify-between text-red-600">
                    <span>AFP:</span>
                    <span>-${{ formatCurrency(payroll.afp_deduction) }}</span>
                  </div>
                  <div class="flex justify-between text-red-600">
                    <span>Salud:</span>
                    <span>-${{ formatCurrency(payroll.health_deduction) }}</span>
                  </div>
                  <div class="flex justify-between text-red-600">
                    <span>Otros:</span>
                    <span>-${{ formatCurrency(payroll.other_deductions) }}</span>
                  </div>
                  <div class="flex justify-between font-medium pt-2 border-t text-green-600">
                    <span>Total Neto:</span>
                    <span class="text-lg">${{ formatCurrency(payroll.net_total) }}</span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 pt-3 border-t border-gray-200 flex justify-end space-x-3">
                  <button
                    @click="viewPayslip(payroll)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                  >
                    Ver Liquidación
                  </button>
                  <button
                    v-if="payroll.status === 'processed'"
                    @click="markAsPaid(payroll)"
                    class="text-sm font-medium text-green-600 hover:text-green-500"
                  >
                    Marcar Pagado
                  </button>
                </div>
              </div>

              <!-- Empty state -->
              <div v-if="!payrolls.data.length" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay datos de nómina</h3>
                <p class="mt-1 text-sm text-gray-500">Procesa la nómina para este período.</p>
              </div>
            </div>
          </div>

          <!-- Paginación -->
          <div v-if="payrolls.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Mostrando {{ payrolls.from }} a {{ payrolls.to }} de {{ payrolls.total }} registros
              </div>
              <div class="flex space-x-2">
                <Link
                  v-for="link in payrolls.links"
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
  payrolls: Object,
  departments: Array,
  summary: Object,
  filters: Object,
});

const filters = ref({
  period: props.filters.period || new Date().toISOString().slice(0, 7),
  department: props.filters.department || '',
  status: props.filters.status || '',
});

const applyFilter = () => {
  router.get(route('hrm.payroll.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const processPayroll = () => {
  if (confirm('¿Está seguro de procesar la nómina para el período seleccionado?')) {
    router.post(route('hrm.payroll.process'), {
      period: filters.value.period
    }, {
      preserveScroll: true,
      onSuccess: () => {
        alert('Nómina procesada exitosamente');
      }
    });
  }
};

const viewPayslip = (payroll) => {
  window.open(route('hrm.payroll.payslip', payroll), '_blank');
};

const editPayroll = (payroll) => {
  router.get(route('hrm.payroll.edit', payroll));
};

const markAsPaid = (payroll) => {
  if (confirm('¿Marcar esta nómina como pagada?')) {
    router.put(route('hrm.payroll.mark-paid', payroll), {}, {
      preserveScroll: true,
    });
  }
};

const exportPayroll = () => {
  window.location.href = route('hrm.payroll.export', filters.value);
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-CL').format(amount);
};
</script>