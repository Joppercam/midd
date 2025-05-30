<template>
    <AuthenticatedLayout>
        <Head title="Reporte Mensual de Conciliación" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header con selector de período -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <div class="flex justify-between items-center">
                        <h2 class="text-2xl font-semibold text-gray-900">Reporte Mensual de Conciliación</h2>
                        
                        <form @submit.prevent="loadReport" class="flex items-center space-x-3">
                            <select v-model="form.month" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="month in months" :key="month.value" :value="month.value">
                                    {{ month.label }}
                                </option>
                            </select>
                            
                            <select v-model="form.year" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="year in years" :key="year" :value="year">
                                    {{ year }}
                                </option>
                            </select>
                            
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Consultar
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Resumen del Período -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Resumen {{ summary.period.month_name }} {{ summary.period.year }}
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <p class="text-3xl font-bold text-gray-900">{{ summary.totals.reconciliations }}</p>
                            <p class="text-sm text-gray-500">Conciliaciones Totales</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-green-600">{{ summary.totals.completed }}</p>
                            <p class="text-sm text-gray-500">Completadas</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-yellow-600">{{ summary.totals.pending }}</p>
                            <p class="text-sm text-gray-500">Pendientes</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-blue-600">{{ summary.totals.total_transactions }}</p>
                            <p class="text-sm text-gray-500">Transacciones</p>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Total Depósitos</p>
                            <p class="text-xl font-semibold text-green-600">
                                {{ formatCurrency(summary.totals.total_deposits || 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Total Retiros</p>
                            <p class="text-xl font-semibold text-red-600">
                                {{ formatCurrency(summary.totals.total_withdrawals || 0) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Resumen por Cuenta -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6" v-if="summary.accounts && summary.accounts.length > 0">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Resumen por Cuenta Bancaria</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Banco</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Conciliaciones</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Completadas</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Pendientes</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última Conciliación</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="account in summary.accounts" :key="account.account_name">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ account.account_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ account.bank_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900">
                                        {{ account.reconciliations }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ account.completed }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <span v-if="account.pending > 0" 
                                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ account.pending }}
                                        </span>
                                        <span v-else class="text-gray-400">0</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(account.last_reconciliation) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Lista de Conciliaciones del Mes -->
                <div class="bg-white shadow-sm rounded-lg p-6" v-if="reconciliations && reconciliations.length > 0">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Conciliaciones del Período</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Estado Cuenta</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Diferencia</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="reconciliation in reconciliations" :key="reconciliation.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(reconciliation.reconciliation_date) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ reconciliation.bank_account.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <span :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            reconciliation.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                        ]">
                                            {{ reconciliation.status === 'completed' ? 'Completada' : 'Borrador' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                        {{ formatCurrency(reconciliation.statement_balance) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right" 
                                        :class="reconciliation.difference === 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(reconciliation.difference) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link :href="route('banking.reconcile.report', reconciliation.id)" 
                                              class="text-indigo-600 hover:text-indigo-900">
                                            Ver Reporte
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Estado vacío -->
                <div v-else class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No hay conciliaciones</h3>
                        <p class="mt-1 text-sm text-gray-500">No se encontraron conciliaciones para el período seleccionado.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import { useFormatters } from '@/composables/useFormatters';

const props = defineProps({
    reconciliations: Array,
    summary: Object,
    period: Object
});

const { formatCurrency, formatDate } = useFormatters();

const currentDate = new Date();
const form = reactive({
    month: props.period?.month || currentDate.getMonth() + 1,
    year: props.period?.year || currentDate.getFullYear()
});

const months = [
    { value: 1, label: 'Enero' },
    { value: 2, label: 'Febrero' },
    { value: 3, label: 'Marzo' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Mayo' },
    { value: 6, label: 'Junio' },
    { value: 7, label: 'Julio' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Septiembre' },
    { value: 10, label: 'Octubre' },
    { value: 11, label: 'Noviembre' },
    { value: 12, label: 'Diciembre' }
];

const years = Array.from({ length: 5 }, (_, i) => currentDate.getFullYear() - 2 + i);

const loadReport = () => {
    router.get(route('banking.monthly-report'), {
        month: form.month,
        year: form.year
    });
};
</script>