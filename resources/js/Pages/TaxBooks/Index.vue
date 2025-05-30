<template>
    <Head title="Libro de Compras y Ventas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Libro de Compras y Ventas
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Period Selector -->
                <Card class="mb-6">
                    <template #content>
                        <div class="flex flex-wrap items-end gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Año
                                </label>
                                <select
                                    v-model="selectedYear"
                                    @change="updatePeriod"
                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option v-for="year in availableYears" :key="year" :value="year">
                                        {{ year }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Mes
                                </label>
                                <select
                                    v-model="selectedMonth"
                                    @change="updatePeriod"
                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option v-for="(month, index) in months" :key="index" :value="index + 1">
                                        {{ month }}
                                    </option>
                                </select>
                            </div>
                            <PrimaryButton @click="updatePeriod">
                                Ver Período
                            </PrimaryButton>
                        </div>
                    </template>
                </Card>

                <!-- Tax Summary -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <Stats
                        title="IVA Ventas"
                        :value="formatCurrency(taxSummary.sales_tax)"
                        trend="Débito Fiscal"
                        icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        color="blue"
                    />
                    <Stats
                        title="IVA Compras"
                        :value="formatCurrency(taxSummary.purchase_tax)"
                        trend="Crédito Fiscal"
                        icon="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                        color="green"
                    />
                    <Stats
                        title="Balance IVA"
                        :value="formatCurrency(Math.abs(taxSummary.balance))"
                        :trend="taxSummary.balance > 0 ? 'A pagar' : 'A favor'"
                        icon="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                        :color="taxSummary.balance > 0 ? 'red' : 'green'"
                    />
                    <Stats
                        title="Período"
                        :value="`${months[selectedMonth - 1]} ${selectedYear}`"
                        trend="Actual"
                        icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        color="gray"
                    />
                </div>

                <!-- Books Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Sales Book -->
                    <Card>
                        <template #header>
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900">Libro de Ventas</h3>
                                <Badge v-if="salesBook" :type="getStatusType(salesBook.status)">
                                    {{ salesBook.status_label }}
                                </Badge>
                            </div>
                        </template>
                        <template #content>
                            <div v-if="salesBook" class="space-y-4">
                                <div class="border-l-4 border-blue-500 pl-4">
                                    <dl class="grid grid-cols-2 gap-2 text-sm">
                                        <dt class="text-gray-500">Documentos:</dt>
                                        <dd class="font-medium">{{ salesBook.total_documents }}</dd>
                                        <dt class="text-gray-500">Neto:</dt>
                                        <dd class="font-medium">${{ formatNumber(salesBook.total_net) }}</dd>
                                        <dt class="text-gray-500">IVA:</dt>
                                        <dd class="font-medium">${{ formatNumber(salesBook.total_tax) }}</dd>
                                        <dt class="text-gray-500">Total:</dt>
                                        <dd class="font-medium">${{ formatNumber(salesBook.total_amount) }}</dd>
                                    </dl>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        :href="route('tax-books.sales.show', salesBook.id)"
                                        class="text-sm text-blue-600 hover:text-blue-800"
                                    >
                                        Ver detalle
                                    </Link>
                                    <button
                                        v-if="salesBook.status === 'final' && can.export"
                                        @click="exportSalesBook('excel')"
                                        class="text-sm text-green-600 hover:text-green-800"
                                    >
                                        Exportar Excel
                                    </button>
                                    <button
                                        v-if="salesBook.status === 'final' && can.export"
                                        @click="exportSalesBook('pdf')"
                                        class="text-sm text-red-600 hover:text-red-800"
                                    >
                                        Exportar PDF
                                    </button>
                                </div>
                            </div>
                            <div v-else class="text-center py-8">
                                <p class="text-gray-500 mb-4">No hay libro de ventas para este período</p>
                                <PrimaryButton v-if="can.generate" @click="generateSalesBook">
                                    Generar Libro
                                </PrimaryButton>
                            </div>
                        </template>
                    </Card>

                    <!-- Purchase Book -->
                    <Card>
                        <template #header>
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900">Libro de Compras</h3>
                                <Badge v-if="purchaseBook" :type="getStatusType(purchaseBook.status)">
                                    {{ purchaseBook.status_label }}
                                </Badge>
                            </div>
                        </template>
                        <template #content>
                            <div v-if="purchaseBook" class="space-y-4">
                                <div class="border-l-4 border-green-500 pl-4">
                                    <dl class="grid grid-cols-2 gap-2 text-sm">
                                        <dt class="text-gray-500">Documentos:</dt>
                                        <dd class="font-medium">{{ purchaseBook.total_documents }}</dd>
                                        <dt class="text-gray-500">Neto:</dt>
                                        <dd class="font-medium">${{ formatNumber(purchaseBook.total_net) }}</dd>
                                        <dt class="text-gray-500">IVA:</dt>
                                        <dd class="font-medium">${{ formatNumber(purchaseBook.total_tax) }}</dd>
                                        <dt class="text-gray-500">Total:</dt>
                                        <dd class="font-medium">${{ formatNumber(purchaseBook.total_amount) }}</dd>
                                    </dl>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        :href="route('tax-books.purchase.show', purchaseBook.id)"
                                        class="text-sm text-blue-600 hover:text-blue-800"
                                    >
                                        Ver detalle
                                    </Link>
                                    <button
                                        v-if="purchaseBook.status === 'final' && can.export"
                                        @click="exportPurchaseBook('excel')"
                                        class="text-sm text-green-600 hover:text-green-800"
                                    >
                                        Exportar Excel
                                    </button>
                                    <button
                                        v-if="purchaseBook.status === 'final' && can.export"
                                        @click="exportPurchaseBook('pdf')"
                                        class="text-sm text-red-600 hover:text-red-800"
                                    >
                                        Exportar PDF
                                    </button>
                                </div>
                            </div>
                            <div v-else class="text-center py-8">
                                <p class="text-gray-500 mb-4">No hay libro de compras para este período</p>
                                <PrimaryButton v-if="can.generate" @click="generatePurchaseBook">
                                    Generar Libro
                                </PrimaryButton>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Instructions -->
                <Card class="mt-6">
                    <template #content>
                        <div class="text-sm text-gray-600">
                            <h4 class="font-medium text-gray-900 mb-2">Instrucciones:</h4>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Los libros se generan automáticamente con los documentos del período seleccionado</li>
                                <li>Una vez generado, puede revisar el detalle antes de finalizarlo</li>
                                <li>Los libros finalizados no pueden ser modificados</li>
                                <li>Puede exportar los libros finalizados en formato Excel o PDF</li>
                                <li>El formato es compatible con los requerimientos del SII</li>
                            </ul>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Stats from '@/Components/UI/Stats.vue';
import Badge from '@/Components/UI/Badge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    salesBook: Object,
    purchaseBook: Object,
    taxSummary: Object,
    currentYear: Number,
    currentMonth: Number,
    availablePeriods: Array,
    can: Object,
});

