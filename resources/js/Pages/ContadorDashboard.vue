<template>
    <AuthenticatedLayout title="Dashboard Contable">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Resumen Financiero -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Resumen Financiero</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Balance General -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Balance General</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ formatCurrency(kpis.balance.activos) }}
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Pasivos: {{ formatCurrency(kpis.balance.pasivos) }}
                                    </p>
                                </div>
                                <div class="text-indigo-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Cuentas por Cobrar -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Cuentas por Cobrar</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ formatCurrency(kpis.cuentasPorCobrar.total) }}
                                    </p>
                                    <p class="text-sm mt-1" :class="kpis.cuentasPorCobrar.vencidas > 0 ? 'text-red-600' : 'text-green-600'">
                                        Vencidas: {{ formatCurrency(kpis.cuentasPorCobrar.vencidas) }}
                                    </p>
                                </div>
                                <div class="text-blue-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Cuentas por Pagar -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Cuentas por Pagar</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ formatCurrency(kpis.cuentasPorPagar.total) }}
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Próx. 7 días: {{ formatCurrency(kpis.cuentasPorPagar.proximaSemana) }}
                                    </p>
                                </div>
                                <div class="text-red-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Flujo de Caja -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Flujo de Caja Neto</p>
                                    <p class="text-2xl font-bold" :class="kpis.flujoCaja.neto >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(kpis.flujoCaja.neto) }}
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Saldo: {{ formatCurrency(kpis.flujoCaja.saldoActual) }}
                                    </p>
                                </div>
                                <div class="text-green-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos Contables -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Estado de Resultados -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Estado de Resultados (Mes Actual)</h3>
                        <canvas ref="estadoResultadosChart" height="300"></canvas>
                    </div>

                    <!-- Análisis de Gastos -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Distribución de Gastos</h3>
                        <canvas ref="gastosChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Información Tributaria y Documentos -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Resumen Tributario -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Resumen Tributario</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">IVA Débito</span>
                                <span class="font-semibold">{{ formatCurrency(tributario.ivaDebito) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">IVA Crédito</span>
                                <span class="font-semibold">{{ formatCurrency(tributario.ivaCredito) }}</span>
                            </div>
                            <div class="pt-3 border-t">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-semibold text-gray-700">IVA a Pagar</span>
                                    <span class="text-lg font-bold" :class="tributario.ivaPagar > 0 ? 'text-red-600' : 'text-green-600'">
                                        {{ formatCurrency(tributario.ivaPagar) }}
                                    </span>
                                </div>
                            </div>
                            <div class="pt-3">
                                <p class="text-xs text-gray-500">Próximo vencimiento: {{ tributario.proximoVencimiento }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Documentos Tributarios -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Documentos del Mes</h3>
                        <div class="space-y-3">
                            <div v-for="doc in documentosTributarios" :key="doc.tipo" class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">{{ doc.tipo }}</span>
                                <span class="font-semibold">{{ doc.cantidad }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Conciliación Bancaria -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Conciliación Bancaria</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Saldo Libro</span>
                                <span class="font-semibold">{{ formatCurrency(conciliacion.saldoLibro) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Saldo Banco</span>
                                <span class="font-semibold">{{ formatCurrency(conciliacion.saldoBanco) }}</span>
                            </div>
                            <div class="pt-3 border-t">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-semibold text-gray-700">Diferencia</span>
                                    <span class="text-lg font-bold" :class="conciliacion.diferencia === 0 ? 'text-green-600' : 'text-yellow-600'">
                                        {{ formatCurrency(conciliacion.diferencia) }}
                                    </span>
                                </div>
                            </div>
                            <div class="pt-3">
                                <p class="text-xs text-gray-500">Transacciones sin conciliar: {{ conciliacion.transaccionesPendientes }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Vencimientos -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Próximos Vencimientos</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Documento</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="vencimiento in vencimientos" :key="vencimiento.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(vencimiento.fecha) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                              :class="vencimiento.tipo === 'cobrar' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                            {{ vencimiento.tipo === 'cobrar' ? 'Por Cobrar' : 'Por Pagar' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ vencimiento.entidad }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ vencimiento.documento }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        {{ formatCurrency(vencimiento.monto) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                              :class="{
                                                  'bg-red-100 text-red-800': vencimiento.diasRestantes < 0,
                                                  'bg-yellow-100 text-yellow-800': vencimiento.diasRestantes >= 0 && vencimiento.diasRestantes <= 7,
                                                  'bg-gray-100 text-gray-800': vencimiento.diasRestantes > 7
                                              }">
                                            {{ vencimiento.diasRestantes < 0 ? 'Vencido' : vencimiento.diasRestantes + ' días' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <button @click="generarLibroVentas" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm text-gray-700">Libro de Ventas</span>
                        </button>
                        <button @click="generarLibroCompras" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Libro de Compras</span>
                        </button>
                        <button @click="conciliarBanco" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            <span class="text-sm text-gray-700">Conciliar Banco</span>
                        </button>
                        <button @click="declararImpuestos" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm text-gray-700">Declarar Impuestos</span>
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
    tributario: Object,
    documentosTributarios: Array,
    conciliacion: Object,
    vencimientos: Array
});

const estadoResultadosChart = ref(null);
const gastosChart = ref(null);
let estadoResultadosChartInstance = null;
let gastosChartInstance = null;

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

const createEstadoResultadosChart = () => {
    if (estadoResultadosChartInstance) {
        estadoResultadosChartInstance.destroy();
    }

    const ctx = estadoResultadosChart.value.getContext('2d');
    estadoResultadosChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: props.chartData.estadoResultados.labels,
            datasets: [{
                data: props.chartData.estadoResultados.valores,
                backgroundColor: [
                    'rgba(34, 197, 94, 0.5)',  // Ingresos - verde
                    'rgba(239, 68, 68, 0.5)',   // Costos - rojo
                    'rgba(239, 68, 68, 0.5)',   // Gastos - rojo
                    'rgba(59, 130, 246, 0.5)'   // Utilidad - azul
                ],
                borderColor: [
                    'rgb(34, 197, 94)',
                    'rgb(239, 68, 68)',
                    'rgb(239, 68, 68)',
                    'rgb(59, 130, 246)'
                ],
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

const createGastosChart = () => {
    if (gastosChartInstance) {
        gastosChartInstance.destroy();
    }

    const ctx = gastosChart.value.getContext('2d');
    gastosChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: props.chartData.gastos.labels,
            datasets: [{
                data: props.chartData.gastos.valores,
                backgroundColor: [
                    'rgba(239, 68, 68, 0.5)',
                    'rgba(245, 158, 11, 0.5)',
                    'rgba(59, 130, 246, 0.5)',
                    'rgba(139, 92, 246, 0.5)',
                    'rgba(34, 197, 94, 0.5)'
                ],
                borderColor: [
                    'rgb(239, 68, 68)',
                    'rgb(245, 158, 11)',
                    'rgb(59, 130, 246)',
                    'rgb(139, 92, 246)',
                    'rgb(34, 197, 94)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = formatCurrency(context.parsed);
                            const percentage = ((context.parsed / context.dataset.data.reduce((a, b) => a + b, 0)) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
};

const generarLibroVentas = () => {
    router.visit('/reports/libro-ventas');
};

const generarLibroCompras = () => {
    router.visit('/reports/libro-compras');
};

const conciliarBanco = () => {
    router.visit('/bank-reconciliation');
};

const declararImpuestos = () => {
    router.visit('/sii/declaracion');
};

onMounted(() => {
    createEstadoResultadosChart();
    createGastosChart();

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
    if (estadoResultadosChartInstance) {
        estadoResultadosChartInstance.destroy();
    }
    if (gastosChartInstance) {
        gastosChartInstance.destroy();
    }
});
</script>