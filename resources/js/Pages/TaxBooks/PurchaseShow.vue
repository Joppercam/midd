<template>
    <Head :title="`Libro de Compras - ${book.period_name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Libro de Compras - {{ book.period_name }}
                </h2>
                <Link :href="route('tax-books.index')" class="text-sm text-gray-600 hover:text-gray-900">
                    Volver
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Actions -->
                <Card class="mb-6">
                    <template #content>
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <Badge :type="getStatusType(book.status)">
                                    {{ book.status_label }}
                                </Badge>
                                <span class="text-sm text-gray-500">
                                    Generado: {{ formatDate(book.generated_at) }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <PrimaryButton
                                    v-if="can.finalize && book.status === 'draft'"
                                    @click="finalizeBook"
                                >
                                    Finalizar Libro
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="can.export && book.status === 'final'"
                                    @click="exportBook('excel')"
                                >
                                    Exportar Excel
                                </SecondaryButton>
                                <SecondaryButton
                                    v-if="can.export && book.status === 'final'"
                                    @click="exportBook('pdf')"
                                >
                                    Exportar PDF
                                </SecondaryButton>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Summary -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                    <Card>
                        <template #content>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Documentos</p>
                                <p class="text-2xl font-bold">{{ book.total_documents }}</p>
                            </div>
                        </template>
                    </Card>
                    <Card>
                        <template #content>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Exento</p>
                                <p class="text-xl font-bold">${{ formatNumber(book.total_exempt) }}</p>
                            </div>
                        </template>
                    </Card>
                    <Card>
                        <template #content>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Neto</p>
                                <p class="text-xl font-bold">${{ formatNumber(book.total_net) }}</p>
                            </div>
                        </template>
                    </Card>
                    <Card>
                        <template #content>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">IVA</p>
                                <p class="text-xl font-bold">${{ formatNumber(book.total_tax) }}</p>
                            </div>
                        </template>
                    </Card>
                    <Card>
                        <template #content>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Total</p>
                                <p class="text-xl font-bold">${{ formatNumber(book.total_amount) }}</p>
                            </div>
                        </template>
                    </Card>
                    <Card>
                        <template #content>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Retenciones</p>
                                <p class="text-xl font-bold">${{ formatNumber(book.total_withholding) }}</p>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Summary by Document Type -->
                <Card v-if="book.summary && book.summary.length > 0" class="mb-6">
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">Resumen por Tipo de Documento</h3>
                    </template>
                    <template #content>
                        <Table>
                            <template #header>
                                <tr>
                                    <th>Tipo de Documento</th>
                                    <th class="text-right">Cantidad</th>
                                    <th class="text-right">Exento</th>
                                    <th class="text-right">Neto</th>
                                    <th class="text-right">IVA</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </template>
                            <template #body>
                                <tr v-for="item in book.summary" :key="item.document_type">
                                    <td>{{ getDocumentTypeLabel(item.document_type) }}</td>
                                    <td class="text-right">{{ item.count }}</td>
                                    <td class="text-right">${{ formatNumber(item.exempt) }}</td>
                                    <td class="text-right">${{ formatNumber(item.net) }}</td>
                                    <td class="text-right">${{ formatNumber(item.tax) }}</td>
                                    <td class="text-right font-medium">${{ formatNumber(item.total) }}</td>
                                </tr>
                            </template>
                        </Table>
                    </template>
                </Card>

                <!-- Entries -->
                <Card>
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">Detalle de Documentos</h3>
                    </template>
                    <template #content>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Fecha
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            N° Doc
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Proveedor
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Descripción
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Exento
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Neto
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            IVA
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="entry in book.entries" :key="entry.id">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            {{ formatDate(entry.document_date) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            {{ entry.document_type_label }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                            {{ entry.document_number }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <div>{{ entry.supplier_name }}</div>
                                            <div class="text-gray-500">{{ entry.formatted_rut }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ entry.description || '-' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                            ${{ formatNumber(entry.exempt_amount) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                            ${{ formatNumber(entry.net_amount) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                            ${{ formatNumber(entry.tax_amount) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium">
                                            ${{ formatNumber(entry.total_amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-right font-medium">
                                            Totales:
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium">
                                            ${{ formatNumber(book.total_exempt) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium">
                                            ${{ formatNumber(book.total_net) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium">
                                            ${{ formatNumber(book.total_tax) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold">
                                            ${{ formatNumber(book.total_amount) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Table from '@/Components/UI/Table.vue';
import Badge from '@/Components/UI/Badge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    book: Object,
    can: Object,
});

const finalizeBook = () => {
    if (confirm('¿Finalizar este libro? Una vez finalizado no podrá ser modificado.')) {
        router.post(route('tax-books.purchase.finalize', props.book.id));
    }
};

const exportBook = (format) => {
    const url = format === 'excel' 
        ? route('tax-books.purchase.export.excel', props.book.id)
        : route('tax-books.purchase.export.pdf', props.book.id);
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

const getDocumentTypeLabel = (type) => {
    const labels = {
        'invoice': 'Factura',
        'invoice_electronic': 'Factura Electrónica',
        'credit_note': 'Nota de Crédito',
        'debit_note': 'Nota de Débito',
        'receipt': 'Boleta',
        'fee': 'Honorarios',
        'import': 'Importación',
        'other': 'Otro',
    };
    return labels[type] || type;
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('es-CL');
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('es-CL').format(value || 0);
};
</script>