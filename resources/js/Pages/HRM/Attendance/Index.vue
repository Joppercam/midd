<template>
  <Head title="Control de Asistencia" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Control de Asistencia
        </h2>
        <div class="flex space-x-2">
          <button
            @click="markAttendance"
            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Marcar Asistencia
          </button>
          <button
            @click="exportAttendance"
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
              <label class="block text-sm font-medium text-gray-700 mb-1">Empleado</label>
              <select
                v-model="filters.employee"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                  {{ emp.first_name }} {{ emp.last_name }}
                </option>
              </select>
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
          </div>
        </div>

        <!-- Resúmenes de hoy -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-green-100 rounded-md">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Presentes Hoy</p>
                <p class="text-2xl font-semibold text-gray-900">{{ todayStats.present || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-red-100 rounded-md">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Ausentes Hoy</p>
                <p class="text-2xl font-semibold text-gray-900">{{ todayStats.absent || 0 }}</p>
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
                <p class="text-sm font-medium text-gray-500">Tardanzas</p>
                <p class="text-2xl font-semibold text-gray-900">{{ todayStats.late || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-blue-100 rounded-md">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">% Asistencia</p>
                <p class="text-2xl font-semibold text-gray-900">{{ todayStats.attendance_rate || 0 }}%</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabla de asistencia -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <!-- Desktop -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Empleado
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Fecha
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Entrada
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Salida
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Horas Trabajadas
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Estado
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Observaciones
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="attendance in attendances.data" :key="attendance.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div class="text-sm font-medium text-gray-900">
                        {{ attendance.employee.first_name }} {{ attendance.employee.last_name }}
                      </div>
                      <div class="text-sm text-gray-500">
                        {{ attendance.employee.department?.name }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                    {{ formatDate(attendance.date) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                    {{ attendance.check_in ? formatTime(attendance.check_in) : '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                    {{ attendance.check_out ? formatTime(attendance.check_out) : '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                    {{ attendance.hours_worked || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span :class="[
                      'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                      attendance.status === 'present' ? 'bg-green-100 text-green-800' :
                      attendance.status === 'absent' ? 'bg-red-100 text-red-800' :
                      attendance.status === 'late' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-gray-100 text-gray-800'
                    ]">
                      {{ getStatusLabel(attendance.status) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                    {{ attendance.notes || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                      <button
                        @click="editAttendance(attendance)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Editar
                      </button>
                      <button
                        v-if="!attendance.check_out"
                        @click="checkOut(attendance)"
                        class="text-green-600 hover:text-green-900"
                      >
                        Marcar Salida
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
              <div v-for="attendance in attendances.data" :key="attendance.id" 
                   class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-3">
                  <div>
                    <h3 class="text-base font-medium text-gray-900">
                      {{ attendance.employee.first_name }} {{ attendance.employee.last_name }}
                    </h3>
                    <p class="text-sm text-gray-500">{{ attendance.employee.department?.name }}</p>
                    <p class="text-sm text-gray-500">{{ formatDate(attendance.date) }}</p>
                  </div>
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    attendance.status === 'present' ? 'bg-green-100 text-green-800' :
                    attendance.status === 'absent' ? 'bg-red-100 text-red-800' :
                    attendance.status === 'late' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-gray-100 text-gray-800'
                  ]">
                    {{ getStatusLabel(attendance.status) }}
                  </span>
                </div>

                <!-- Horarios -->
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Entrada:</span>
                    <span class="text-gray-900 font-medium">
                      {{ attendance.check_in ? formatTime(attendance.check_in) : '-' }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Salida:</span>
                    <span class="text-gray-900 font-medium">
                      {{ attendance.check_out ? formatTime(attendance.check_out) : '-' }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Horas:</span>
                    <span class="text-gray-900 font-medium">{{ attendance.hours_worked || '-' }}</span>
                  </div>
                  <div v-if="attendance.notes" class="pt-2 border-t">
                    <span class="text-gray-500">Observaciones:</span>
                    <p class="text-gray-900 mt-1">{{ attendance.notes }}</p>
                  </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 pt-3 border-t border-gray-200 flex justify-end space-x-3">
                  <button
                    @click="editAttendance(attendance)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                  >
                    Editar
                  </button>
                  <button
                    v-if="!attendance.check_out"
                    @click="checkOut(attendance)"
                    class="text-sm font-medium text-green-600 hover:text-green-500"
                  >
                    Marcar Salida
                  </button>
                </div>
              </div>

              <!-- Empty state -->
              <div v-if="!attendances.data.length" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros de asistencia</h3>
                <p class="mt-1 text-sm text-gray-500">Los empleados pueden marcar su asistencia.</p>
              </div>
            </div>
          </div>

          <!-- Paginación -->
          <div v-if="attendances.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Mostrando {{ attendances.from }} a {{ attendances.to }} de {{ attendances.total }} registros
              </div>
              <div class="flex space-x-2">
                <Link
                  v-for="link in attendances.links"
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
  attendances: Object,
  employees: Array,
  departments: Array,
  todayStats: Object,
  filters: Object,
});

const filters = ref({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  employee: props.filters.employee || '',
  department: props.filters.department || '',
});

const applyFilter = () => {
  router.get(route('hrm.attendance.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const markAttendance = () => {
  router.post(route('hrm.attendance.mark'), {}, {
    preserveScroll: true,
    onSuccess: () => {
      alert('Asistencia marcada exitosamente');
    }
  });
};

const editAttendance = (attendance) => {
  // Aquí podrías abrir un modal o redirigir a una página de edición
  console.log('Editar asistencia:', attendance);
};

const checkOut = (attendance) => {
  if (confirm('¿Marcar salida para este empleado?')) {
    router.put(route('hrm.attendance.checkout', attendance), {}, {
      preserveScroll: true,
    });
  }
};

const exportAttendance = () => {
  window.location.href = route('hrm.attendance.export', filters.value);
};

const getStatusLabel = (status) => {
  const labels = {
    'present': 'Presente',
    'absent': 'Ausente',
    'late': 'Tarde',
    'early_departure': 'Salida Temprana',
    'overtime': 'Horas Extra'
  };
  return labels[status] || status;
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-CL');
};

const formatTime = (datetime) => {
  if (!datetime) return '-';
  return new Date(datetime).toLocaleTimeString('es-CL', { 
    hour: '2-digit', 
    minute: '2-digit' 
  });
};
</script>