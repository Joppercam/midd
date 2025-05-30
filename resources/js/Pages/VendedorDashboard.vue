<template>
    <AuthenticatedLayout title="Dashboard de Ventas">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Resumen de Ventas Personal -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Mi Desempeño</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Ventas del Mes -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Mis Ventas del Mes</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ formatCurrency(kpis.ventasMes.total) }}
                                    </p>
                                    <p class="text-sm mt-1" :class="kpis.ventasMes.porcentajeMeta >= 100 ? 'text-green-600' : 'text-yellow-600'">
                                        {{ kpis.ventasMes.porcentajeMeta }}% de la meta
                                    </p>
                                </div>
                                <div class="text-blue-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Cantidad de Ventas -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Ventas Realizadas</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ kpis.cantidadVentas.mes }}</p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Hoy: {{ kpis.cantidadVentas.hoy }}
                                    </p>
                                </div>
                                <div class="text-green-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Promedio -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Ticket Promedio</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ formatCurrency(kpis.ticketPromedio.actual) }}
                                    </p>
                                    <p class="text-sm mt-1" :class="kpis.ticketPromedio.variacion >= 0 ? 'text-green-600' : 'text-red-600'">
                                        <span v-if="kpis.ticketPromedio.variacion >= 0">↑</span>
                                        <span v-else>↓</span>
                                        {{ Math.abs(kpis.ticketPromedio.variacion) }}% vs mes anterior
                                    </p>
                                </div>
                                <div class="text-purple-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Comisiones -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Comisiones Ganadas</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ formatCurrency(kpis.comisiones.total) }}
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Por cobrar: {{ formatCurrency(kpis.comisiones.pendiente) }}
                                    </p>
                                </div>
                                <div class="text-yellow-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos de Desempeño -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Progreso de Meta Mensual -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Progreso de Meta Mensual</h3>
                        <canvas ref="progresoMetaChart" height="300"></canvas>
                    </div>

                    <!-- Ventas por Día de la Semana -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Mis Ventas por Día</h3>
                        <canvas ref="ventasPorDiaChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Información de Clientes y Productos -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Mis Mejores Clientes -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Mis Mejores Clientes</h3>
                        <div class="space-y-3">
                            <div v-for="cliente in topClientes" :key="cliente.id" class="flex justify-between items-center">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ cliente.nombre }}</p>
                                    <p class="text-xs text-gray-500">{{ cliente.cantidadCompras }} compras</p>
                                </div>
                                <span class="text-sm font-semibold">{{ formatCurrency(cliente.total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Productos Más Vendidos -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Productos Estrella</h3>
                        <div class="space-y-3">
                            <div v-for="producto in topProductos" :key="producto.id" class="flex justify-between items-center">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ producto.nombre }}</p>
                                    <p class="text-xs text-gray-500">{{ producto.cantidad }} unidades</p>
                                </div>
                                <span class="text-sm font-semibold">{{ formatCurrency(producto.total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actividades Pendientes -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Mis Pendientes</h3>
                        <div class="space-y-3">
                            <div v-for="actividad in actividadesPendientes" :key="actividad.id" 
                                 class="p-3 rounded-lg cursor-pointer hover:bg-gray-50"
                                 :class="{
                                     'bg-red-50': actividad.prioridad === 'alta',
                                     'bg-yellow-50': actividad.prioridad === 'media',
                                     'bg-blue-50': actividad.prioridad === 'baja'
                                 }">
                                <p class="text-sm font-medium text-gray-900">{{ actividad.titulo }}</p>
                                <p class="text-xs text-gray-600 mt-1">{{ actividad.descripcion }}</p>
                                <p class="text-xs text-gray-500 mt-1">Vence: {{ formatDate(actividad.vencimiento) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Últimas Ventas -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Mis Últimas Ventas</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Productos</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="venta in ultimasVentas" :key="venta.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(venta.fecha) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ venta.cliente }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <span class="text-gray-900">{{ venta.cantidadItems }}</span> items
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        {{ formatCurrency(venta.total) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                              :class="{
                                                  'bg-green-100 text-green-800': venta.estado === 'pagado',
                                                  'bg-yellow-100 text-yellow-800': venta.estado === 'pendiente',
                                                  'bg-red-100 text-red-800': venta.estado === 'vencido'
                                              }">
                                            {{ venta.estado }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <button @click="verVenta(venta.id)" class="text-indigo-600 hover:text-indigo-900">
                                            Ver detalle
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Herramientas Rápidas -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Acceso Rápido</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <button @click="nuevaVenta" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span class="text-sm text-gray-700">Nueva Venta</span>
                        </button>
                        <button @click="verCatalogo" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span class="text-sm text-gray-700">Catálogo</span>
                        </button>
                        <button @click="verClientes" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Mis Clientes</span>
                        </button>
                        <button @click="verComisiones" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Mis Comisiones</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { Chart, registerables } from 'chart.js';
import { router } from '@inertiajs/vue3';

Chart.register(...registerables);

const props = defineProps({
    kpis: Object,
    chartData: Object,
    topClientes: Array,
    topProductos: Array,
    actividadesPendientes: Array,
    ultimasVentas: Array
});

const progresoMetaChart = ref(null);
const ventasPorDiaChart = ref(null);
let progresoMetaChartInstance = null;
let ventasPorDiaChartInstance = null;

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

const createProgresoMetaChart = () => {
    if (progresoMetaChartInstance) {
        progresoMetaChartInstance.destroy();
    }

    const ctx = progresoMetaChart.value.getContext('2d');
    progresoMetaChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Vendido', 'Por Vender'],
            datasets: [{
                data: [props.chartData.progresoMeta.vendido, props.chartData.progresoMeta.porVender],
                backgroundColor: [
                    'rgba(34, 197, 94, 0.5)',
                    'rgba(229, 231, 235, 0.5)'
                ],
                borderColor: [
                    'rgb(34, 197, 94)',
                    'rgb(229, 231, 235)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = formatCurrency(context.parsed);
                            const percentage = ((context.parsed / (props.chartData.progresoMeta.vendido + props.chartData.progresoMeta.porVender)) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
};

const createVentasPorDiaChart = () => {
    if (ventasPorDiaChartInstance) {
        ventasPorDiaChartInstance.destroy();
    }

    const ctx = ventasPorDiaChart.value.getContext('2d');
    ventasPorDiaChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: props.chartData.ventasPorDia.labels,
            datasets: [{
                label: 'Ventas',
                data: props.chartData.ventasPorDia.valores,
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1
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
};

const verVenta = (id) => {
    router.visit(`/invoices/${id}`);
};

const nuevaVenta = () => {
    router.visit('/invoices/create');
};

const verCatalogo = () => {
    router.visit('/products');
};

const verClientes = () => {
    router.visit('/customers');
};

const verComisiones = () => {
    router.visit('/reports/comisiones');
};

onMounted(() => {
    createProgresoMetaChart();
    createVentasPorDiaChart();

    // Auto-actualización cada 5 minutos
    const refreshInterval = setInterval(() => {
        window.location.reload();
    }, 300000);

    // Limpiar el intervalo cuando el componente se desmonte
    onUnmounted(() => {
        clearInterval(refreshInterval);
    });
});

onUnmounted(() => {
    if (progresoMetaChartInstance) {
        progresoMetaChartInstance.destroy();
    }
    if (ventasPorDiaChartInstance) {
        ventasPorDiaChartInstance.destroy();
    }
});
</script>