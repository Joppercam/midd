<template>
  <Head :title="product.name" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <Link
            :href="route('products.index')"
            class="text-gray-400 hover:text-gray-600 mr-4"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </Link>
          <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              {{ product.name }}
            </h2>
            <p class="text-sm text-gray-500">Código: {{ product.code }}</p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <Link
            :href="route('products.edit', product)"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
          >
            Editar
          </Link>
          <button
            @click="showStockModal = true"
            v-if="!product.is_service"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
          >
            Actualizar Stock
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Información general -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Detalles del producto -->
          <div class="md:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Producto</h3>
              
              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <dt class="text-sm font-medium text-gray-500">Categoría</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ product.category?.name || '-' }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                  <dd class="mt-1 text-sm">
                    <span v-if="product.is_service" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                      Servicio
                    </span>
                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      Producto
                    </span>
                  </dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="text-sm font-medium text-gray-500">Descripción</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ product.description || 'Sin descripción' }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Precios -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Precios</h3>
              
              <dl class="space-y-4">
                <div>
                  <dt class="text-sm font-medium text-gray-500">Costo</dt>
                  <dd class="mt-1 text-xl font-semibold text-gray-900">${{ formatCurrency(product.cost) }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Precio de venta</dt>
                  <dd class="mt-1 text-xl font-semibold text-gray-900">${{ formatCurrency(product.price) }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">IVA</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ product.tax_rate }}%</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Precio con IVA</dt>
                  <dd class="mt-1 text-xl font-semibold text-indigo-600">
                    ${{ formatCurrency(product.price * (1 + product.tax_rate / 100)) }}
                  </dd>
                </div>
                <div class="pt-4 border-t border-gray-200">
                  <dt class="text-sm font-medium text-gray-500">Margen</dt>
                  <dd class="mt-1 text-lg font-semibold" :class="getMarginClass()">
                    {{ calculateMargin() }}%
                  </dd>
                </div>
              </dl>
            </div>
          </div>
        </div>

        <!-- Inventario -->
        <div v-if="!product.is_service" class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Estado del inventario -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Estado del Inventario</h3>
              
              <dl class="space-y-4">
                <div>
                  <dt class="text-sm font-medium text-gray-500">Stock actual</dt>
                  <dd class="mt-1 text-2xl font-semibold" :class="getStockClass()">
                    {{ product.stock_quantity }} {{ product.unit }}
                  </dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Stock mínimo</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ product.minimum_stock }} {{ product.unit }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Valor en inventario</dt>
                  <dd class="mt-1 text-lg font-semibold text-gray-900">
                    ${{ formatCurrency(product.stock_quantity * product.cost) }}
                  </dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Estadísticas de movimientos -->
          <div class="md:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas de Movimientos</h3>
              
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                  <div class="text-sm font-medium text-gray-500">Compras</div>
                  <div class="mt-1 text-xl font-semibold text-green-600">
                    {{ movementStats.total_purchases || 0 }}
                  </div>
                </div>
                <div>
                  <div class="text-sm font-medium text-gray-500">Ventas</div>
                  <div class="mt-1 text-xl font-semibold text-red-600">
                    {{ movementStats.total_sales || 0 }}
                  </div>
                </div>
                <div>
                  <div class="text-sm font-medium text-gray-500">Ajustes</div>
                  <div class="mt-1 text-xl font-semibold text-gray-900">
                    {{ movementStats.total_adjustments || 0 }}
                  </div>
                </div>
                <div>
                  <div class="text-sm font-medium text-gray-500">Último mov.</div>
                  <div class="mt-1 text-sm text-gray-900">
                    {{ movementStats.last_movement ? formatDate(movementStats.last_movement.created_at) : 'Nunca' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Historial de movimientos -->
        <div v-if="!product.is_service && product.inventory_movements.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Últimos Movimientos de Inventario</h3>
            
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Fecha
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Tipo
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Cantidad
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Costo Unit.
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Notas
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="movement in product.inventory_movements" :key="movement.id">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ formatDate(movement.created_at) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span :class="getMovementTypeClass(movement.movement_type)">
                        {{ getMovementTypeLabel(movement.movement_type) }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="getMovementQuantityClass(movement.movement_type)">
                      {{ getMovementSign(movement.movement_type) }}{{ movement.quantity }} {{ product.unit }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                      ${{ formatCurrency(movement.unit_cost) }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                      {{ movement.notes || '-' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de actualización de stock -->
    <Modal :show="showStockModal" @close="closeStockModal">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Actualizar Stock</h3>
        
        <form @submit.prevent="submitStock" class="space-y-4">
          <div>
            <InputLabel for="movement_type" value="Tipo de movimiento *" />
            <select
              id="movement_type"
              v-model="stockForm.movement_type"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              required
            >
              <option value="">Seleccionar tipo</option>
              <option value="purchase">Compra</option>
              <option value="adjustment">Ajuste de inventario</option>
              <option value="return">Devolución</option>
            </select>
            <InputError class="mt-2" :message="stockForm.errors.movement_type" />
          </div>

          <div>
            <InputLabel for="quantity" value="Cantidad *" />
            <div class="mt-1 relative rounded-md shadow-sm">
              <input
                id="quantity"
                type="number"
                v-model="stockForm.quantity"
                class="pr-12 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
                min="0.01"
                step="0.01"
                :placeholder="stockForm.movement_type === 'adjustment' ? 'Nuevo stock total' : 'Cantidad a agregar'"
              />
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">{{ product.unit }}</span>
              </div>
            </div>
            <InputError class="mt-2" :message="stockForm.errors.quantity" />
          </div>

          <div v-if="stockForm.movement_type === 'purchase'">
            <InputLabel for="unit_cost" value="Costo unitario *" />
            <div class="mt-1 relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">$</span>
              </div>
              <input
                id="unit_cost"
                type="number"
                v-model="stockForm.unit_cost"
                class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
                min="0"
                step="0.01"
              />
            </div>
            <InputError class="mt-2" :message="stockForm.errors.unit_cost" />
          </div>

          <div>
            <InputLabel for="notes" value="Notas" />
            <textarea
              id="notes"
              v-model="stockForm.notes"
              rows="2"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              placeholder="Observaciones opcionales..."
            ></textarea>
            <InputError class="mt-2" :message="stockForm.errors.notes" />
          </div>

          <div class="flex items-center justify-end space-x-4 pt-4">
            <SecondaryButton @click="closeStockModal">
              Cancelar
            </SecondaryButton>
            <PrimaryButton :disabled="stockForm.processing">
              Actualizar Stock
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  product: Object,
  movementStats: Object,
});

const showStockModal = ref(false);

const stockForm = useForm({
  movement_type: '',
  quantity: '',
  unit_cost: props.product.cost,
  notes: '',
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value || 0);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('es-CL', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const calculateMargin = () => {
  if (!props.product.price || !props.product.cost || props.product.cost === 0) return 0;
  const margin = ((props.product.price - props.product.cost) / props.product.cost) * 100;
  return margin.toFixed(2);
};

const getMarginClass = () => {
  const margin = parseFloat(calculateMargin());
  if (margin < 20) return 'text-red-600';
  if (margin < 30) return 'text-yellow-600';
  return 'text-green-600';
};

const getStockClass = () => {
  if (props.product.stock_quantity <= 0) return 'text-red-600';
  if (props.product.stock_quantity <= props.product.minimum_stock) return 'text-yellow-600';
  return 'text-green-600';
};

const getMovementTypeLabel = (type) => {
  const labels = {
    purchase: 'Compra',
    sale: 'Venta',
    adjustment: 'Ajuste',
    return: 'Devolución',
    transfer: 'Transferencia',
  };
  return labels[type] || type;
};

const getMovementTypeClass = (type) => {
  const classes = {
    purchase: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800',
    sale: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800',
    adjustment: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800',
    return: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800',
    transfer: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800',
  };
  return classes[type] || '';
};

const getMovementSign = (type) => {
  return ['purchase', 'return'].includes(type) ? '+' : '-';
};

const getMovementQuantityClass = (type) => {
  return ['purchase', 'return'].includes(type) ? 'text-green-600' : 'text-red-600';
};

const closeStockModal = () => {
  showStockModal.value = false;
  stockForm.reset();
};

const submitStock = () => {
  stockForm.post(route('products.update-stock', props.product), {
    preserveScroll: true,
    onSuccess: () => closeStockModal(),
  });
};
</script>