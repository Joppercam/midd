<template>
    <AuthenticatedLayout>
        <Head title="Reporte de Conciliación" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900">Reporte de Conciliación</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ report.bank_account.name }} - {{ report.bank_account.bank_name }}
                            </p>
                            <p class="text-sm text-gray-500">
                                Período: {{ formatDate(report.reconciliation.period.start) }} al {{ formatDate(report.reconciliation.period.end) }}
                            </p>
                        </div>
                        <div class="flex space-x-3">
                            <a :href="route('banking.reconcile.export.pdf', reconciliation.id)"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Exportar PDF
                            </a>
                            <a :href="route('banking.reconcile.export.excel', reconciliation.id)"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Exportar Excel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Resumen de Balances -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Resumen de Balances</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Saldo Inicial</p>
                            <p class="text-2xl font-semibold">{{ formatCurrency(report.balances.opening_balance) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Saldo Final Estado de Cuenta</p>
                            <p class="text-2xl font-semibold">{{ formatCurrency(report.balances.closing_balance) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Saldo Sistema</p>
                            <p class="text-2xl font-semibold">{{ formatCurrency(report.balances.system_balance) }}</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-6 border-t">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-sm text-gray-500">Diferencia</p>
                                <p class="text-xl font-semibold" :class="report.balances.difference === 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ formatCurrency(report.balances.difference) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Ajustes</p>
                                <p class="text-xl font-semibold">{{ formatCurrency(report.balances.total_adjustments) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Diferencia Final</p>
                                <p class="text-xl font-semibold" :class="report.balances.final_difference === 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ formatCurrency(report.balances.final_difference) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas de Transacciones -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas de Transacciones</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <p class="text-3xl font-bold text-gray-900">{{ report.summary.total_transactions }}</p>
                            <p class="text-sm text-gray-500">Total Transacciones</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-green-600">{{ report.summary.matched_transactions }}</p>
                            <p class="text-sm text-gray-500">Conciliadas</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-yellow-600">{{ report.summary.unmatched_transactions }}</p>
                            <p class="text-sm text-gray-500">Pendientes</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-gray-600">{{ report.summary.ignored_transactions }}</p>
                            <p class="text-sm text-gray-500">Ignoradas</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-6 border-t grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Depósitos</p>
                            <p class="text-lg font-semibold text-green-600">
                                {{ formatCurrency(report.summary.total_deposits) }}
                                <span class="text-sm text-gray-500">({{ report.summary.deposit_count }} transacciones)</span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Retiros</p>
                            <p class="text-lg font-semibold text-red-600">
                                {{ formatCurrency(report.summary.total_withdrawals) }}
                                <span class="text-sm text-gray-500">({{ report.summary.withdrawal_count }} transacciones)</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Transacciones Conciliadas -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6" v-if="report.matched_transactions.length > 0">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Transacciones Conciliadas</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conciliado con</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="transaction in report.matched_transactions" :key="transaction.date + transaction.amount">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(transaction.date) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ transaction.description }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="transaction.amount > 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(transaction.amount) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <span v-if="transaction.matched_with">
                                            {{ transaction.matched_with.type }}: {{ transaction.matched_with.reference }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Transacciones No Conciliadas -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6" v-if="report.unmatched_transactions.length > 0">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Transacciones No Conciliadas</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referencia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="transaction in report.unmatched_transactions" :key="transaction.date + transaction.amount">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(transaction.date) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ transaction.description }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="transaction.amount > 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(transaction.amount) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ transaction.reference || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Ajustes -->
                <div class="bg-white shadow-sm rounded-lg p-6" v-if="report.adjustments && report.adjustments.length > 0">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Ajustes</h3>
                    <div class="space-y-2">
                        <div v-for="(adjustment, index) in report.adjustments" :key="index" 
                             class="flex justify-between items-center py-2 border-b">
                            <span class="text-sm text-gray-900">{{ adjustment.description }}</span>
                            <span class="text-sm font-medium" :class="adjustment.amount > 0 ? 'text-green-600' : 'text-red-600'">
                                {{ formatCurrency(adjustment.amount) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useFormatters } from '@/composables/useFormatters';

const props = defineProps({
    reconciliation: Object,
    report: Object
});

const { formatCurrency, formatDate } = useFormatters();
</script>