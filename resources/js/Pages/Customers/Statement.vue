<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Estado de Cuenta - {{ customer.name }}
                </h2>
                <div class="flex items-center space-x-2">
                    <button
                        @click="print"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Imprimir
                    </button>
                    <Link
                        :href="route('customers.show', customer.id)"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Volver al cliente
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div id="statement-content" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-8">
                        <!-- Encabezado -->
                        <div class="border-b pb-6 mb-6">
                            <div class="grid grid-cols-2 gap-8">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Estado de Cuenta</h3>
                                    <div class="space-y-1 text-sm">
                                        <p class="font-medium">{{ customer.name }}</p>
                                        <p>RUT: {{ customer.rut }}</p>
                                        <p v-if="customer.address">{{ customer.address }}</p>
                                        <p v-if="customer.city || customer.commune">
                                            {{ [customer.city, customer.commune].filter(Boolean).join(', ') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="space-y-1 text-sm">
                                        <p><span class="font-medium">Fecha:</span> {{ formatDate(new Date()) }}</p>
                                        <p><span class="font-medium">Período:</span> {{ period.start }} - {{ period.end }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen -->
                        <div class="grid grid-cols-3 gap-6 mb-8">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">Saldo Anterior</p>
                                <p class="text-xl font-semibold">{{ formatCurrency(summary.previous_balance) }}</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">Cargos del Período</p>
                                <p class="text-xl font-semibold">{{ formatCurrency(summary.charges) }}</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">Pagos del Período</p>
                                <p class="text-xl font-semibold">{{ formatCurrency(summary.payments) }}</p>
                            </div>
                        </div>

                        <!-- Movimientos -->
                        <div class="mb-8">
                            <h4 class="text-lg font-medium mb-4">Detalle de Movimientos</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fecha
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Documento
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Descripción
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Cargos
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Abonos
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Saldo
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="movement in movements" :key="movement.id">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ formatDate(movement.date) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ movement.document_number }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                {{ movement.description }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                                {{ movement.charge ? formatCurrency(movement.charge) : '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                                {{ movement.payment ? formatCurrency(movement.payment) : '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium" :class="movement.balance > 0 ? 'text-red-600' : 'text-gray-900'">
                                                {{ formatCurrency(movement.balance) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="3" class="px-4 py-3 text-sm font-medium text-gray-900">
                                                Total del Período
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-medium">
                                                {{ formatCurrency(summary.charges) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-medium">
                                                {{ formatCurrency(summary.payments) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-bold" :class="summary.current_balance > 0 ? 'text-red-600' : 'text-gray-900'">
                                                {{ formatCurrency(summary.current_balance) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Resumen Final -->
                        <div class="border-t pt-6">
                            <div class="grid grid-cols-2 gap-8">
                                <div>
                                    <h4 class="text-lg font-medium mb-3">Antigüedad de Saldo</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span>Por Vencer</span>
                                            <span class="font-medium">{{ formatCurrency(aging.current) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>1-30 días</span>
                                            <span class="font-medium">{{ formatCurrency(aging.days_30) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>31-60 días</span>
                                            <span class="font-medium">{{ formatCurrency(aging.days_60) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>61-90 días</span>
                                            <span class="font-medium">{{ formatCurrency(aging.days_90) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Más de 90 días</span>
                                            <span class="font-medium text-red-600">{{ formatCurrency(aging.over_90) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="bg-gray-100 p-6 rounded-lg inline-block">
                                        <p class="text-sm text-gray-600 mb-1">Saldo Total</p>
                                        <p class="text-3xl font-bold" :class="summary.current_balance > 0 ? 'text-red-600' : 'text-gray-900'">
                                            {{ formatCurrency(summary.current_balance) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
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
    movements: {
        type: Array,
        required: true
    },
    summary: {
        type: Object,
        required: true
    },
    aging: {
        type: Object,
        required: true
    },
    period: {
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

const print = () => {
    window.print();
};
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    
    #statement-content, #statement-content * {
        visibility: visible;
    }
    
    #statement-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    .no-print {
        display: none !important;
    }
}
</style>