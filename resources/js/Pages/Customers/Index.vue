<template>
  <Head title="Clientes" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Gestión de Clientes
        </h2>
        <Link
          :href="route('customers.create')"
          class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Cliente
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Total Clientes</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900">{{ stats.total_customers }}</div>
          </div>
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Clientes Activos</div>
            <div class="mt-1 text-3xl font-semibold text-green-600">{{ stats.active_customers }}</div>
          </div>
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Nuevos este mes</div>
            <div class="mt-1 text-3xl font-semibold text-blue-600">{{ stats.new_this_month }}</div>
          </div>
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Ingresos Totales</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
              ${{ formatCurrency(stats.total_revenue) }}
            </div>
          </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                <input
                  v-model="filters.search"
                  type="text"
                  placeholder="Nombre, RUT, email..."
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  @input="debounceSearch"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select
                  v-model="filters.type"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  @change="applyFilters"
                >
                  <option value="">Todos</option>
                  <option value="person">Persona Natural</option>
                  <option value="company">Empresa</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select
                  v-model="filters.is_active"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  @change="applyFilters"
                >
                  <option value="">Todos</option>
                  <option value="1">Activos</option>
                  <option value="0">Inactivos</option>
                </select>
              </div>
              <div class="flex items-end">
                <button
                  @click="clearFilters"
                  class="w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                >
                  Limpiar Filtros
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabla de clientes -->
        <div v-if="customers.data && customers.data.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <!-- Vista Desktop -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left">
                    <button
                      @click="sort('rut')"
                      class="text-xs font-medium text-gray-500 uppercase tracking-wider hover:text-gray-700"
                    >
                      RUT
                      <span v-if="filters.sort === 'rut'">
                        {{ filters.direction === 'asc' ? '↑' : '↓' }}
                      </span>
                    </button>
                  </th>
                  <th class="px-6 py-3 text-left">
                    <button
                      @click="sort('name')"
                      class="text-xs font-medium text-gray-500 uppercase tracking-wider hover:text-gray-700"
                    >
                      Nombre
                      <span v-if="filters.sort === 'name'">
                        {{ filters.direction === 'asc' ? '↑' : '↓' }}
                      </span>
                    </button>
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Contacto
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Documentos
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Total Compras
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
                <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ customer.formatted_rut }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ customer.name }}</div>
                      <div class="text-xs text-gray-500">
                        {{ customer.type === 'company' ? 'Empresa' : 'Persona Natural' }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div v-if="customer.email" class="flex items-center">
                      <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                      {{ customer.email }}
                    </div>
                    <div v-if="customer.phone" class="flex items-center mt-1">
                      <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                      </svg>
                      {{ customer.phone }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                      {{ customer.tax_documents_count || 0 }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <div class="font-medium text-gray-900">
                      ${{ formatCurrency(customer.tax_documents_sum_total || 0) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span
                      :class="[
                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                        customer.is_active
                          ? 'bg-green-100 text-green-800'
                          : 'bg-gray-100 text-gray-800'
                      ]"
                    >
                      {{ customer.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                      <Link
                        :href="route('customers.show', customer)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Ver
                      </Link>
                      <Link
                        :href="route('customers.edit', customer)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Editar
                      </Link>
                      <button
                        @click="deleteCustomer(customer)"
                        class="text-red-600 hover:text-red-900"
                      >
                        Eliminar
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Vista Móvil: Cards -->
          <div class="sm:hidden">
            <div class="space-y-3 p-4">
              <div v-for="customer in customers.data" :key="customer.id" 
                   class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                
                <!-- Header del Card -->
                <div class="flex justify-between items-start mb-3">
                  <div class="flex-1">
                    <h3 class="text-base font-medium text-gray-900">{{ customer.name }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ customer.formatted_rut }}</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-2"
                          :class="customer.type === 'company' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'">
                      {{ customer.type === 'company' ? 'Empresa' : 'Persona Natural' }}
                    </span>
                  </div>
                  
                  <!-- Badge de estado -->
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    customer.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                  ]">
                    {{ customer.is_active ? 'Activo' : 'Inactivo' }}
                  </span>
                </div>

                <!-- Información de contacto -->
                <div class="space-y-2 text-sm mb-3">
                  <div v-if="customer.email" class="flex items-center text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    {{ customer.email }}
                  </div>
                  <div v-if="customer.phone" class="flex items-center text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    {{ customer.phone }}
                  </div>
                </div>

                <!-- Métricas -->
                <div class="grid grid-cols-2 gap-4 py-3 border-y border-gray-100">
                  <div>
                    <p class="text-xs text-gray-500">Documentos</p>
                    <p class="text-sm font-semibold text-gray-900">{{ customer.tax_documents_count || 0 }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Total Compras</p>
                    <p class="text-sm font-semibold text-gray-900">${{ formatCurrency(customer.tax_documents_sum_total || 0) }}</p>
                  </div>
                </div>

                <!-- Acciones -->
                <div class="mt-3 flex justify-end space-x-3">
                  <Link :href="route('customers.show', customer)"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    Ver detalles
                  </Link>
                  <Link :href="route('customers.edit', customer)"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    Editar
                  </Link>
                </div>
              </div>

            </div>
          </div>

          <!-- Paginación -->
          <div v-if="customers.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between mb-4">
              <div class="text-sm text-gray-700">
                Mostrando {{ customers.from }} a {{ customers.to }} de {{ customers.total }} resultados
              </div>
            </div>
            <Pagination :links="customers.links" />
          </div>
        </div>

        <!-- Sin resultados -->
        <div v-else class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay clientes</h3>
            <p class="mt-1 text-sm text-gray-500">Comienza creando un nuevo cliente.</p>
            <div class="mt-6">
              <Link
                :href="route('customers.create')"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Cliente
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const props = defineProps({
  customers: Object,
  filters: Object,
  stats: Object,
});

const filters = reactive({
  search: props.filters.search || '',
  type: props.filters.type || '',
  is_active: props.filters.is_active || '',
  sort: props.filters.sort || 'name',
  direction: props.filters.direction || 'asc',
});

let searchTimeout = null;

const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 300);
};

const applyFilters = () => {
  router.get(route('customers.index'), filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  filters.search = '';
  filters.type = '';
  filters.is_active = '';
  filters.sort = 'name';
  filters.direction = 'asc';
  applyFilters();
};

const sort = (field) => {
  if (filters.sort === field) {
    filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
  } else {
    filters.sort = field;
    filters.direction = 'asc';
  }
  applyFilters();
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value || 0);
};

const deleteCustomer = (customer) => {
  if (confirm(`¿Está seguro de eliminar el cliente "${customer.name}"?`)) {
    router.delete(route('customers.destroy', customer), {
      preserveScroll: true,
    });
  }
};
</script>