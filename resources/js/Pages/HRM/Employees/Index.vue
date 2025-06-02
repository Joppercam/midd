<template>
  <Head title="Empleados" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Gestión de Empleados
        </h2>
        <Link
          :href="route('hrm.employees.create')"
          class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Empleado
        </Link>
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
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Empleados</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.total_employees || 0 }}</p>
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
                <p class="text-sm font-medium text-gray-500">Activos</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.active_employees || 0 }}</p>
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
                <p class="text-sm font-medium text-gray-500">De Vacaciones</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.on_vacation || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-indigo-100 rounded-md">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Nómina Mensual</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatCurrency(stats.monthly_payroll || 0) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
              <input
                type="text"
                v-model="filters.search"
                @input="search"
                placeholder="Nombre, RUT, email..."
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
              <label class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
              <select
                v-model="filters.position"
                @change="applyFilter"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Todos</option>
                <option v-for="pos in positions" :key="pos.id" :value="pos.id">
                  {{ pos.title }}
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
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
                <option value="terminated">Terminado</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Tabla de empleados -->
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
                    Cargo
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Departamento
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Fecha Ingreso
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Estado
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Salario
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="employee in employees.data" :key="employee.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div v-if="employee.photo_path" 
                             class="h-10 w-10 rounded-full bg-cover bg-center"
                             :style="`background-image: url(${employee.photo_path})`">
                        </div>
                        <div v-else class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                          <span class="text-indigo-600 font-medium">
                            {{ employee.first_name.charAt(0) }}{{ employee.last_name.charAt(0) }}
                          </span>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">
                          {{ employee.first_name }} {{ employee.last_name }}
                        </div>
                        <div class="text-sm text-gray-500">
                          {{ employee.rut }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">{{ employee.position?.title || '-' }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">{{ employee.department?.name || '-' }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="text-sm text-gray-900">{{ formatDate(employee.hire_date) }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span :class="[
                      'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                      employee.status === 'active' ? 'bg-green-100 text-green-800' :
                      employee.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
                      'bg-red-100 text-red-800'
                    ]">
                      {{ employee.status === 'active' ? 'Activo' : 
                         employee.status === 'inactive' ? 'Inactivo' : 'Terminado' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm font-medium text-gray-900">
                      ${{ formatCurrency(employee.current_salary || 0) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                      <Link
                        :href="route('hrm.employees.show', employee)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Ver
                      </Link>
                      <Link
                        :href="route('hrm.employees.edit', employee)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Editar
                      </Link>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile -->
          <div class="sm:hidden">
            <div class="space-y-3 p-4">
              <div v-for="employee in employees.data" :key="employee.id" 
                   class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-3">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-12 w-12">
                      <div v-if="employee.photo_path" 
                           class="h-12 w-12 rounded-full bg-cover bg-center"
                           :style="`background-image: url(${employee.photo_path})`">
                      </div>
                      <div v-else class="h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center">
                        <span class="text-indigo-600 font-medium text-lg">
                          {{ employee.first_name.charAt(0) }}{{ employee.last_name.charAt(0) }}
                        </span>
                      </div>
                    </div>
                    <div class="ml-3">
                      <h3 class="text-base font-medium text-gray-900">
                        {{ employee.first_name }} {{ employee.last_name }}
                      </h3>
                      <p class="text-sm text-gray-500">{{ employee.rut }}</p>
                    </div>
                  </div>
                  
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    employee.status === 'active' ? 'bg-green-100 text-green-800' :
                    employee.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
                    'bg-red-100 text-red-800'
                  ]">
                    {{ employee.status === 'active' ? 'Activo' : 
                       employee.status === 'inactive' ? 'Inactivo' : 'Terminado' }}
                  </span>
                </div>

                <!-- Info -->
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Cargo:</span>
                    <span class="text-gray-900 font-medium">{{ employee.position?.title || '-' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Departamento:</span>
                    <span class="text-gray-900">{{ employee.department?.name || '-' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Ingreso:</span>
                    <span class="text-gray-900">{{ formatDate(employee.hire_date) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">Salario:</span>
                    <span class="text-gray-900 font-medium">${{ formatCurrency(employee.current_salary || 0) }}</span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 pt-3 border-t border-gray-200 flex justify-end space-x-3">
                  <Link
                    :href="route('hrm.employees.show', employee)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                  >
                    Ver detalles
                  </Link>
                  <Link
                    :href="route('hrm.employees.edit', employee)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                  >
                    Editar
                  </Link>
                </div>
              </div>

              <!-- Empty state -->
              <div v-if="!employees.data.length" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay empleados</h3>
                <p class="mt-1 text-sm text-gray-500">Comienza agregando el primer empleado.</p>
              </div>
            </div>
          </div>

          <!-- Paginación -->
          <div v-if="employees.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Mostrando {{ employees.from }} a {{ employees.to }} de {{ employees.total }} empleados
              </div>
              <div class="flex space-x-2">
                <Link
                  v-for="link in employees.links"
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
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
  employees: Object,
  departments: Array,
  positions: Array,
  stats: Object,
  filters: Object,
});

const filters = ref({
  search: props.filters.search || '',
  department: props.filters.department || '',
  position: props.filters.position || '',
  status: props.filters.status || '',
});

const search = debounce(() => {
  router.get(route('hrm.employees.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
}, 300);

const applyFilter = () => {
  router.get(route('hrm.employees.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-CL').format(amount);
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-CL');
};
</script>