<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Dashboard de Administración
                </h2>
                <div class="text-sm text-gray-500">
                    Última actualización: {{ lastUpdated }}
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- KPIs Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Revenue Card -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Ingresos del Mes</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ formatCurrency(kpis.revenue.current) }}
                                </p>
                                <div class="flex items-center mt-2">
                                    <svg v-if="kpis.revenue.growth >= 0" class="w-4 h-4 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 text-red-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                    </svg>
                                    <span :class="kpis.revenue.growth >= 0 ? 'text-green-600' : 'text-red-600'" class="text-sm font-semibold">
                                        {{ kpis.revenue.growth }}%
                                    </span>
                                    <span class="text-sm text-gray-500 ml-1">vs mes anterior</span>
                                </div>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            class="bg-indigo-600 h-2 rounded-full" 
                                            :style="{ width: `${Math.min((kpis.revenue.current / kpis.revenue.target) * 100, 100)}%` }"
                                        ></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ Math.round((kpis.revenue.current / kpis.revenue.target) * 100) }}% de la meta
                                    </p>
                                </div>
                            </div>
                            <div class="bg-indigo-100 rounded-full p-3">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Customers Card -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Clientes</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ kpis.customers.total }}
                                </p>
                                <div class="flex items-center mt-2 space-x-4">
                                    <div>
                                        <span class="text-sm font-semibold text-green-600">+{{ kpis.customers.new_this_month }}</span>
                                        <span class="text-xs text-gray-500 ml-1">nuevos</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-semibold text-blue-600">{{ kpis.customers.active }}</span>
                                        <span class="text-xs text-gray-500 ml-1">activos</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-green-100 rounded-full p-3">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices Card -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Facturas</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ kpis.invoices.total_month }}
                                </p>
                                <div class="flex items-center mt-2 space-x-4">
                                    <div>
                                        <span class="text-sm font-semibold text-yellow-600">{{ kpis.invoices.pending }}</span>
                                        <span class="text-xs text-gray-500 ml-1">pendientes</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-semibold text-red-600">{{ kpis.invoices.overdue }}</span>
                                        <span class="text-xs text-gray-500 ml-1">vencidas</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-blue-100 rounded-full p-3">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Flow Card -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Flujo de Caja</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ formatCurrency(kpis.cash_flow.balance) }}
                                </p>
                                <div class="mt-2 space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-green-600">Ingresos:</span>
                                        <span class="font-semibold">{{ formatCurrency(kpis.cash_flow.income) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-red-600">Gastos:</span>
                                        <span class="font-semibold">{{ formatCurrency(kpis.cash_flow.expenses) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-purple-100 rounded-full p-3">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Revenue Trend Chart -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Tendencia de Ingresos</h3>
                        <canvas ref="revenueTrendChart"></canvas>
                    </div>

                    <!-- Sales by Category -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Ventas por Categoría</h3>
                        <canvas ref="salesByCategoryChart"></canvas>
                    </div>

                    <!-- Payment Methods -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Métodos de Pago</h3>
                        <canvas ref="paymentMethodsChart"></canvas>
                    </div>

                    <!-- Hourly Activity -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Actividad por Hora</h3>
                        <canvas ref="hourlyActivityChart"></canvas>
                    </div>
                </div>

                <!-- Tables Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Top Products -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b">
                            <h3 class="text-lg font-medium text-gray-900">Productos Más Vendidos</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Producto
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cantidad
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Ingresos
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="product in charts.top_products" :key="product.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
                                                <div class="text-sm text-gray-500">{{ product.sku }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ product.quantity_sold }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                            {{ formatCurrency(product.revenue) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Customers -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b">
                            <h3 class="text-lg font-medium text-gray-900">Mejores Clientes</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cliente
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Facturas
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="customer in charts.top_customers" :key="customer.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ customer.name }}</div>
                                                <div class="text-sm text-gray-500">{{ customer.rut }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ customer.invoices_count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                            {{ formatCurrency(customer.total_spent) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Alerts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Low Stock Alert -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b bg-yellow-50">
                            <h3 class="text-lg font-medium text-gray-900 flex items-center">
                                <svg class="w-5 h-5 text-yellow-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Stock Bajo
                            </h3>
                        </div>
                        <div class="p-4 max-h-64 overflow-y-auto">
                            <div v-if="alerts.low_stock.length === 0" class="text-center py-4 text-gray-500">
                                No hay productos con stock bajo
                            </div>
                            <div v-else class="space-y-3">
                                <div v-for="product in alerts.low_stock" :key="product.id" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ product.name }}</p>
                                        <p class="text-xs text-gray-500">SKU: {{ product.sku }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-red-600">{{ product.current_stock }}</p>
                                        <p class="text-xs text-gray-500">Min: {{ product.minimum_stock }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Overdue Invoices Alert -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b bg-red-50">
                            <h3 class="text-lg font-medium text-gray-900 flex items-center">
                                <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Facturas Vencidas
                            </h3>
                        </div>
                        <div class="p-4 max-h-64 overflow-y-auto">
                            <div v-if="alerts.overdue_invoices.length === 0" class="text-center py-4 text-gray-500">
                                No hay facturas vencidas
                            </div>
                            <div v-else class="space-y-3">
                                <div v-for="invoice in alerts.overdue_invoices" :key="invoice.id" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ invoice.document_number }}</p>
                                        <p class="text-xs text-gray-500">{{ invoice.customer.name }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(invoice.total) }}</p>
                                        <p class="text-xs text-red-600">{{ invoice.days_overdue }} días vencida</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Health -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b" :class="getHealthHeaderClass()">
                            <h3 class="text-lg font-medium text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2" :class="getHealthIconClass()" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Estado del Sistema
                            </h3>
                        </div>
                        <div class="p-4">
                            <div class="space-y-3">
                                <div v-for="(check, key) in alerts.system_health.checks" :key="key" class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600 capitalize">{{ key }}</span>
                                    <span class="flex items-center">
                                        <span 
                                            class="w-2 h-2 rounded-full mr-2"
                                            :class="{
                                                'bg-green-500': check.status === 'ok',
                                                'bg-yellow-500': check.status === 'warning',
                                                'bg-red-500': check.status === 'error'
                                            }"
                                        ></span>
                                        <span class="text-xs text-gray-500">{{ check.message }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Actividad Reciente</h3>
                            <span class="text-sm text-gray-500">{{ activeUsers }} usuarios activos</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                <li v-for="(activity, index) in recentActivity" :key="activity.id">
                                    <div class="relative pb-8">
                                        <span v-if="index !== recentActivity.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white" :class="getActivityColor(activity.type)">
                                                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActivityIcon(activity.type)" />
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                <div>
                                                    <p class="text-sm text-gray-500">
                                                        <span class="font-medium text-gray-900">{{ activity.user?.name || 'Sistema' }}</span>
                                                        {{ activity.description }}
                                                    </p>
                                                </div>
                                                <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                    {{ formatRelativeTime(activity.created_at) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    kpis: Object,
    charts: Object,
    alerts: Object,
    recentActivity: Array,
    activeUsers: Number,
    lastUpdated: String,
});

// Chart refs
const revenueTrendChart = ref(null);
const salesByCategoryChart = ref(null);
const paymentMethodsChart = ref(null);
const hourlyActivityChart = ref(null);

let chartInstances = [];

onMounted(() => {
    initCharts();
    // Auto-refresh every 5 minutes
    const refreshInterval = setInterval(() => {
        window.location.reload();
    }, 300000);
    
    onUnmounted(() => {
        clearInterval(refreshInterval);
        chartInstances.forEach(chart => chart.destroy());
    });
});

const initCharts = () => {
    // Revenue Trend Chart
    const revenueTrendCtx = revenueTrendChart.value.getContext('2d');
    chartInstances.push(new Chart(revenueTrendCtx, {
        type: 'line',
        data: {
            labels: props.charts.revenue_trend.map(item => item.month),
            datasets: [{
                label: 'Ingresos',
                data: props.charts.revenue_trend.map(item => item.revenue),
                borderColor: 'rgb(79, 70, 229)',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                tension: 0.4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
    }));

    // Sales by Category Chart
    const salesByCategoryCtx = salesByCategoryChart.value.getContext('2d');
    chartInstances.push(new Chart(salesByCategoryCtx, {
        type: 'doughnut',
        data: {
            labels: props.charts.sales_by_category.map(item => item.name),
            datasets: [{
                data: props.charts.sales_by_category.map(item => item.total),
                backgroundColor: [
                    'rgba(79, 70, 229, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(251, 191, 36, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                ],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    }));

    // Payment Methods Chart
    const paymentMethodsCtx = paymentMethodsChart.value.getContext('2d');
    chartInstances.push(new Chart(paymentMethodsCtx, {
        type: 'bar',
        data: {
            labels: props.charts.payment_methods.map(item => item.method),
            datasets: [{
                label: 'Cantidad',
                data: props.charts.payment_methods.map(item => item.count),
                backgroundColor: 'rgba(79, 70, 229, 0.8)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    }));

    // Hourly Activity Chart
    const hourlyActivityCtx = hourlyActivityChart.value.getContext('2d');
    chartInstances.push(new Chart(hourlyActivityCtx, {
        type: 'bar',
        data: {
            labels: props.charts.hourly_activity.map(item => item.hour),
            datasets: [{
                label: 'Actividad',
                data: props.charts.hourly_activity.map(item => item.activity),
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    }));
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
    }).format(value);
};

const formatRelativeTime = (date) => {
    const now = new Date();
    const past = new Date(date);
    const diffInSeconds = Math.floor((now - past) / 1000);
    
    if (diffInSeconds < 60) return 'hace unos segundos';
    if (diffInSeconds < 3600) return `hace ${Math.floor(diffInSeconds / 60)} minutos`;
    if (diffInSeconds < 86400) return `hace ${Math.floor(diffInSeconds / 3600)} horas`;
    if (diffInSeconds < 604800) return `hace ${Math.floor(diffInSeconds / 86400)} días`;
    
    return past.toLocaleDateString('es-CL');
};

const getHealthHeaderClass = () => {
    const status = props.alerts.system_health.status;
    if (status === 'healthy') return 'bg-green-50';
    if (status === 'warning') return 'bg-yellow-50';
    return 'bg-red-50';
};

const getHealthIconClass = () => {
    const status = props.alerts.system_health.status;
    if (status === 'healthy') return 'text-green-500';
    if (status === 'warning') return 'text-yellow-500';
    return 'text-red-500';
};

const getActivityColor = (type) => {
    const colors = {
        login: 'bg-green-500',
        logout: 'bg-yellow-500',
        create: 'bg-blue-500',
        update: 'bg-indigo-500',
        delete: 'bg-red-500',
        view: 'bg-gray-500',
        export: 'bg-purple-500',
        import: 'bg-cyan-500',
        email: 'bg-pink-500',
        permission: 'bg-orange-500',
    };
    return colors[type] || 'bg-gray-500';
};

const getActivityIcon = (type) => {
    const icons = {
        login: 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',
        logout: 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
        create: 'M12 4v16m8-8H4',
        update: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        delete: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
        view: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
        export: 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        import: 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',
        email: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        permission: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
    };
    return icons[type] || 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
};
</script>

<style scoped>
canvas {
    max-height: 300px;
}
</style>