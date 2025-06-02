<template>
    <AuthenticatedLayout title="Conciliación Bancaria">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Encabezado -->
                <div class="mb-8 flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Conciliación Bancaria</h1>
                        <p class="mt-2 text-gray-600">Gestiona tus cuentas bancarias y concilia transacciones</p>
                    </div>
                    <div class="flex space-x-3">
                        <!-- <Link :href="route('banking.reports.monthly')" class="btn btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Reporte Mensual
                        </Link> -->
                        <!-- Temporalmente deshabilitado hasta configurar rutas del módulo Banking -->
                        <!-- <Link :href="route('banking.accounts.create')" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Nueva Cuenta
                        </Link> -->
                    </div>
                </div>

                <!-- Resumen de Cuentas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <div v-for="account in bankAccounts" :key="account.id" 
                         class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">{{ account.name }}</h3>
                                    <p class="text-sm text-gray-500">{{ account.bank_name }}</p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full"
                                      :class="{
                                          'bg-blue-100 text-blue-800': account.account_type === 'checking',
                                          'bg-green-100 text-green-800': account.account_type === 'savings',
                                          'bg-purple-100 text-purple-800': account.account_type === 'credit_card'
                                      }">
                                    {{ getAccountTypeLabel(account.account_type) }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Saldo Actual</span>
                                    <span class="text-lg font-semibold text-gray-900">
                                        {{ formatCurrency(account.current_balance) }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Saldo Conciliado</span>
                                    <span class="text-lg font-semibold" 
                                          :class="account.current_balance === account.reconciled_balance ? 'text-green-600' : 'text-yellow-600'">
                                        {{ formatCurrency(account.reconciled_balance) }}
                                    </span>
                                </div>
                                <div v-if="account.account_number" class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Número de Cuenta</span>
                                    <span class="text-sm text-gray-900">****{{ account.account_number.slice(-4) }}</span>
                                </div>
                            </div>

                            <div class="mt-6 flex space-x-3">
                                <!-- <Link :href="route('banking.transactions.by-account', account.id)" 
                                      class="flex-1 text-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                                    Ver Transacciones
                                </Link> -->
                                <!-- <Link :href="route('banking.reconcile.start', account.id)"
                                      class="flex-1 text-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                                    Conciliar
                                </Link> -->
                                <div class="text-center text-sm text-gray-500">Funcionalidad en desarrollo</div>
                            </div>

                            <!-- Última conciliación -->
                            <div v-if="account.reconciliations && account.reconciliations.length > 0" class="mt-4 pt-4 border-t">
                                <p class="text-xs text-gray-500">
                                    Última conciliación: {{ formatDate(account.reconciliations[0].reconciliation_date) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conciliaciones Activas -->
                <div v-if="activeReconciliations.length > 0" class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b">
                        <h2 class="text-lg font-semibold text-gray-900">Conciliaciones en Proceso</h2>
                    </div>
                    <div class="divide-y divide-gray-200">
                        <div v-for="reconciliation in activeReconciliations" :key="reconciliation.id" 
                             class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">
                                        {{ reconciliation.bank_account.name }}
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Período: {{ formatDate(reconciliation.start_date) }} - {{ formatDate(reconciliation.end_date) }}
                                    </p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <div class="text-right">
                                        <p class="text-sm text-gray-600">Diferencia</p>
                                        <p class="text-lg font-semibold" 
                                           :class="reconciliation.difference === 0 ? 'text-green-600' : 'text-red-600'">
                                            {{ formatCurrency(reconciliation.difference) }}
                                        </p>
                                    </div>
                                    <!-- <Link :href="route('banking.reconcile.show', reconciliation.id)"
                                          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                                        Continuar
                                    </Link> -->
                                    <span class="px-4 py-2 bg-gray-300 text-gray-600 rounded-lg text-sm font-medium">
                                        En desarrollo
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estado vacío -->
                <div v-if="bankAccounts.length === 0" class="text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay cuentas bancarias</h3>
                    <p class="mt-1 text-sm text-gray-500">Comienza agregando tu primera cuenta bancaria.</p>
                    <div class="mt-6">
                        <!-- <Link :href="route('banking.accounts.create')" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Agregar Cuenta Bancaria
                        </Link> -->
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    bankAccounts: Array,
    activeReconciliations: Array
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const getAccountTypeLabel = (type) => {
    const labels = {
        'checking': 'Cuenta Corriente',
        'savings': 'Cuenta de Ahorro',
        'credit_card': 'Tarjeta de Crédito'
    };
    return labels[type] || type;
};
</script>