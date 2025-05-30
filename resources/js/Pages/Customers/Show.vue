<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ customer.name }}
                </h2>
                <div class="flex items-center space-x-2">
                    <Link
                        :href="route('customers.statement', customer.id)"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Estado de Cuenta
                    </Link>
                    <Link
                        :href="route('customers.edit', customer.id)"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Editar
                    </Link>
                    <Link
                        :href="route('customers.index')"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Volver al listado
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Información del Cliente -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Datos Básicos -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información General</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">RUT</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ customer.rut }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ customer.type === 'company' ? 'Empresa' : 'Persona Natural' }}
                                    </dd>
                                </div>
                                <div v-if="customer.business_name">
                                    <dt class="text-sm font-medium text-gray-500">Giro</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ customer.business_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                    <dd class="mt-1">
                                        <span v-if="customer.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Activo
                                        </span>
                                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Inactivo
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Contacto</h3>
                            <dl class="space-y-3">
                                <div v-if="customer.email">
                                    <dt class="text-sm font-medium text-gray-500">Correo Electrónico</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a :href="`mailto:${customer.email}`" class="text-indigo-600 hover:text-indigo-500">
                                            {{ customer.email }}
                                        </a>
                                    </dd>
                                </div>
                                <div v-if="customer.phone">
                                    <dt class="text-sm font-medium text-gray-500">Teléfono</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a :href="`tel:${customer.phone}`" class="text-indigo-600 hover:text-indigo-500">
                                            {{ customer.phone }}
                                        </a>
                                    </dd>
                                </div>
                                <div v-if="customer.address">
                                    <dt class="text-sm font-medium text-gray-500">Dirección</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ customer.address }}</dd>
                                </div>
                                <div v-if="customer.city || customer.commune">
                                    <dt class="text-sm font-medium text-gray-500">Ciudad/Comuna</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ [customer.city, customer.commune].filter(Boolean).join(', ') }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Información Comercial -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información Comercial</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Límite de Crédito</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900">
                                    {{ formatCurrency(customer.credit_limit) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Condiciones de Pago</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ paymentTermsLabel(customer.payment_terms) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Compras</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900">
                                    {{ formatCurrency(statistics.total_purchases) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Saldo Pendiente</dt>
                                <dd class="mt-1 text-2xl font-semibold" :class="statistics.balance > 0 ? 'text-red-600' : 'text-gray-900'">
                                    {{ formatCurrency(statistics.balance) }}
                                </dd>
                            </div>
                        </div>
                        <div v-if="customer.notes" class="mt-6">
                            <dt class="text-sm font-medium text-gray-500">Notas</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ customer.notes }}</dd>
                        </div>
                    </div>
                </div>

                <!-- Historial de Compras -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Historial de Compras</h3>
                            <Link
                                :href="route('invoices.create', { customer_id: customer.id })"
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Nueva Factura
                            </Link>
                        </div>

                        <div v-if="documents.data.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Fecha
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Documento
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Saldo
                                        </th>
                                        <th class="relative px-6 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="document in documents.data" :key="document.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDate(document.issue_date) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ document.formatted_number }}</div>
                                            <div class="text-sm text-gray-500">{{ documentTypeLabel(document.document_type) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="statusClass(document.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                {{ statusLabel(document.status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatCurrency(document.total_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm" :class="document.balance > 0 ? 'text-red-600 font-medium' : 'text-gray-900'">
                                            {{ formatCurrency(document.balance) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link
                                                :href="route('invoices.show', document.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Ver
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Sin documentos</h3>
                            <p class="mt-1 text-sm text-gray-500">Este cliente no tiene documentos registrados.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    customer: {
        type: Object,
        required: true
    },
    documents: {
        type: Object,
        required: true
    },
    statistics: {
        type: Object,
        required: true
    }
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL');
};

const paymentTermsLabel = (terms) => {
    const labels = {
        'immediate': 'Contado',
        '15_days': '15 días',
        '30_days': '30 días',
        '60_days': '60 días',
        '90_days': '90 días'
    };
    return labels[terms] || terms;
};

const documentTypeLabel = (type) => {
    const labels = {
        'invoice': 'Factura',
        'credit_note': 'Nota de Crédito',
        'debit_note': 'Nota de Débito',
        'receipt': 'Boleta'
    };
    return labels[type] || type;
};

const statusLabel = (status) => {
    const labels = {
        'draft': 'Borrador',
        'sent': 'Enviado',
        'accepted': 'Aceptado',
        'rejected': 'Rechazado',
        'cancelled': 'Anulado',
        'paid': 'Pagado',
        'overdue': 'Vencido'
    };
    return labels[status] || status;
};

const statusClass = (status) => {
    const classes = {
        'draft': 'bg-gray-100 text-gray-800',
        'sent': 'bg-blue-100 text-blue-800',
        'accepted': 'bg-green-100 text-green-800',
        'rejected': 'bg-red-100 text-red-800',
        'cancelled': 'bg-gray-100 text-gray-800',
        'paid': 'bg-green-100 text-green-800',
        'overdue': 'bg-red-100 text-red-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};
</script>