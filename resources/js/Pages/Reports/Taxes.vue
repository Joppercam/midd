<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Reporte de Impuestos
                </h2>
                <Link
                    :href="route('reports.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Volver a reportes
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filtros -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700">Fecha Inicio</label>
                                <input
                                    type="date"
                                    id="start_date"
                                    v-model="form.start_date"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    required
                                />
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700">Fecha Fin</label>
                                <input
                                    type="date"
                                    id="end_date"
                                    v-model="form.end_date"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    required
                                />
                            </div>
                            <div class="flex items-end space-x-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
                                >
                                    Aplicar Filtros
                                </button>
                                <button
                                    type="button"
                                    @click="exportPDF"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    PDF
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Resumen IVA -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">IVA Débito Fiscal</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(sales_tax.net) }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                IVA por ventas
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">IVA Crédito Fiscal</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(purchase_tax.total) }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                IVA por compras
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Balance IVA</div>
                            <div class="mt-1 text-3xl font-semibold" :class="vat_balance > 0 ? 'text-red-600' : 'text-green-600'">
                                {{ formatCurrency(Math.abs(vat_balance)) }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                {{ vat_balance > 0 ? 'A pagar' : 'A favor' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle IVA Débito -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Detalle IVA Débito Fiscal</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo Documento
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cantidad
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Neto
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            IVA
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="doc in sales_tax.documents" :key="doc.document_type">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ getDocumentTypeLabel(doc.document_type) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ doc.count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ formatCurrency(doc.net_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ formatCurrency(doc.tax_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ formatCurrency(doc.total_amount) }}
                                        </td>
                                    </tr>
                                    <tr v-if="sales_tax.credit_notes" class="bg-red-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                            Notas de Crédito (-)
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ sales_tax.credit_notes.count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            -{{ formatCurrency(sales_tax.credit_notes.net_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium text-right">
                                            -{{ formatCurrency(sales_tax.credit_notes.tax_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            -{{ formatCurrency(sales_tax.credit_notes.total_amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-900">
                                            Total IVA Débito Fiscal
                                        </td>
                                        <td class="px-6 py-4 text-sm font-bold text-gray-900 text-right">
                                            {{ formatCurrency(sales_tax.net) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Detalle Mensual -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Detalle Mensual</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Período
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Documentos
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Neto
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            IVA
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template v-for="(documents, period) in monthly_detail" :key="period">
                                        <tr v-for="(doc, index) in documents" :key="`${period}-${doc.document_type}`" :class="index === 0 ? 'border-t-2 border-gray-300' : ''">
                                            <td v-if="index === 0" :rowspan="documents.length" class="px-6 py-4 text-sm font-medium text-gray-900">
                                                {{ formatPeriod(period) }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                {{ getDocumentTypeLabel(doc.document_type) }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 text-right">
                                                {{ doc.count }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 text-right">
                                                {{ formatCurrency(doc.net_amount) }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 text-right">
                                                {{ doc.document_type === 'credit_note' ? '-' : '' }}{{ formatCurrency(doc.tax_amount) }}
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Nota informativa -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                <strong>Nota:</strong> El IVA Crédito Fiscal se calculará automáticamente cuando se implemente el módulo de gastos y compras.
                            </p>
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

const props = defineProps({
    period: Object,
    sales_tax: Object,
    purchase_tax: Object,
    vat_balance: Number,
    monthly_detail: Object,
    filters: Object
});

const form = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const getDocumentTypeLabel = (type) => {
    const labels = {
        'invoice': 'Facturas',
        'credit_note': 'Notas de Crédito',
        'debit_note': 'Notas de Débito',
        'receipt': 'Boletas'
    };
    return labels[type] || type;
};

const formatPeriod = (period) => {
    const [year, month] = period.split('-');
    const date = new Date(year, month - 1);
    return date.toLocaleDateString('es-CL', { month: 'long', year: 'numeric' });
};

const applyFilters = () => {
    router.get(route('reports.taxes'), form.value);
};

const exportPDF = () => {
    window.open(route('reports.taxes', { ...form.value, format: 'pdf' }), '_blank');
};
</script>