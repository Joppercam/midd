<template>
  <Head title="Productos" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Gestión de Productos
        </h2>
        <Link
          :href="route('products.create')"
          class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Producto
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Total Productos</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900">{{ stats.total_products }}</div>
          </div>
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Servicios</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900">{{ stats.total_services }}</div>
          </div>
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Stock Bajo</div>
            <div class="mt-1 text-3xl font-semibold text-yellow-600">{{ stats.low_stock }}</div>
          </div>
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Sin Stock</div>
            <div class="mt-1 text-3xl font-semibold text-red-600">{{ stats.out_of_stock }}</div>
          </div>
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">Valor Inventario</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
              ${{ formatCurrency(stats.total_value) }}
            </div>
          </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                <input
                  v-model="filters.search"
                  type="text"
                  placeholder="Código, nombre..."
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  @input="debounceSearch"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                <select
                  v-model="filters.category_id"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  @change="applyFilters"
                >
                  <option value="">Todas</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado Stock</label>
                <select
                  v-model="filters.stock_status"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  @change="applyFilters"
                >
                  <option value="">Todos</option>
                  <option value="in_stock">En Stock</option>
                  <option value="low_stock">Stock Bajo</option>
                  <option value="out_of_stock">Sin Stock</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select
                  v-model="filters.is_service"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  @change="applyFilters"
                >
                  <option value="">Todos</option>
                  <option value="0">Productos</option>
                  <option value="1">Servicios</option>
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

        <!-- Tabla de productos -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left">
                    <button
                      @click="sort('code')"
                      class="text-xs font-medium text-gray-500 uppercase tracking-wider hover:text-gray-700"
                    >
                      Código
                      <span v-if="filters.sort === 'code'">
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
                    Categoría
                  </th>
                  <th class="px-6 py-3 text-right">
                    <button
                      @click="sort('stock_quantity')"
                      class="text-xs font-medium text-gray-500 uppercase tracking-wider hover:text-gray-700"
                    >
                      Stock
                      <span v-if="filters.sort === 'stock_quantity'">
                        {{ filters.direction === 'asc' ? '↑' : '↓' }}
                      </span>
                    </button>
                  </th>
                  <th class="px-6 py-3 text-right">
                    <button
                      @click="sort('price')"
                      class="text-xs font-medium text-gray-500 uppercase tracking-wider hover:text-gray-700"
                    >
                      Precio
                      <span v-if="filters.sort === 'price'">
                        {{ filters.direction === 'asc' ? '↑' : '↓' }}
                      </span>
                    </button>
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
                <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ product.code }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
                      <div v-if="product.is_service" class="text-xs text-indigo-600">Servicio</div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ product.category?.name || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <template v-if="!product.is_service">
                      <span :class="getStockClass(product)">
                        {{ product.stock_quantity }} {{ product.unit }}
                      </span>
                      <div v-if="product.stock_quantity <= product.minimum_stock" class="text-xs text-gray-500">
                        Mín: {{ product.minimum_stock }}
                      </div>
                    </template>
                    <span v-else class="text-gray-400">N/A</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <div class="font-medium">${{ formatCurrency(product.price) }}</div>
                    <div class="text-xs text-gray-500">+{{ product.tax_rate }}% IVA</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span
                      :class="[
                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                        getStatusClass(product)
                      ]"
                    >
                      {{ getStatusLabel(product) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                      <Link
                        :href="route('products.show', product)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Ver
                      </Link>
                      <Link
                        :href="route('products.edit', product)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Editar
                      </Link>
                      <button
                        @click="deleteProduct(product)"
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

          <!-- Paginación -->
          <div v-if="products.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Mostrando {{ products.from }} a {{ products.to }} de {{ products.total }} resultados
              </div>
              <div class="flex space-x-2">
                <Link
                  v-for="link in products.links"
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

          <!-- Sin resultados -->
          <div v-else class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay productos</h3>
            <p class="mt-1 text-sm text-gray-500">Comienza creando un nuevo producto.</p>
            <div class="mt-6">
              <Link
                :href="route('products.create')"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Producto
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
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const props = defineProps({
  products: Object,
  filters: Object,
  stats: Object,
  categories: Array,
});

const filters = reactive({
  search: props.filters.search || '',
  category_id: props.filters.category_id || '',
  stock_status: props.filters.stock_status || '',
  is_service: props.filters.is_service || '',
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
  router.get(route('products.index'), filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  filters.search = '';
  filters.category_id = '';
  filters.stock_status = '';
  filters.is_service = '';
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

const getStockClass = (product) => {
  if (product.stock_quantity <= 0) return 'text-red-600 font-medium';
  if (product.stock_quantity <= product.minimum_stock) return 'text-yellow-600 font-medium';
  return 'text-gray-900';
};

const getStatusClass = (product) => {
  if (product.is_service) return 'bg-indigo-100 text-indigo-800';
  if (product.stock_quantity <= 0) return 'bg-red-100 text-red-800';
  if (product.stock_quantity <= product.minimum_stock) return 'bg-yellow-100 text-yellow-800';
  return 'bg-green-100 text-green-800';
};

const getStatusLabel = (product) => {
  if (product.is_service) return 'Servicio';
  if (product.stock_quantity <= 0) return 'Sin Stock';
  if (product.stock_quantity <= product.minimum_stock) return 'Stock Bajo';
  return 'Disponible';
};

const deleteProduct = (product) => {
  if (confirm(`¿Está seguro de eliminar el producto "${product.name}"?`)) {
    router.delete(route('products.destroy', product), {
      preserveScroll: true,
    });
  }
};
</script>