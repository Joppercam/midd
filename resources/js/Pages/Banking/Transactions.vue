<template>
    <AuthenticatedLayout :title="`Transacciones - ${bankAccount.name}`">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Encabezado -->
                <div class="mb-8">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">{{ bankAccount.name }}</h1>
                            <p class="mt-2 text-gray-600">{{ bankAccount.bank_name }}</p>
                            <div class="mt-4 flex space-x-6">
                                <div>
                                    <p class="text-sm text-gray-500">Saldo Actual</p>
                                    <p class="text-xl font-semibold text-gray-900">{{ formatCurrency(bankAccount.current_balance) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Saldo Conciliado</p>
                                    <p class="text-xl font-semibold text-green-600">{{ formatCurrency(bankAccount.reconciled_balance) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <button @click="showImportModal = true" class="btn btn-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                Importar Extracto
                            </button>
                            <Link :href="route('banking.reconcile.start', bankAccount.id)" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Iniciar Conciliación
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                            <input
                                v-model="filters.search"
                                type="text"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Descripción, referencia..."
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                            <select v-model="filters.type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Todos</option>
                                <option value="debit">Débito</option>
                                <option value="credit">Crédito</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select v-model="filters.status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Todos</option>
                                <option value="matched">Conciliado</option>
                                <option value="pending">Pendiente</option>
                                <option value="ignored">Ignorado</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                            <select v-model="filters.period" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Todo</option>
                                <option value="today">Hoy</option>
                                <option value="week">Esta semana</option>
                                <option value="month">Este mes</option>
                                <option value="lastMonth">Mes anterior</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Lista de Transacciones -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Descripción
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Débito
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Crédito
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Saldo
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
                                <tr v-for="transaction in filteredTransactions" :key="transaction.id" 
                                    class="hover:bg-gray-50"
                                    :class="{ 'bg-green-50': transaction.is_reconciled, 'bg-gray-100': transaction.is_ignored }">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(transaction.transaction_date) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ transaction.description }}</p>
                                            <p v-if="transaction.reference" class="text-xs text-gray-500">Ref: {{ transaction.reference }}</p>
                                            <div v-if="transaction.matches && transaction.matches.length > 0" class="mt-1">
                                                <span class="text-xs text-green-600">
                                                    Conciliado con: {{ getMatchDescription(transaction.matches[0]) }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600">
                                        {{ transaction.type === 'debit' ? formatCurrency(transaction.amount) : '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">
                                        {{ transaction.type === 'credit' ? formatCurrency(transaction.amount) : '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900">
                                        {{ formatCurrency(transaction.balance) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span v-if="transaction.is_reconciled" 
                                              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Conciliado
                                        </span>
                                        <span v-else-if="transaction.is_ignored" 
                                              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Ignorado
                                        </span>
                                        <span v-else 
                                              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pendiente
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <button v-if="!transaction.is_reconciled && !transaction.is_ignored"
                                                    @click="matchTransaction(transaction)"
                                                    class="text-indigo-600 hover:text-indigo-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                </svg>
                                            </button>
                                            <button v-if="transaction.is_reconciled"
                                                    @click="unmatchTransaction(transaction)"
                                                    class="text-red-600 hover:text-red-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            </button>
                                            <button v-if="!transaction.is_reconciled && !transaction.is_ignored"
                                                    @click="ignoreTransaction(transaction)"
                                                    class="text-gray-600 hover:text-gray-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="transactions.links" class="px-6 py-4 border-t">
                        <Pagination :links="transactions.links" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Importación -->
        <Modal :show="showImportModal" @close="showImportModal = false" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Importar Extracto Bancario</h2>
                
                <form @submit.prevent="importStatement">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Formato del archivo
                            </label>
                            <select v-model="importForm.format" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="csv">CSV</option>
                                <option value="excel">Excel (XLS/XLSX)</option>
                                <option value="ofx">OFX</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Archivo
                            </label>
                            <input
                                type="file"
                                @change="handleFileUpload"
                                :accept="getAcceptedFormats()"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                required
                            />
                        </div>

                        <div class="bg-yellow-50 rounded-lg p-4">
                            <p class="text-sm text-yellow-800">
                                <strong>Formato CSV:</strong> El archivo debe contener columnas para fecha, descripción, débito/crédito o monto.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" @click="showImportModal = false" class="btn btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="!importForm.file || importForm.processing" class="btn btn-primary">
                            {{ importForm.processing ? 'Importando...' : 'Importar' }}
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
import Pagination from '@/Components/UI/Pagination.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    bankAccount: Object,
    transactions: Object
});

const showImportModal = ref(false);
const filters = ref({
    search: '',
    type: '',
    status: '',
    period: ''
});

const importForm = useForm({
    format: 'csv',
    file: null
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
    if (match.matchable_type === 'payment') {
        return `Pago #${match.matchable.id}`;
    } else if (match.matchable_type === 'expense') {
        return `Gasto #${match.matchable.id}`;
    }
    return 'Transacción';
};

const getAcceptedFormats = () => {
    switch (importForm.format) {
        case 'csv':
            return '.csv';
        case 'excel':
            return '.xls,.xlsx';
        case 'ofx':
            return '.ofx';
        default:
            return '';
    }
};

const handleFileUpload = (event) => {
    importForm.file = event.target.files[0];
};

const importStatement = () => {
    const formData = new FormData();
    formData.append('file', importForm.file);
    formData.append('format', importForm.format);

    router.post(route('banking.import', props.bankAccount.id), formData, {
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
        }
    });
};

const matchTransaction = (transaction) => {
    router.visit(route('banking.match', transaction.id));
};

const unmatchTransaction = (transaction) => {
    if (confirm('¿Deseas deshacer esta conciliación?')) {
        router.post(route('banking.unmatch', transaction.id));
    }
};

const ignoreTransaction = (transaction) => {
    if (confirm('¿Marcar esta transacción como ignorada?')) {
        router.post(route('banking.ignore', transaction.id));
    }
};

const filteredTransactions = computed(() => {
    return props.transactions.data;
});
</script>