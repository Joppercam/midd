<template>
    <Head :title="`Cotización #${quote.quote_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Cotización #{{ quote.quote_number }}
                </h2>
                <Link :href="route('quotes.index')" class="text-sm text-gray-600 hover:text-gray-900">
                    Volver al listado
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Acciones -->
                <div class="mb-6">
                    <Card>
                        <template #content>
                            <div class="flex flex-wrap gap-3">
                                <Link
                                    v-if="quote.status === 'draft'"
                                    :href="route('quotes.edit', quote.id)"
                                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
                                >
                                    Editar
                                </Link>
                                
                                <button
                                    v-if="['draft', 'sent'].includes(quote.status)"
                                    @click="sendQuote"
                                    :disabled="sending"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                                >
                                    {{ quote.status === 'draft' ? 'Enviar' : 'Reenviar' }}
                                </button>
                                
                                <button
                                    v-if="quote.status === 'sent'"
                                    @click="approveQuote"
                                    :disabled="processing"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
                                >
                                    Aprobar
                                </button>
                                
                                <button
                                    v-if="quote.status === 'sent'"
                                    @click="rejectQuote"
                                    :disabled="processing"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                                >
                                    Rechazar
                                </button>
                                
                                <button
                                    v-if="quote.status === 'approved' && !quote.invoice_id"
                                    @click="convertToInvoice"
                                    :disabled="processing"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                                >
                                    Convertir a Factura
                                </button>
                                
                                <a
                                    :href="route('quotes.download', quote.id)"
                                    target="_blank"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                                >
                                    Descargar PDF
                                </a>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Información de la cotización -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div class="lg:col-span-2">
                        <Card>
                            <template #header>
                                <h3 class="text-lg font-medium text-gray-900">Información de la Cotización</h3>
                            </template>
                            <template #content>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                        <dd class="mt-1">
                                            <Badge :type="getStatusType(quote.status)">
                                                {{ getStatusLabel(quote.status) }}
                                            </Badge>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Fecha</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(quote.quote_date) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Válida hasta</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(quote.valid_until) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Términos de pago</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ quote.payment_terms || 'No especificado' }}</dd>
                                    </div>
                                    <div v-if="quote.sent_at">
                                        <dt class="text-sm font-medium text-gray-500">Enviada el</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(quote.sent_at) }}</dd>
                                    </div>
                                    <div v-if="quote.approved_at">
                                        <dt class="text-sm font-medium text-gray-500">Aprobada el</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(quote.approved_at) }}</dd>
                                    </div>
                                    <div v-if="quote.rejected_at">
                                        <dt class="text-sm font-medium text-gray-500">Rechazada el</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(quote.rejected_at) }}</dd>
                                    </div>
                                    <div v-if="quote.invoice_id">
                                        <dt class="text-sm font-medium text-gray-500">Factura</dt>
                                        <dd class="mt-1">
                                            <Link :href="route('invoices.show', quote.invoice_id)" class="text-sm text-blue-600 hover:text-blue-800">
                                                Ver Factura #{{ quote.invoice?.invoice_number }}
                                            </Link>
                                        </dd>
                                    </div>
                                </dl>
                            </template>
                        </Card>
                    </div>

                    <div>
                        <Card>
                            <template #header>
                                <h3 class="text-lg font-medium text-gray-900">Cliente</h3>
                            </template>
                            <template #content>
                                <div class="text-sm">
                                    <p class="font-medium text-gray-900">{{ quote.customer.name }}</p>
                                    <p class="text-gray-500">RUT: {{ quote.customer.rut }}</p>
                                    <p class="text-gray-500" v-if="quote.customer.email">{{ quote.customer.email }}</p>
                                    <p class="text-gray-500" v-if="quote.customer.phone">{{ quote.customer.phone }}</p>
                                    <p class="text-gray-500 mt-2" v-if="quote.customer.address">{{ quote.customer.address }}</p>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>

                <!-- Items -->
                <Card class="mb-6">
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">Items</h3>
                    </template>
                    <template #content>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Producto/Servicio
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Descripción
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cantidad
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Precio Unit.
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in quote.items" :key="item.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ item.product?.name || 'Servicio' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ item.description || '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ formatNumber(item.quantity) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            ${{ formatCurrency(item.unit_price) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                            ${{ formatCurrency(item.total_amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="4" class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                                            Subtotal:
                                        </td>
                                        <td class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                                            ${{ formatCurrency(quote.subtotal) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                                            IVA (19%):
                                        </td>
                                        <td class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                                            ${{ formatCurrency(quote.tax_amount) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-6 py-3 text-right text-lg font-bold text-gray-900">
                                            Total:
                                        </td>
                                        <td class="px-6 py-3 text-right text-lg font-bold text-gray-900">
                                            ${{ formatCurrency(quote.total_amount) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </template>
                </Card>

                <!-- Notas -->
                <Card v-if="quote.notes">
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">Notas</h3>
                    </template>
                    <template #content>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ quote.notes }}</p>
                    </template>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    quote: Object,
});

const sending = ref(false);
const processing = ref(false);

const sendQuote = () => {
    if (confirm('¿Enviar esta cotización al cliente?')) {
        sending.value = true;
        router.post(route('quotes.send', props.quote.id), {}, {
            onFinish: () => sending.value = false,
        });
    }
};

const approveQuote = () => {
    if (confirm('¿Aprobar esta cotización?')) {
        processing.value = true;
        router.post(route('quotes.approve', props.quote.id), {}, {
            onFinish: () => processing.value = false,
        });
    }
};

const rejectQuote = () => {
    if (confirm('¿Rechazar esta cotización?')) {
        processing.value = true;
        router.post(route('quotes.reject', props.quote.id), {}, {
            onFinish: () => processing.value = false,
        });
    }
};

const convertToInvoice = () => {
    if (confirm('¿Convertir esta cotización en factura?')) {
        processing.value = true;
        router.post(route('quotes.convert', props.quote.id), {}, {
            onFinish: () => processing.value = false,
        });
    }
};

const getStatusType = (status) => {
    const types = {
        draft: 'gray',
        sent: 'blue',
        approved: 'green',
        rejected: 'red',
        converted: 'purple',
    };
    return types[status] || 'gray';
};

const getStatusLabel = (status) => {
    const labels = {
        draft: 'Borrador',
        sent: 'Enviada',
        approved: 'Aprobada',
        rejected: 'Rechazada',
        converted: 'Convertida',
    };
    return labels[status] || status;
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('es-CL');
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('es-CL', { minimumFractionDigits: 2 }).format(value || 0);
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CL').format(value || 0);
};
</script>