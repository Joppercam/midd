<template>
    <AuthenticatedLayout>
        <Head title="Detalles de Recepción" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900">
                                Recepción {{ receipt.receipt_number }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Orden de Compra: {{ order.order_number }}
                            </p>
                            <p class="text-sm text-gray-500">
                                Fecha: {{ formatDate(receipt.received_at) }}
                            </p>
                        </div>
                        <div class="flex space-x-3">
                            <a :href="route('purchase-orders.receipts.pdf', [order.id, receipt.id])"
                               target="_blank"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Descargar PDF
                            </a>
                            <Link :href="route('purchase-orders.show', order.id)" 
                                  class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Volver a la Orden
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Información General -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Información General</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Recibido por</p>
                            <p class="text-sm font-medium text-gray-900">{{ receipt.received_by }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Registrado por</p>
                            <p class="text-sm font-medium text-gray-900">{{ receipt.user.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Documento de referencia</p>
                            <p class="text-sm font-medium text-gray-900">{{ receipt.reference_document || 'N/A' }}</p>
                        </div>
                    </div>

                    <div v-if="receipt.notes" class="mt-4">
                        <p class="text-sm text-gray-500">Notas</p>
                        <p class="text-sm text-gray-900">{{ receipt.notes }}</p>
                    </div>
                </div>

                <!-- Items Recibidos -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Items Recibidos</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Producto
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cantidad Recibida
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
                                <tr v-for="item in receipt.items" :key="item.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ item.purchase_order_item.product.name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ item.purchase_order_item.product.sku }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                        {{ item.quantity_received }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            item.condition === 'good' ? 'bg-green-100 text-green-800' :
                                            item.condition === 'damaged' ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-red-100 text-red-800'
                                        ]">
                                            {{ getConditionLabel(item.condition) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ item.notes || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Resumen -->
                    <div class="mt-6 border-t pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Total Recibido</p>
                                <p class="text-xl font-semibold text-gray-900">{{ totalReceived }}</p>
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
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFormatters } from '@/composables/useFormatters';

const props = defineProps({
    order: Object,
    receipt: Object
});

const { formatDate } = useFormatters();

// Computed properties para los totales
const totalReceived = computed(() => {
    return props.receipt.items.reduce((sum, item) => sum + item.quantity_received, 0);
});

const totalGood = computed(() => {
    return props.receipt.items
        .filter(item => item.condition === 'good')
        .reduce((sum, item) => sum + item.quantity_received, 0);
});

const totalDamaged = computed(() => {
    return props.receipt.items
        .filter(item => item.condition === 'damaged')
        .reduce((sum, item) => sum + item.quantity_received, 0);
});

const totalRejected = computed(() => {
    return props.receipt.items
        .filter(item => item.condition === 'rejected')
        .reduce((sum, item) => sum + item.quantity_received, 0);
});

// Helper para etiquetas de condición
const getConditionLabel = (condition) => {
    const labels = {
        'good': 'Bueno',
        'damaged': 'Dañado',
        'rejected': 'Rechazado'
    };
    return labels[condition] || condition;
};
</script>