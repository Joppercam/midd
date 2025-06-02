<template>
  <Head title="Gestión de Vacaciones" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Gestión de Vacaciones
        </h2>
        <div class="flex space-x-2">
          <Link
            :href="route('hrm.leaves.create')"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva Solicitud
          </Link>
          <Link
            :href="route('hrm.leaves.calendar')"
            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Calendario
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Estadísticas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-blue-100 rounded-md">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Solicitudes</p>
                <p class="text-2xl font-semibold text-gray-900">{{ statistics.total_requests || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-yellow-100 rounded-md">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Pendientes</p>
                <p class="text-2xl font-semibold text-gray-900">{{ statistics.pending_requests || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-green-100 rounded-md">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Aprobadas</p>
                <p class="text-2xl font-semibold text-gray-900">{{ statistics.approved_requests || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-red-100 rounded-md">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Rechazadas</p>
                <p class="text-2xl font-semibold text-gray-900">{{ statistics.rejected_requests || 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
              <select
                v-model="filters.status"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option value="pending">Pendiente</option>
                <option value="approved">Aprobado</option>
                <option value="rejected">Rechazado</option>
                <option value="cancelled">Cancelado</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Empleado</label>
              <select
                v-model="filters.employee_id"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                  {{ employee.first_name }} {{ employee.last_name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
              <select
                v-model="filters.department_id"
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
              <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
              <select
                v-model="filters.type"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option v-for="(label, value) in leaveTypes" :key="value" :value="value">
                  {{ label }}
                </option>
              </select>
            </div>
            <div class="flex items-end">
              <button
                @click="clearFilters"
                class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm font-medium"
              >
                Limpiar
              </button>
            </div>
          </div>
        </div>

        <!-- Tabla de solicitudes -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <!-- Desktop -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Empleado
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tipo
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Fechas
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Días
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
                <tr v-for="leave in leaveRequests.data" :key="leave.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">
                      {{ leave.employee.first_name }} {{ leave.employee.last_name }}
                    </div>
                    <div class="text-sm text-gray-500">
                      {{ leave.employee.employee_number }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                      {{ leaveTypes[leave.type] || leave.type }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                    {{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                    {{ leave.days_requested }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span :class="[
                      'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                      leave.status === 'approved' ? 'bg-green-100 text-green-800' :
                      leave.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                      leave.status === 'rejected' ? 'bg-red-100 text-red-800' :
                      'bg-gray-100 text-gray-800'
                    ]">
                      {{ getStatusLabel(leave.status) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                      <Link
                        :href="route('hrm.leaves.show', leave)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Ver
                      </Link>
                      <button
                        v-if="leave.status === 'pending'"
                        @click="approveLeave(leave)"
                        class="text-green-600 hover:text-green-900"
                      >
                        Aprobar
                      </button>
                      <button
                        v-if="leave.status === 'pending'"
                        @click="rejectLeave(leave)"
                        class="text-red-600 hover:text-red-900"
                      >
                        Rechazar
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
              <div v-for="leave in leaveRequests.data" :key="leave.id" 
                   class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-3">
                  <div>
                    <h3 class="text-base font-medium text-gray-900">
                      {{ leave.employee.first_name }} {{ leave.employee.last_name }}
                    </h3>
                    <p class="text-sm text-gray-500">{{ leave.employee.employee_number }}</p>
                  </div>
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    leave.status === 'approved' ? 'bg-green-100 text-green-800' :
                    leave.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                    leave.status === 'rejected' ? 'bg-red-100 text-red-800' :
                    'bg-gray-100 text-gray-800'
                  ]">
                    {{ getStatusLabel(leave.status) }}
                  </span>
                </div>

                <!-- Info -->
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Tipo:</span>
                    <span class="text-gray-900 font-medium">{{ leaveTypes[leave.type] || leave.type }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Fechas:</span>
                    <span class="text-gray-900">{{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Días:</span>
                    <span class="text-gray-900 font-medium">{{ leave.days_requested }}</span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 pt-3 border-t border-gray-200 flex justify-end space-x-3">
                  <Link
                    :href="route('hrm.leaves.show', leave)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                  >
                    Ver detalles
                  </Link>
                  <button
                    v-if="leave.status === 'pending'"
                    @click="approveLeave(leave)"
                    class="text-sm font-medium text-green-600 hover:text-green-500"
                  >
                    Aprobar
                  </button>
                </div>
              </div>

              <!-- Empty state -->
              <div v-if="!leaveRequests.data.length" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay solicitudes de vacaciones</h3>
                <p class="mt-1 text-sm text-gray-500">Comienza creando una nueva solicitud.</p>
              </div>
            </div>
          </div>

          <!-- Paginación -->
          <div v-if="leaveRequests.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Mostrando {{ leaveRequests.from }} a {{ leaveRequests.to }} de {{ leaveRequests.total }} solicitudes
              </div>
              <div class="flex space-x-2">
                <Link
                  v-for="link in leaveRequests.links"
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
  leaveRequests: Object,
  statistics: Object,
  employees: Array,
  departments: Array,
  leaveTypes: Object,
  filters: Object,
});

const filters = ref({
  status: props.filters.status || '',
  employee_id: props.filters.employee_id || '',
  department_id: props.filters.department_id || '',
  type: props.filters.type || '',
});

const applyFilter = () => {
  router.get(route('hrm.leaves.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  filters.value = {
    status: '',
    employee_id: '',
    department_id: '',
    type: '',
  };
  applyFilter();
};

const approveLeave = (leave) => {
  if (confirm('¿Aprobar esta solicitud de vacaciones?')) {
    router.post(route('hrm.leaves.approve', leave), {}, {
      preserveScroll: true,
    });
  }
};

const rejectLeave = (leave) => {
  const reason = prompt('Motivo del rechazo:');
  if (reason && reason.trim()) {
    router.post(route('hrm.leaves.reject', leave), {
      notes: reason
    }, {
      preserveScroll: true,
    });
  }
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-CL');
};

const getStatusLabel = (status) => {
  const labels = {
    pending: 'Pendiente',
    approved: 'Aprobado',
    rejected: 'Rechazado',
    cancelled: 'Cancelado',
  };
  return labels[status] || status;
};
</script>