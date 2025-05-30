<template>
    <AuthenticatedLayout title="Dashboard Gerencial">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Resumen Ejecutivo -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Resumen Ejecutivo</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- KPI: Ventas del Mes -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Ventas del Mes</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ formatCurrency(kpis.ventas.actual) }}
                                    </p>
                                    <p class="text-sm mt-1" :class="kpis.ventas.crecimiento >= 0 ? 'text-green-600' : 'text-red-600'">
                                        <span v-if="kpis.ventas.crecimiento >= 0">↑</span>
                                        <span v-else>↓</span>
                                        {{ Math.abs(kpis.ventas.crecimiento) }}% vs mes anterior
                                    </p>
                                </div>
                                <div class="text-blue-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- KPI: Margen de Ganancia -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Margen de Ganancia</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ kpis.margen.porcentaje }}%</p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        {{ formatCurrency(kpis.margen.monto) }} en ganancias
                                    </p>
                                </div>
                                <div class="text-green-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- KPI: Clientes Activos -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Clientes Activos</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ kpis.clientes.activos }}</p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        +{{ kpis.clientes.nuevos }} este mes
                                    </p>
                                </div>
                                <div class="text-purple-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- KPI: Eficiencia Operativa -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Eficiencia Operativa</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ kpis.eficiencia.porcentaje }}%</p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Gastos: {{ kpis.eficiencia.gastosPorcentaje }}% de ventas
                                    </p>
                                </div>
                                <div class="text-yellow-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos Principales -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Tendencia de Ventas vs Objetivos -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Ventas vs Objetivos</h3>
                        <canvas ref="ventasObjetivosChart" height="300"></canvas>
                    </div>

                    <!-- Análisis de Rentabilidad por Producto -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Rentabilidad por Categoría</h3>
                        <canvas ref="rentabilidadChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Indicadores de Gestión -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Ciclo de Conversión de Efectivo -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Ciclo de Conversión</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Días de Inventario</span>
                                <span class="font-semibold">{{ indicadores.cicloConversion.diasInventario }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Días de Cobro</span>
                                <span class="font-semibold">{{ indicadores.cicloConversion.diasCobro }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Días de Pago</span>
                                <span class="font-semibold">{{ indicadores.cicloConversion.diasPago }}</span>
                            </div>
                            <div class="pt-3 border-t">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-semibold text-gray-700">Ciclo Total</span>
                                    <span class="text-lg font-bold" :class="indicadores.cicloConversion.total < 30 ? 'text-green-600' : 'text-yellow-600'">
                                        {{ indicadores.cicloConversion.total }} días
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top 5 Clientes -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Clientes</h3>
                        <div class="space-y-3">
                            <div v-for="cliente in topClientes" :key="cliente.id" class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 truncate flex-1">{{ cliente.nombre }}</span>
                                <span class="font-semibold ml-2">{{ formatCurrency(cliente.ventas) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Alertas Importantes -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Alertas Estratégicas</h3>
                        <div class="space-y-3">
                            <div v-for="alerta in alertas" :key="alerta.id" 
                                 class="p-3 rounded-lg" 
                                 :class="{
                                     'bg-red-50 text-red-800': alerta.tipo === 'critica',
                                     'bg-yellow-50 text-yellow-800': alerta.tipo === 'advertencia',
                                     'bg-blue-50 text-blue-800': alerta.tipo === 'info'
                                 }">
                                <p class="text-sm font-medium">{{ alerta.mensaje }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Análisis Comparativo -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Análisis Comparativo (Últimos 6 meses)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Métrica</th>
                                    <th v-for="mes in analisisComparativo.meses" :key="mes" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        {{ mes }}
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Promedio</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="metrica in analisisComparativo.metricas" :key="metrica.nombre">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ metrica.nombre }}
                                    </td>
                                    <td v-for="(valor, index) in metrica.valores" :key="index" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                        {{ metrica.tipo === 'moneda' ? formatCurrency(valor) : valor + (metrica.tipo === 'porcentaje' ? '%' : '') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">
                                        {{ metrica.tipo === 'moneda' ? formatCurrency(metrica.promedio) : metrica.promedio + (metrica.tipo === 'porcentaje' ? '%' : '') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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

Chart.register(...registerables);

const props = defineProps({
    kpis: Object,
    chartData: Object,
    indicadores: Object,
    topClientes: Array,
    alertas: Array,
    analisisComparativo: Object
});

const ventasObjetivosChart = ref(null);
const rentabilidadChart = ref(null);
let ventasObjetivosChartInstance = null;
let rentabilidadChartInstance = null;

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};

const createVentasObjetivosChart = () => {
    if (ventasObjetivosChartInstance) {
        ventasObjetivosChartInstance.destroy();
    }

    const ctx = ventasObjetivosChart.value.getContext('2d');
    ventasObjetivosChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.chartData.ventasObjetivos.labels,
            datasets: [
                {
                    label: 'Ventas Reales',
                    data: props.chartData.ventasObjetivos.ventas,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                },
                {
                    label: 'Objetivo',
                    data: props.chartData.ventasObjetivos.objetivos,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderDash: [5, 5],
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
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

const createRentabilidadChart = () => {
    if (rentabilidadChartInstance) {
        rentabilidadChartInstance.destroy();
    }

    const ctx = rentabilidadChart.value.getContext('2d');
    rentabilidadChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: props.chartData.rentabilidad.labels,
            datasets: [
                {
                    label: 'Ventas',
                    data: props.chartData.rentabilidad.ventas,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                },
                {
                    label: 'Margen',
                    data: props.chartData.rentabilidad.margen,
                    backgroundColor: 'rgba(34, 197, 94, 0.5)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
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

onMounted(() => {
    createVentasObjetivosChart();
    createRentabilidadChart();

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
    if (ventasObjetivosChartInstance) {
        ventasObjetivosChartInstance.destroy();
    }
    if (rentabilidadChartInstance) {
        rentabilidadChartInstance.destroy();
    }
});
</script>