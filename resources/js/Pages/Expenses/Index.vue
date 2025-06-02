<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gastos
                </h2>
                <Link
                    :href="route('expenses.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Registrar Gasto
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Total Gastos</div>
                            <div class="mt-1 text-3xl font-semibold text-red-600">
                                {{ formatCurrency(statistics.total_amount) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Por Pagar</div>
                            <div class="mt-1 text-3xl font-semibold text-yellow-600">
                                {{ formatCurrency(statistics.pending_amount) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Este Mes</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(statistics.this_month) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Vencidos</div>
                            <div class="mt-1 text-3xl font-semibold text-red-700">
                                {{ formatCurrency(statistics.overdue_amount) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">IVA Crédito</div>
                            <div class="mt-1 text-3xl font-semibold text-green-600">
                                {{ formatCurrency(statistics.tax_credit) }}
                            </div>
                            <div class="mt-2 text-xs text-gray-500">este mes</div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-7 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                                <input
                                    type="text"
                                    id="search"
                                    v-model="form.search"
                                    placeholder="Número, proveedor..."
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                />
                            </div>
                            <div>
                                <label for="supplier_id" class="block text-sm font-medium text-gray-700">Proveedor</label>
                                <select
                                    id="supplier_id"
                                    v-model="form.supplier_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                        {{ supplier.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="document_type" class="block text-sm font-medium text-gray-700">Tipo</label>
                                <select
                                    id="document_type"
                                    v-model="form.document_type"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="invoice">Factura</option>
                                    <option value="receipt">Boleta</option>
                                    <option value="expense_note">Nota de Gasto</option>
                                    <option value="petty_cash">Caja Chica</option>
                                    <option value="bank_charge">Cargo Bancario</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="draft">Borrador</option>
                                    <option value="pending">Pendiente</option>
                                    <option value="paid">Pagado</option>
                                    <option value="overdue">Vencidos</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                            </div>
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Categoría</label>
                                <select
                                    id="category"
                                    v-model="form.category"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todas</option>
                                    <option v-for="category in categories" :key="category" :value="category">
                                        {{ category }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">Desde</label>
                                <input
                                    type="date"
                                    id="date_from"
                                    v-model="form.date_from"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                />
                            </div>
                            <div class="flex items-end space-x-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                                >
                                    Filtrar
                                </button>
                                <button
                                    type="button"
                                    @click="clearFilters"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400"
                                >
                                    Limpiar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Gastos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Número
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Proveedor
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Saldo
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="relative px-6 py-3">
                                        <span class="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="expense in expenses.data" :key="expense.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ expense.number }}</div>
                                        <div v-if="expense.supplier_document_number" class="text-sm text-gray-500">{{ expense.supplier_document_number }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div v-if="expense.supplier" class="text-sm text-gray-900">{{ expense.supplier.name }}</div>
                                        <div v-else class="text-sm text-gray-400">Sin proveedor</div>
                                        <div v-if="expense.category" class="text-xs text-gray-500">{{ expense.category }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ formatDate(expense.issue_date) }}</div>
                                        <div v-if="expense.due_date" class="text-sm" :class="isOverdue(expense.due_date, expense.status) ? 'text-red-600' : 'text-gray-500'">
                                            Vence: {{ formatDate(expense.due_date) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getDocumentTypeClass(expense.document_type)">
                                            {{ getDocumentTypeLabel(expense.document_type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        {{ formatCurrency(expense.total_amount) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="expense.balance > 0 ? 'text-red-600 font-medium' : 'text-gray-500'">
                                        {{ formatCurrency(expense.balance) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span :class="getStatusClass(expense.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                            {{ getStatusLabel(expense.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <Link
                                                :href="route('expenses.show', expense.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Ver
                                            </Link>
                                            <Link
                                                :href="route('expenses.edit', expense.id)"
                                                class="text-yellow-600 hover:text-yellow-900"
                                            >
                                                Editar
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="expenses.data && expenses.data.length > 0 && expenses.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <p class="text-sm text-gray-700">
                                    Mostrando {{ expenses.from }} a {{ expenses.to }} de {{ expenses.total }} resultados
                                </p>
                            </div>
                            <Pagination :links="expenses.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/UI/Pagination.vue';

const props = defineProps({
    expenses: Object,
    statistics: Object,
    suppliers: Array,
    categories: Array,
    filters: Object
});

const form = ref({
    search: props.filters.search || '',
    supplier_id: props.filters.supplier_id || '',
    document_type: props.filters.document_type || '',
    status: props.filters.status || '',
    category: props.filters.category || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || ''
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

const isOverdue = (dueDate, status) => {
    return status === 'pending' && new Date(dueDate) < new Date();
};

const getDocumentTypeLabel = (type) => {
    const labels = {
        'invoice': 'Factura',
        'receipt': 'Boleta',
        'expense_note': 'N. Gasto',
        'petty_cash': 'C. Chica',
        'bank_charge': 'C. Bancario',
        'other': 'Otro'
    };
    return labels[type] || type;
};

const getDocumentTypeClass = (type) => {
    const classes = {
        'invoice': 'bg-blue-100 text-blue-800',
        'receipt': 'bg-green-100 text-green-800',
        'expense_note': 'bg-yellow-100 text-yellow-800',
        'petty_cash': 'bg-purple-100 text-purple-800',
        'bank_charge': 'bg-red-100 text-red-800',
        'other': 'bg-gray-100 text-gray-800'
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const getStatusLabel = (status) => {
    const labels = {
        'draft': 'Borrador',
        'pending': 'Pendiente',
        'paid': 'Pagado',
        'cancelled': 'Cancelado'
    };
    return labels[status] || status;
};

const getStatusClass = (status) => {
    const classes = {
        'draft': 'bg-gray-100 text-gray-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'paid': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const applyFilters = () => {
    router.get(route('expenses.index'), form.value);
};

const clearFilters = () => {
    form.value = {
        search: '',
        supplier_id: '',
        document_type: '',
        status: '',
        category: '',
        date_from: '',
        date_to: ''
    };
    router.get(route('expenses.index'));
};
</script>