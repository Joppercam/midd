<template>
    <AuthenticatedLayout>
        <Head title="Recepción de Mercancía" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900">Recepción de Mercancía</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Orden de Compra: {{ order.order_number }}
                            </p>
                            <p class="text-sm text-gray-500">
                                Proveedor: {{ order.supplier.name }}
                            </p>
                        </div>
                        <Link :href="route('purchase-orders.show', order.id)" 
                              class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <form @submit.prevent="submit">
                    <!-- Información General -->
                    <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información General</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="received_by" class="block text-sm font-medium text-gray-700">
                                    Recibido por <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="received_by"
                                    v-model="form.received_by"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    required
                                />
                                <p v-if="form.errors.received_by" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.received_by }}
                                </p>
                            </div>

                            <div>
                                <label for="reference_document" class="block text-sm font-medium text-gray-700">
                                    Documento de referencia (Guía, Factura, etc.)
                                </label>
                                <input
                                    type="text"
                                    id="reference_document"
                                    v-model="form.reference_document"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="Ej: Guía de despacho #12345"
                                />
                                <p v-if="form.errors.reference_document" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.reference_document }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="notes" class="block text-sm font-medium text-gray-700">
                                Notas generales
                            </label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Observaciones sobre la recepción..."
                            />
                            <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                                {{ form.errors.notes }}
                            </p>
                        </div>
                    </div>

                    <!-- Items a Recibir -->
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Items a Recibir</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Producto
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Ordenado
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Recibido Anterior
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Pendiente
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cantidad a Recibir
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Condición
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Notas
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(item, index) in pendingItems" :key="item.id"
                                        :class="{ 'bg-gray-50': index % 2 === 1 }">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ item.product.name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ item.product.sku }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                            {{ item.received_quantity }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium text-indigo-600">
                                            {{ item.pending_quantity }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <input
                                                type="number"
                                                v-model.number="form.items[index].quantity_received"
                                                :max="item.pending_quantity"
                                                min="0"
                                                step="1"
                                                class="w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-center"
                                                @input="validateQuantity(index, item.pending_quantity)"
                                            />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <select
                                                v-model="form.items[index].condition"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                :disabled="!form.items[index].quantity_received || form.items[index].quantity_received === 0"
                                            >
                                                <option value="good">Bueno</option>
                                                <option value="damaged">Dañado</option>
                                                <option value="rejected">Rechazado</option>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input
                                                type="text"
                                                v-model="form.items[index].notes"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="Observaciones..."
                                                :disabled="!form.items[index].quantity_received || form.items[index].quantity_received === 0"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Resumen -->
                        <div class="mt-6 border-t pt-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="text-center">
                                    <p class="text-sm text-gray-500">Total a Recibir</p>
                                    <p class="text-xl font-semibold text-gray-900">{{ totalToReceive }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-500">En Buenas Condiciones</p>
                                    <p class="text-xl font-semibold text-green-600">{{ totalGood }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-500">Dañados</p>
                                    <p class="text-xl font-semibold text-yellow-600">{{ totalDamaged }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-500">Rechazados</p>
                                    <p class="text-xl font-semibold text-red-600">{{ totalRejected }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="mt-6 flex justify-end space-x-3">
                            <Link :href="route('purchase-orders.show', order.id)" 
                                  class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancelar
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing || totalToReceive === 0"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ form.processing ? 'Procesando...' : 'Registrar Recepción' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    order: Object,
    pendingItems: Array
});

const form = useForm({
    received_by: '',
    reference_document: '',
    notes: '',
    items: []
});

// Inicializar items del formulario
onMounted(() => {
    form.items = props.pendingItems.map(item => ({
        purchase_order_item_id: item.id,
        quantity_received: 0,
        condition: 'good',
        notes: ''
    }));
});

// Computed properties para los totales
const totalToReceive = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.quantity_received || 0), 0);
});

const totalGood = computed(() => {
    return form.items
        .filter(item => item.condition === 'good')
        .reduce((sum, item) => sum + (item.quantity_received || 0), 0);
});

const totalDamaged = computed(() => {
    return form.items
        .filter(item => item.condition === 'damaged')
        .reduce((sum, item) => sum + (item.quantity_received || 0), 0);
});

const totalRejected = computed(() => {
    return form.items
        .filter(item => item.condition === 'rejected')
        .reduce((sum, item) => sum + (item.quantity_received || 0), 0);
});

// Validar que la cantidad no exceda lo pendiente
const validateQuantity = (index, maxQuantity) => {
    if (form.items[index].quantity_received > maxQuantity) {
        form.items[index].quantity_received = maxQuantity;
    }
    if (form.items[index].quantity_received < 0) {
        form.items[index].quantity_received = 0;
    }
};

// Enviar formulario
const submit = () => {
    // Filtrar solo items con cantidad > 0
    const itemsToSubmit = form.items.filter(item => item.quantity_received > 0);
    
    if (itemsToSubmit.length === 0) {
        alert('Debe recibir al menos un item');
        return;
    }

    form.transform(data => ({
        ...data,
        items: itemsToSubmit
    })).post(route('purchase-orders.receipts.store', props.order.id), {
        preserveScroll: true,
        onError: () => {
            alert('Error al registrar la recepción. Por favor revise los datos.');
        }
    });
};
</script>