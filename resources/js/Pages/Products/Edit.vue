<template>
  <Head :title="`Editar ${product.name}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center">
        <Link
          :href="route('products.show', product)"
          class="text-gray-400 hover:text-gray-600 mr-4"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </Link>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Editar Producto
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Información básica -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información Básica</h3>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="code" value="Código *" />
                  <TextInput
                    id="code"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.code"
                    required
                    autofocus
                  />
                  <InputError class="mt-2" :message="form.errors.code" />
                </div>

                <div>
                  <InputLabel for="category_id" value="Categoría *" />
                  <select
                    id="category_id"
                    v-model="form.category_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required
                  >
                    <option value="">Seleccionar categoría</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                      {{ category.name }}
                    </option>
                  </select>
                  <InputError class="mt-2" :message="form.errors.category_id" />
                </div>

                <div class="md:col-span-2">
                  <InputLabel for="name" value="Nombre *" />
                  <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                  />
                  <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="md:col-span-2">
                  <InputLabel for="description" value="Descripción" />
                  <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  ></textarea>
                  <InputError class="mt-2" :message="form.errors.description" />
                </div>

                <div class="md:col-span-2">
                  <label class="flex items-center">
                    <Checkbox v-model:checked="form.is_service" />
                    <span class="ml-2 text-sm text-gray-600">
                      Es un servicio (no requiere gestión de inventario)
                    </span>
                  </label>
                  <p v-if="product.inventory_movements_count > 0" class="mt-1 text-sm text-yellow-600">
                    Este producto tiene movimientos de inventario registrados
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Precios y costos -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Precios y Costos</h3>
              
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <InputLabel for="cost" value="Costo *" />
                  <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 sm:text-sm">$</span>
                    </div>
                    <input
                      id="cost"
                      type="number"
                      v-model="form.cost"
                      class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      required
                      min="0"
                      step="0.01"
                    />
                  </div>
                  <InputError class="mt-2" :message="form.errors.cost" />
                </div>

                <div>
                  <InputLabel for="price" value="Precio de venta *" />
                  <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 sm:text-sm">$</span>
                    </div>
                    <input
                      id="price"
                      type="number"
                      v-model="form.price"
                      class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      required
                      min="0"
                      step="0.01"
                    />
                  </div>
                  <InputError class="mt-2" :message="form.errors.price" />
                  <p v-if="form.price && form.cost" class="mt-1 text-sm text-gray-500">
                    Margen: {{ calculateMargin() }}%
                  </p>
                </div>

                <div>
                  <InputLabel for="tax_rate" value="Tasa de impuesto (%) *" />
                  <div class="mt-1 relative rounded-md shadow-sm">
                    <input
                      id="tax_rate"
                      type="number"
                      v-model="form.tax_rate"
                      class="pr-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      required
                      min="0"
                      max="100"
                      step="0.01"
                    />
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 sm:text-sm">%</span>
                    </div>
                  </div>
                  <InputError class="mt-2" :message="form.errors.tax_rate" />
                  <p v-if="form.price && form.tax_rate" class="mt-1 text-sm text-gray-500">
                    Precio con IVA: ${{ formatCurrency(form.price * (1 + form.tax_rate / 100)) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Inventario -->
          <div v-if="!form.is_service" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Gestión de Inventario</h3>
              
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <InputLabel for="unit" value="Unidad de medida *" />
                  <select
                    id="unit"
                    v-model="form.unit"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required
                  >
                    <option value="UN">Unidad</option>
                    <option value="KG">Kilogramo</option>
                    <option value="LT">Litro</option>
                    <option value="MT">Metro</option>
                    <option value="M2">Metro cuadrado</option>
                    <option value="M3">Metro cúbico</option>
                    <option value="CJ">Caja</option>
                    <option value="PQ">Paquete</option>
                  </select>
                  <InputError class="mt-2" :message="form.errors.unit" />
                </div>

                <div>
                  <InputLabel value="Stock actual" />
                  <div class="mt-1 text-lg font-medium text-gray-900">
                    {{ product.stock_quantity }} {{ product.unit }}
                  </div>
                  <p class="text-sm text-gray-500">
                    Para modificar el stock, usa la gestión de inventario
                  </p>
                </div>

                <div>
                  <InputLabel for="minimum_stock" value="Stock mínimo *" />
                  <input
                    id="minimum_stock"
                    type="number"
                    v-model="form.minimum_stock"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required
                    min="0"
                    step="0.01"
                  />
                  <InputError class="mt-2" :message="form.errors.minimum_stock" />
                  <p class="mt-1 text-sm text-gray-500">
                    Se alertará cuando el stock baje de este nivel
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Acciones -->
          <div class="flex items-center justify-end space-x-4">
            <Link
              :href="route('products.show', product)"
              class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
            >
              Cancelar
            </Link>
            <PrimaryButton :disabled="form.processing">
              Guardar Cambios
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  product: Object,
  categories: Array,
});

const form = useForm({
  code: props.product.code,
  name: props.product.name,
  description: props.product.description || '',
  category_id: props.product.category_id,
  is_service: props.product.is_service,
  price: props.product.price,
  cost: props.product.cost,
  tax_rate: props.product.tax_rate,
  minimum_stock: props.product.minimum_stock,
  unit: props.product.unit,
});

const calculateMargin = () => {
  if (!form.price || !form.cost || form.cost == 0) return 0;
  const margin = ((form.price - form.cost) / form.cost) * 100;
  return margin.toFixed(2);
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value || 0);
};

const submit = () => {
  form.put(route('products.update', props.product));
};
</script>