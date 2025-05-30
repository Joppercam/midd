<template>
    <AuthenticatedLayout>
        <Head :title="`Orden de Compra ${order.order_number}`" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
                            Orden de Compra {{ order.order_number }}
                        </h1>
                        <div class="mt-2">
                            <Badge :variant="order.status_color" size="lg">
                                {{ order.status_label }}
                            </Badge>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="flex space-x-2">
                        <a
                            :href="route('purchase-orders.pdf', order)"
                            target="_blank"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            PDF
                        </a>
                        
                        <Link
                            v-if="order.status === 'draft'"
                            :href="route('purchase-orders.edit', order)"
                            class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Editar
                        </Link>

                        <button
                            v-if="order.can_be_sent"
                            @click="sendOrder"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Enviar
                        </button>

                        <button
                            v-if="order.can_be_confirmed"
                            @click="confirmOrder"
                            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Confirmar
                        </button>

                        <Link
                            v-if="order.can_be_received"
                            :href="route('purchase-orders.receipts.create', order)"
                            class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            Recibir
                        </Link>

                        <button
                            v-if="order.can_be_cancelled"
                            @click="showCancelModal = true"
                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancelar
                        </button>
                    </div>
                </div>

                <!-- Información general -->
                <Card class="mb-6">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Información General</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Proveedor</h3>
                                <p class="mt-1 text-sm text-gray-900">{{ order.supplier.name }}</p>
                                <p class="text-sm text-gray-500">{{ order.supplier.rut }}</p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Fecha de Orden</h3>
                                <p class="mt-1 text-sm text-gray-900">{{ formatDate(order.order_date) }}</p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Fecha Esperada</h3>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ order.expected_date ? formatDate(order.expected_date) : 'No especificada' }}
                                </p>
                            </div>
                            
                            <div v-if="order.reference">
                                <h3 class="text-sm font-medium text-gray-500">Referencia</h3>
                                <p class="mt-1 text-sm text-gray-900">{{ order.reference }}</p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Creada por</h3>
                                <p class="mt-1 text-sm text-gray-900">{{ order.user?.name || 'Usuario eliminado' }}</p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Moneda</h3>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ order.currency }}
                                    <span v-if="order.currency !== 'CLP'" class="text-gray-500">
                                        (TC: {{ order.exchange_rate }})
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div v-if="order.notes" class="mt-6">
                            <h3 class="text-sm font-medium text-gray-500">Notas</h3>
                            <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ order.notes }}</p>
                        </div>
                    </div>
                </Card>

                <!-- Items -->
                <Card class="mb-6">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Items</h2>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Descripción
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            SKU
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cantidad
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Recibido
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Precio Unit.
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Desc.
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in order.items" :key="item.id">
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ item.description }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ item.sku || '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ item.quantity }} {{ item.unit }}
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <div class="flex items-center">
                                                <span :class="[
                                                    item.is_fully_received ? 'text-green-600' : 'text-gray-900'
                                                ]">
                                                    {{ item.quantity_received }}
                                                </span>
                                                <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                    <div
                                                        class="bg-green-600 h-2 rounded-full"
                                                        :style="`width: ${item.received_percentage}%`"
                                                    ></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ formatCurrency(item.unit_price) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ item.discount_percent }}%
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                            {{ formatCurrency(item.total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totales -->
                        <div class="mt-6 border-t pt-6">
                            <div class="max-w-xs ml-auto">
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-medium">{{ formatCurrency(order.subtotal) }}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Descuento:</span>
                                    <span class="font-medium">{{ formatCurrency(order.discount_amount) }}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">IVA:</span>
                                    <span class="font-medium">{{ formatCurrency(order.tax_amount) }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-t pt-4">
                                    <span class="text-lg font-medium">Total:</span>
                                    <span class="text-lg font-bold">{{ formatCurrency(order.total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Recepciones -->
                <Card v-if="order.receipts.length > 0">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Recepciones</h2>
                        
                        <div class="space-y-4">
                            <div
                                v-for="receipt in order.receipts"
                                :key="receipt.id"
                                class="border rounded-lg p-4"
                            >
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium">{{ receipt.receipt_number }}</h3>
                                        <p class="text-sm text-gray-500">
                                            {{ formatDateTime(receipt.received_at) }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            Recibido por: {{ receipt.received_by }}
                                        </p>
                                    </div>
                                    <Link
                                        :href="route('purchase-orders.receipts.show', [order, receipt])"
                                        class="text-blue-600 hover:text-blue-900 text-sm"
                                    >
                                        Ver detalles
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Modal de cancelación -->
        <Modal :show="showCancelModal" @close="showCancelModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Cancelar Orden de Compra
                </h3>
                
                <div class="mb-4">
                    <InputLabel for="cancel_reason" value="Motivo de cancelación" />
                    <textarea
                        id="cancel_reason"
                        v-model="cancelForm.reason"
                        rows="3"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        required
                    ></textarea>
                    <InputError :message="cancelForm.errors.reason" class="mt-2" />
                </div>

                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showCancelModal = false">
                        Cerrar
                    </SecondaryButton>
                    <DangerButton @click="cancelOrder" :disabled="cancelForm.processing">
                        Cancelar Orden
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useFormatters } from '@/composables/useFormatters';

const { formatCurrency, formatDate, formatDateTime } = useFormatters();

const props = defineProps({
    order: Object,
});

const showCancelModal = ref(false);

const cancelForm = useForm({
    reason: '',
});

const sendOrder = () => {
    router.post(route('purchase-orders.send', props.order), {}, {
        preserveScroll: true,
    });
};

const confirmOrder = () => {
    router.post(route('purchase-orders.confirm', props.order), {}, {
        preserveScroll: true,
    });
};

const cancelOrder = () => {
    cancelForm.post(route('purchase-orders.cancel', props.order), {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
            cancelForm.reset();
        },
    });
};
</script>