<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Reporte de Ventas
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
                        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                            <div>
                                <label for="group_by" class="block text-sm font-medium text-gray-700">Agrupar por</label>
                                <select
                                    id="group_by"
                                    v-model="form.group_by"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="day">Día</option>
                                    <option value="week">Semana</option>
                                    <option value="month">Mes</option>
                                    <option value="year">Año</option>
                                </select>
                            </div>
                            <div class="flex items-end space-x-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
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

                <!-- Resumen -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Total Ventas</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(summary.total_amount) }}
                            </div>
                            <div class="mt-2 flex items-center text-sm">
                                <span 
                                    class="flex items-center"
                                    :class="growth_rate >= 0 ? 'text-green-600' : 'text-red-600'"
                                >
                                    <svg v-if="growth_rate >= 0" class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    {{ Math.abs(growth_rate) }}%
                                </span>
                                <span class="ml-2 text-gray-500">vs período anterior</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Documentos</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ summary.total_documents }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                {{ period.days }} días
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Venta Promedio</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(summary.average_sale) }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                por documento
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">IVA Total</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(summary.total_tax) }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                19% sobre {{ formatCurrency(summary.total_net) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de Ventas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Evolución de Ventas</h3>
                        <div class="h-64">
                            <canvas ref="salesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Top Productos -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Top 10 Productos</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Producto
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Cantidad
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="product in top_products" :key="product.sku">
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                <div>{{ product.name }}</div>
                                                <div class="text-xs text-gray-500">{{ product.sku }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                                {{ formatNumber(product.total_quantity) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                                {{ formatCurrency(product.total_revenue) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Top Clientes -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Top 10 Clientes</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Cliente
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Docs
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="customer in top_customers" :key="customer.id">
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                <div>{{ customer.name }}</div>
                                                <div class="text-xs text-gray-500">{{ customer.rut }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                                {{ customer.document_count }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                                {{ formatCurrency(customer.total_amount) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    period: Object,
    summary: Object,
    growth_rate: Number,
    sales_by_period: Array,
    top_products: Array,
    top_customers: Array,
    filters: Object
});

const salesChart = ref(null);
const form = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    group_by: props.filters.group_by
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const formatNumber = (number) => {
    return new Intl.NumberFormat('es-CL').format(number || 0);
};

const applyFilters = () => {
    router.get(route('reports.sales'), form.value);
};

const exportPDF = () => {
    window.open(route('reports.sales', { ...form.value, format: 'pdf' }), '_blank');
};

onMounted(() => {
    // Crear gráfico de ventas
    const ctx = salesChart.value.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.sales_by_period.map(item => {
                const date = new Date(item.period);
                if (props.filters.group_by === 'month') {
                    return date.toLocaleDateString('es-CL', { month: 'short', year: 'numeric' });
                } else if (props.filters.group_by === 'year') {
                    return date.getFullYear();
                } else {
                    return date.toLocaleDateString('es-CL');
                }
            }),
            datasets: [{
                label: 'Ventas',
                data: props.sales_by_period.map(item => item.total),
                borderColor: 'rgb(79, 70, 229)',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Ventas: ' + formatCurrency(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
});
</script>