<template>
    <AuthenticatedLayout :title="`Conciliación - ${reconciliation.bank_account.name}`">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Encabezado y Resumen -->
                <div class="mb-8">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">
                                    Conciliación Bancaria - {{ reconciliation.bank_account.name }}
                                </h1>
                                <p class="mt-1 text-gray-600">
                                    Período: {{ formatDate(reconciliation.start_date) }} al {{ formatDate(reconciliation.end_date) }}
                                </p>
                            </div>
                            <div class="flex space-x-3">
                                <Link :href="route('banking.reconcile.report', reconciliation.id)" 
                                      class="btn btn-secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Ver Reporte
                                </Link>
                                <button @click="autoMatch" :disabled="processing" class="btn btn-secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    {{ processing ? 'Procesando...' : 'Conciliación Automática' }}
                                </button>
                                <button @click="completeReconciliation" 
                                        :disabled="summary.difference !== 0 || processing"
                                        class="btn btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Completar Conciliación
                                </button>
                            </div>
                        </div>

                        <!-- Resumen de Conciliación -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-sm text-gray-600">Saldo Inicial</p>
                                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(summary.starting_balance) }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-sm text-gray-600">Saldo según Estado de Cuenta</p>
                                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(reconciliation.statement_balance) }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-sm text-gray-600">Saldo Calculado</p>
                                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(summary.calculated_balance) }}</p>
                            </div>
                            <div class="rounded-lg p-4" 
                                 :class="summary.difference === 0 ? 'bg-green-50' : 'bg-red-50'">
                                <p class="text-sm" :class="summary.difference === 0 ? 'text-green-600' : 'text-red-600'">
                                    Diferencia
                                </p>
                                <p class="text-xl font-bold" 
                                   :class="summary.difference === 0 ? 'text-green-900' : 'text-red-900'">
                                    {{ formatCurrency(summary.difference) }}
                                </p>
                            </div>
                        </div>

                        <!-- Estadísticas -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold text-gray-900">{{ summary.total_transactions }}</p>
                                <p class="text-sm text-gray-600">Transacciones Totales</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-green-600">{{ summary.matched_transactions }}</p>
                                <p class="text-sm text-gray-600">Conciliadas</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-yellow-600">{{ summary.pending_transactions }}</p>
                                <p class="text-sm text-gray-600">Pendientes</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button @click="activeTab = 'pending'"
                                    :class="[
                                        activeTab === 'pending' 
                                            ? 'border-indigo-500 text-indigo-600' 
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                    ]">
                                Pendientes ({{ pendingTransactions.length }})
                            </button>
                            <button @click="activeTab = 'matched'"
                                    :class="[
                                        activeTab === 'matched' 
                                            ? 'border-indigo-500 text-indigo-600' 
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                    ]">
                                Conciliadas ({{ matchedTransactions.length }})
                            </button>
                            <button @click="activeTab = 'adjustments'"
                                    :class="[
                                        activeTab === 'adjustments' 
                                            ? 'border-indigo-500 text-indigo-600' 
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                    ]">
                                Ajustes ({{ reconciliation.adjustments?.length || 0 }})
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Contenido de las tabs -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <!-- Transacciones Pendientes -->
                    <div v-if="activeTab === 'pending'" class="p-6">
                        <div v-if="pendingTransactions.length === 0" class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay transacciones pendientes</h3>
                            <p class="mt-1 text-sm text-gray-500">Todas las transacciones han sido conciliadas.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div v-for="transaction in pendingTransactions" :key="transaction.id"
                                 class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ formatDate(transaction.transaction_date) }}
                                                </p>
                                                <p class="text-lg font-semibold text-gray-900 mt-1">
                                                    {{ transaction.description }}
                                                </p>
                                                <p v-if="transaction.reference" class="text-sm text-gray-500">
                                                    Ref: {{ transaction.reference }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right ml-4">
                                        <p class="text-lg font-bold"
                                           :class="transaction.type === 'debit' ? 'text-red-600' : 'text-green-600'">
                                            {{ transaction.type === 'debit' ? '-' : '+' }}{{ formatCurrency(transaction.amount) }}
                                        </p>
                                        <button @click="findMatches(transaction)" 
                                                class="mt-2 text-sm text-indigo-600 hover:text-indigo-900">
                                            Buscar coincidencias
                                        </button>
                                    </div>
                                </div>

                                <!-- Sugerencias de coincidencias -->
                                <div v-if="showSuggestions[transaction.id]" class="mt-4 border-t pt-4">
                                    <p class="text-sm font-medium text-gray-700 mb-2">Posibles coincidencias:</p>
                                    <div class="space-y-2">
                                        <div v-for="suggestion in suggestions[transaction.id]" :key="suggestion.id"
                                             class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                            <div>
                                                <p class="text-sm font-medium">{{ suggestion.description }}</p>
                                                <p class="text-xs text-gray-500">
                                                    {{ formatDate(suggestion.date) }} - {{ formatCurrency(suggestion.amount) }}
                                                </p>
                                            </div>
                                            <button @click="matchWithSuggestion(transaction, suggestion)"
                                                    class="px-3 py-1 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                                                Conciliar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transacciones Conciliadas -->
                    <div v-if="activeTab === 'matched'" class="p-6">
                        <div v-if="matchedTransactions.length === 0" class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay transacciones conciliadas</h3>
                            <p class="mt-1 text-sm text-gray-500">Comienza conciliando las transacciones pendientes.</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transacción Bancaria</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coincidencia</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="transaction in matchedTransactions" :key="transaction.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDate(transaction.transaction_date) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ transaction.description }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            {{ getMatchDescription(transaction.matches[0]) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                            :class="transaction.type === 'debit' ? 'text-red-600' : 'text-green-600'">
                                            {{ transaction.type === 'debit' ? '-' : '+' }}{{ formatCurrency(transaction.amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button @click="unmatchTransaction(transaction)"
                                                    class="text-red-600 hover:text-red-900">
                                                Deshacer
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Ajustes -->
                    <div v-if="activeTab === 'adjustments'" class="p-6">
                        <div class="mb-4">
                            <button @click="showAddAdjustment = true" class="btn btn-secondary btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Agregar Ajuste
                            </button>
                        </div>

                        <div v-if="!reconciliation.adjustments || reconciliation.adjustments.length === 0" 
                             class="text-center py-8 text-gray-500">
                            No hay ajustes registrados
                        </div>

                        <div v-else class="space-y-3">
                            <div v-for="(adjustment, index) in reconciliation.adjustments" :key="index"
                                 class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900">{{ adjustment.description }}</p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <p class="font-semibold"
                                       :class="adjustment.amount > 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ adjustment.amount > 0 ? '+' : '' }}{{ formatCurrency(adjustment.amount) }}
                                    </p>
                                    <button @click="removeAdjustment(index)"
                                            class="text-red-600 hover:text-red-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para agregar ajuste -->
        <Modal :show="showAddAdjustment" @close="showAddAdjustment = false" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Agregar Ajuste</h2>
                
                <form @submit.prevent="addAdjustment">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Descripción
                            </label>
                            <input
                                v-model="adjustmentForm.description"
                                type="text"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Monto
                            </label>
                            <input
                                v-model="adjustmentForm.amount"
                                type="number"
                                step="0.01"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                required
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Use valores negativos para débitos y positivos para créditos
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" @click="showAddAdjustment = false" class="btn btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Agregar
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { ref, computed } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    reconciliation: Object,
    transactions: Array,
    summary: Object
});

const activeTab = ref('pending');
const showAddAdjustment = ref(false);
const showSuggestions = ref({});
const suggestions = ref({});
const processing = ref(false);

const adjustmentForm = useForm({
    description: '',
    amount: ''
});

const pendingTransactions = computed(() => {
    return props.transactions.filter(t => !t.is_reconciled && !t.is_ignored);
});

const matchedTransactions = computed(() => {
    return props.transactions.filter(t => t.is_reconciled);
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

const getMatchDescription = (match) => {
    if (!match) return '';
    if (match.matchable_type === 'payment') {
        return `Pago #${match.matchable.id} - ${match.matchable.description || 'Sin descripción'}`;
    } else if (match.matchable_type === 'expense') {
        return `Gasto #${match.matchable.id} - ${match.matchable.description || 'Sin descripción'}`;
    }
    return 'Transacción';
};

const autoMatch = () => {
    processing.value = true;
    router.post(route('banking.reconcile.auto', props.reconciliation.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        }
    });
};

const findMatches = async (transaction) => {
    try {
        const response = await axios.get(route('banking.transaction.suggestions', transaction.id));
        suggestions.value[transaction.id] = response.data.suggestions;
        showSuggestions.value[transaction.id] = true;
    } catch (error) {
        console.error('Error fetching suggestions:', error);
    }
};

const matchWithSuggestion = (transaction, suggestion) => {
    router.post(route('banking.transaction.match', transaction.id), {
        matchable_type: suggestion.type,
        matchable_id: suggestion.id,
        match_type: 'exact'
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showSuggestions.value[transaction.id] = false;
        }
    });
};

const unmatchTransaction = (transaction) => {
    if (confirm('¿Deseas deshacer esta conciliación?')) {
        router.post(route('banking.transaction.unmatch', transaction.id), {}, {
            preserveScroll: true
        });
    }
};

const addAdjustment = () => {
    adjustmentForm.post(route('banking.reconcile.adjustment.add', props.reconciliation.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddAdjustment.value = false;
            adjustmentForm.reset();
        }
    });
};

const removeAdjustment = (index) => {
    if (confirm('¿Eliminar este ajuste?')) {
        router.delete(route('banking.reconcile.adjustment.remove', [props.reconciliation.id, index]), {
            preserveScroll: true
        });
    }
};

const completeReconciliation = () => {
    if (confirm('¿Completar esta conciliación? Una vez completada no se podrá modificar.')) {
        processing.value = true;
        router.post(route('banking.reconcile.complete', props.reconciliation.id), {}, {
            onFinish: () => {
                processing.value = false;
            }
        });
    }
};
</script>