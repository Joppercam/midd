<template>
  <Head :title="`Editar ${invoice.number}`" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Editar {{ invoice.formatted_number }}
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <!-- Información básica -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Tipo de Documento</label>
                  <input
                    :value="documentTypes[invoice.type]"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm sm:text-sm"
                    disabled
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Fecha de Emisión</label>
                  <input
                    v-model="form.issue_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required
                  />
                  <div v-if="form.errors.issue_date" class="mt-2 text-sm text-red-600">
                    {{ form.errors.issue_date }}
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Fecha de Vencimiento</label>
                  <input
                    v-model="form.due_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required
                  />
                  <div v-if="form.errors.due_date" class="mt-2 text-sm text-red-600">
                    {{ form.errors.due_date }}
                  </div>
                </div>
              </div>

              <!-- Cliente -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Cliente</label>
                <select
                  v-model="form.customer_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  required
                >
                  <option value="">Seleccionar cliente...</option>
                  <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                    {{ customer.name }} ({{ customer.rut }})
                  </option>
                </select>
                <div v-if="form.errors.customer_id" class="mt-2 text-sm text-red-600">
                  {{ form.errors.customer_id }}
                </div>
              </div>

              <!-- Items -->
              <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-medium text-gray-900">Detalle de Items</h3>
                  <button
                    type="button"
                    @click="addItem"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Agregar Item
                  </button>
                </div>

                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Producto
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Descripción
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Cantidad
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Precio Unit.
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Total
                        </th>
                        <th class="relative px-6 py-3">
                          <span class="sr-only">Acciones</span>
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr v-for="(item, index) in form.items" :key="index">
                        <td class="px-6 py-4 whitespace-nowrap">
                          <select
                            v-model="item.product_id"
                            @change="updateItem(index)"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required
                          >
                            <option value="">Seleccionar...</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                              {{ product.name }}
                            </option>
                          </select>
                        </td>
                        <td class="px-6 py-4">
                          <input
                            v-model="item.description"
                            type="text"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                          />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input
                            v-model.number="item.quantity"
                            type="number"
                            min="0.01"
                            step="0.01"
                            @input="calculateItemTotal(index)"
                            class="block w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required
                          />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input
                            v-model.number="item.unit_price"
                            type="number"
                            min="0"
                            step="0.01"
                            @input="calculateItemTotal(index)"
                            class="block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required
                          />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                          ${{ formatCurrency(item.total) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                          <button
                            type="button"
                            @click="removeItem(index)"
                            class="text-red-600 hover:text-red-900"
                          >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div v-if="form.errors.items" class="mt-2 text-sm text-red-600">
                  {{ form.errors.items }}
                </div>
              </div>

              <!-- Totales -->
              <div class="border-t border-gray-200 pt-6">
                <div class="flex justify-end">
                  <div class="w-full max-w-xs">
                    <div class="flex justify-between mb-2">
                      <span class="text-gray-600">Subtotal:</span>
                      <span class="font-medium">${{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between mb-2">
                      <span class="text-gray-600">IVA (19%):</span>
                      <span class="font-medium">${{ formatCurrency(tax) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold">
                      <span>Total:</span>
                      <span>${{ formatCurrency(total) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Acciones -->
            <div class="bg-gray-50 px-6 py-3 flex items-center justify-between">
              <button
                type="button"
                @click="confirmDelete"
                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring focus:ring-red-300 disabled:opacity-25 transition"
              >
                Eliminar
              </button>
              <div class="flex items-center space-x-3">
                <Link
                  :href="route('invoices.show', invoice)"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Cancelar
                </Link>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                  <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Guardar Cambios
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  invoice: Object,
  customers: Array,
  products: Array,
  documentTypes: Object,
});

const form = useForm({
  customer_id: props.invoice.customer_id,
  issue_date: props.invoice.issue_date,
  due_date: props.invoice.due_date,
  items: props.invoice.items.map(item => ({
    product_id: item.product_id,
    description: item.description,
    quantity: parseFloat(item.quantity),
    unit_price: parseFloat(item.unit_price),
    total: parseFloat(item.total),
  })),
});

const subtotal = computed(() => {
  return form.items.reduce((sum, item) => sum + (item.total || 0), 0);
});

const tax = computed(() => {
  return subtotal.value * 0.19;
});

const total = computed(() => {
  return subtotal.value + tax.value;
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value || 0);
};

const addItem = () => {
  form.items.push({
    product_id: '',
    description: '',
    quantity: 1,
    unit_price: 0,
    total: 0,
  });
};

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
  }
};

const updateItem = (index) => {
  const item = form.items[index];
  if (item.product_id) {
    const product = props.products.find(p => p.id === item.product_id);
    if (product) {
      item.unit_price = product.price;
      item.description = product.name;
      calculateItemTotal(index);
    }
  }
};

const calculateItemTotal = (index) => {
  const item = form.items[index];
  item.total = (item.quantity || 0) * (item.unit_price || 0);
};

const submit = () => {
  form.put(route('invoices.update', props.invoice));
};

const confirmDelete = () => {
  if (confirm('¿Está seguro de eliminar este documento? Esta acción no se puede deshacer.')) {
    router.delete(route('invoices.destroy', props.invoice));
  }
};
</script>