const selectedYear = ref(props.currentYear);
const selectedMonth = ref(props.currentMonth);

const months = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];

const availableYears = [2024, 2025, 2026];

const updatePeriod = () => {
    router.get(route('tax-books.index'), {
        year: selectedYear.value,
        month: selectedMonth.value,
    });
};

const generateSalesBook = () => {
    if (confirm('¿Generar el libro de ventas para este período?')) {
        router.post(route('tax-books.sales.generate'), {
            year: selectedYear.value,
            month: selectedMonth.value,
        });
    }
};

const generatePurchaseBook = () => {
    if (confirm('¿Generar el libro de compras para este período?')) {
        router.post(route('tax-books.purchase.generate'), {
            year: selectedYear.value,
            month: selectedMonth.value,
        });
    }
};

const exportSalesBook = (format) => {
    const url = format === 'excel' 
        ? route('tax-books.sales.export.excel', props.salesBook.id)
        : route('tax-books.sales.export.pdf', props.salesBook.id);
    window.open(url, '_blank');
};

const exportPurchaseBook = (format) => {
    const url = format === 'excel' 
        ? route('tax-books.purchase.export.excel', props.purchaseBook.id)
        : route('tax-books.purchase.export.pdf', props.purchaseBook.id);
    window.open(url, '_blank');
};

const getStatusType = (status) => {
    const types = {
        draft: 'gray',
        final: 'green',
        sent: 'blue',
    };
    return types[status] || 'gray';
};

const formatCurrency = (value) => {
    return '$' + new Intl.NumberFormat('es-CL').format(value || 0);
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('es-CL').format(value || 0);
};
</script>