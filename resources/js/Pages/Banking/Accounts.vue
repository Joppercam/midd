<template>
    <AuthenticatedLayout title="Cuentas Bancarias">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Encabezado -->
                <div class="mb-8 flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Cuentas Bancarias</h1>
                        <p class="mt-2 text-gray-600">Administra todas tus cuentas bancarias</p>
                    </div>
                    <div class="flex space-x-3">
                        <Link :href="route('banking.index')" class="btn btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Volver
                        </Link>
                        <Link :href="route('banking.accounts.create')" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Nueva Cuenta
                        </Link>
                    </div>
                </div>

                <!-- Lista de Cuentas -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cuenta
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Saldo Actual
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Saldo Conciliado
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Transacciones
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="account in accounts" :key="account.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ account.name }}</p>
                                            <p class="text-sm text-gray-500">{{ account.bank_name }}</p>
                                            <p v-if="account.account_number" class="text-xs text-gray-400">
                                                ****{{ account.account_number.slice(-4) }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                              :class="{
                                                  'bg-blue-100 text-blue-800': account.account_type === 'checking',
                                                  'bg-green-100 text-green-800': account.account_type === 'savings',
                                                  'bg-purple-100 text-purple-800': account.account_type === 'credit_card'
                                              }">
                                            {{ getAccountTypeLabel(account.account_type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ formatCurrency(account.current_balance) }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <p class="text-sm font-medium"
                                           :class="account.current_balance === account.reconciled_balance ? 'text-green-600' : 'text-yellow-600'">
                                            {{ formatCurrency(account.reconciled_balance) }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <p class="text-sm text-gray-900">{{ account.transactions_count || 0 }}</p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span v-if="account.current_balance === account.reconciled_balance" 
                                              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Conciliado
                                        </span>
                                        <span v-else 
                                              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Por Conciliar
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <Link :href="route('banking.transactions', account.id)" 
                                                  class="text-gray-600 hover:text-gray-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                            </Link>
                                            <Link :href="route('banking.reconcile.start', account.id)" 
                                                  class="text-indigo-600 hover:text-indigo-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                                </svg>
                                            </Link>
                                            <Link :href="route('banking.accounts.edit', account.id)" 
                                                  class="text-gray-600 hover:text-gray-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Resumen Total -->
                <div class="mt-6 bg-gray-50 rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-sm text-gray-600">Total en Cuentas</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalBalance) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Conciliado</p>
                            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(totalReconciledBalance) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Diferencia</p>
                            <p class="text-2xl font-bold" 
                               :class="difference === 0 ? 'text-gray-900' : 'text-yellow-600'">
                                {{ formatCurrency(difference) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    accounts: Array
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};

const getAccountTypeLabel = (type) => {
    const labels = {
        'checking': 'Cuenta Corriente',
        'savings': 'Cuenta de Ahorro',
        'credit_card': 'Tarjeta de Crédito'
    };
    return labels[type] || type;
};

const totalBalance = computed(() => {
    return props.accounts.reduce((sum, account) => sum + parseFloat(account.current_balance || 0), 0);
});

const totalReconciledBalance = computed(() => {
    return props.accounts.reduce((sum, account) => sum + parseFloat(account.reconciled_balance || 0), 0);
});

const difference = computed(() => {
    return totalBalance.value - totalReconciledBalance.value;
});
</script>