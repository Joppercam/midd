<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Reporte de Saldos de Clientes
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
                        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="all">Todos los clientes</option>
                                    <option value="with_balance">Con saldo pendiente</option>
                                    <option value="overdue">Con deuda vencida</option>
                                </select>
                            </div>
                            <div class="flex items-end space-x-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
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

                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Total Clientes</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ statistics.total_customers }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Saldo Total</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(statistics.total_balance) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Total Vencido</div>
                            <div class="mt-1 text-3xl font-semibold text-red-600">
                                {{ formatCurrency(statistics.total_overdue) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Con Saldo</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ statistics.customers_with_balance }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Morosos</div>
                            <div class="mt-1 text-3xl font-semibold text-red-600">
                                {{ statistics.customers_overdue }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Antigüedad de Saldos Global -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Antigüedad de Saldos Global</h3>
                        <div class="grid grid-cols-5 gap-4">
                            <div class="text-center">
                                <p class="text-sm text-gray-500 mb-1">Por Vencer</p>
                                <p class="text-2xl font-semibold text-green-600">
                                    {{ formatCurrency(aging_analysis.current) }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-500 mb-1">1-30 días</p>
                                <p class="text-2xl font-semibold text-yellow-600">
                                    {{ formatCurrency(aging_analysis.days_30) }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-500 mb-1">31-60 días</p>
                                <p class="text-2xl font-semibold text-orange-600">
                                    {{ formatCurrency(aging_analysis.days_60) }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-500 mb-1">61-90 días</p>
                                <p class="text-2xl font-semibold text-red-600">
                                    {{ formatCurrency(aging_analysis.days_90) }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-500 mb-1">Más de 90 días</p>
                                <p class="text-2xl font-semibold text-red-800">
                                    {{ formatCurrency(aging_analysis.over_90) }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Gráfico de barras -->
                        <div class="mt-6 h-64">
                            <canvas ref="agingChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Lista de Clientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Detalle por Cliente</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cliente
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Límite Crédito
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Saldo Total
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Monto Vencido
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            % Utilizado
                                        </th>
                                        <th class="relative px-4 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="customer in customers" :key="customer.id">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ customer.name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ customer.rut }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                            {{ formatCurrency(customer.credit_limit) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium text-right" :class="customer.balance > 0 ? 'text-gray-900' : 'text-gray-500'">
                                            {{ formatCurrency(customer.balance) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium text-right" :class="customer.overdue_amount > 0 ? 'text-red-600' : 'text-gray-500'">
                                            {{ formatCurrency(customer.overdue_amount) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span 
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                :class="getStatusClass(customer)"
                                            >
                                                {{ getStatusLabel(customer) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right">
                                            <div class="flex items-center justify-end">
                                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div 
                                                        class="h-2 rounded-full"
                                                        :class="getCreditUsageClass(customer)"
                                                        :style="`width: ${getCreditUsagePercent(customer)}%`"
                                                    ></div>
                                                </div>
                                                <span class="text-xs text-gray-600">
                                                    {{ getCreditUsagePercent(customer) }}%
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-medium">
                                            <Link
                                                :href="route('customers.show', customer.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Ver cliente
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
    customers: {
        type: Array,
        default: () => []
    },
    statistics: {
        type: Object,
        default: () => ({
            total_customers: 0,
            total_balance: 0,
            total_overdue: 0,
            customers_with_balance: 0,
            customers_overdue: 0
        })
    },
    aging_analysis: {
        type: Object,
        default: () => ({
            current: 0,
            days_30: 0,
            days_60: 0,
            days_90: 0,
            over_90: 0
        })
    },
    filters: {
        type: Object,
        default: () => ({ status: 'all' })
    }
});

const agingChart = ref(null);
const form = ref({
    status: props.filters.status || 'all'
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const getStatusLabel = (customer) => {
    if (customer.overdue_amount > 0) {
        return 'Moroso';
    } else if (customer.balance > 0) {
        return 'Con saldo';
    } else {
        return 'Al día';
    }
};

const getStatusClass = (customer) => {
    if (customer.overdue_amount > 0) {
        return 'bg-red-100 text-red-800';
    } else if (customer.balance > 0) {
        return 'bg-yellow-100 text-yellow-800';
    } else {
        return 'bg-green-100 text-green-800';
    }
};

const getCreditUsagePercent = (customer) => {
    if (!customer.credit_limit || customer.credit_limit === 0) {
        return 0;
    }
    return Math.min(100, Math.round((customer.balance / customer.credit_limit) * 100));
};

const getCreditUsageClass = (customer) => {
    const percent = getCreditUsagePercent(customer);
    if (percent >= 90) {
        return 'bg-red-600';
    } else if (percent >= 70) {
        return 'bg-yellow-600';
    } else {
        return 'bg-green-600';
    }
};

const applyFilters = () => {
    router.get(route('reports.customer-balance'), form.value);
};

const exportPDF = () => {
    window.open(route('reports.customer-balance', { ...form.value, format: 'pdf' }), '_blank');
};

onMounted(() => {
    // Crear gráfico de antigüedad solo si el elemento existe
    if (agingChart.value) {
        const ctx = agingChart.value.getContext('2d');
        new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Por Vencer', '1-30 días', '31-60 días', '61-90 días', 'Más de 90 días'],
            datasets: [{
                label: 'Monto',
                data: [
                    props.aging_analysis.current,
                    props.aging_analysis.days_30,
                    props.aging_analysis.days_60,
                    props.aging_analysis.days_90,
                    props.aging_analysis.over_90
                ],
                backgroundColor: [
                    'rgb(34, 197, 94)',
                    'rgb(234, 179, 8)',
                    'rgb(251, 146, 60)',
                    'rgb(239, 68, 68)',
                    'rgb(127, 29, 29)'
                ]
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
                            return formatCurrency(context.parsed.y);
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
    }
});
</script>