<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted, ref, computed, nextTick } from 'vue';
import { Chart, registerables } from 'chart.js';
import { useDashboardWidgets } from '@/composables/useDashboardWidgets.js';

Chart.register(...registerables);

const props = defineProps({
  metrics: {
    type: Object,
    default: () => ({
      // Ventas
      monthly_revenue: 0,
      pending_invoices: 0,
      overdue_invoices: 0,
      documents_this_month: 0,
      
      // Clientes
      total_customers: 0,
      new_customers_month: 0,
      
      // Inventario
      total_products: 0,
      low_stock_products: 0,
      
      // Finanzas
      total_expenses_month: 0,
      cash_flow: 0,
      pending_reconciliations: 0,
      
      // Charts
      monthly_revenue_chart: [],
      expense_by_category: [],
      payment_status_chart: [],
      
      // Listas
      recent_invoices: [],
      pending_payments: [],
      upcoming_expenses: [],
      recent_activities: []
    })
  },
});

const page = usePage();
const salesChart = ref(null);
const expenseChart = ref(null);
const paymentChart = ref(null);

// Usar el composable para widgets dinámicos
const metricsRef = computed(() => props.metrics);
const { 
  availableWidgets, 
  quickActions, 
  formatValue, 
  getWidgetColorClasses, 
  getActionColorClasses,
  hasPermission 
} = useDashboardWidgets(metricsRef);

// Título personalizado según el rol
const dashboardTitle = computed(() => {
  const userRole = page.props.auth.user.role;
  const userName = page.props.auth.user.name.split(' ')[0];
  
  const roleTitles = {
    admin: `Bienvenido, ${userName}`,
    sales: `Bienvenido, ${userName}`,
    accountant: `Bienvenido, ${userName}`,
    operations: `Bienvenido, ${userName}`
  };
  
  return roleTitles[userRole] || `Bienvenido, ${userName}`;
});

// Subtítulo según el rol
const dashboardSubtitle = computed(() => {
  const userRole = page.props.auth.user.role;
  
  const roleSubtitles = {
    admin: 'Panel de Control Administrativo',
    sales: 'Panel de Ventas y Clientes',
    accountant: 'Panel Financiero y Contable',
    operations: 'Panel de Operaciones'
  };
  
  return roleSubtitles[userRole] || 'Panel de Control';
});

// Configuración de gráficos profesional
const chartOptions = {
  sales: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      intersect: false,
    },
    plugins: {
      legend: {
        display: false,
      },
      tooltip: {
        backgroundColor: '#1e293b',
        titleColor: '#f8fafc',
        bodyColor: '#f8fafc',
        borderColor: '#0070d6',
        borderWidth: 1,
        cornerRadius: 8,
        padding: 12,
      }
    },
    scales: {
      x: {
        grid: {
          color: '#e2e8f0',
          borderDash: [2, 2],
        },
        ticks: {
          color: '#64748b',
          font: {
            size: 12,
            weight: 500,
          }
        }
      },
      y: {
        grid: {
          color: '#e2e8f0',
          borderDash: [2, 2],
        },
        ticks: {
          color: '#64748b',
          font: {
            size: 12,
            weight: 500,
          },
          callback: function(value) {
            return '$' + new Intl.NumberFormat('es-CL').format(value);
          }
        }
      }
    },
    elements: {
      point: {
        radius: 6,
        backgroundColor: '#0070d6',
        borderColor: '#ffffff',
        borderWidth: 2,
        hoverRadius: 8,
      },
      line: {
        tension: 0.4,
      }
    }
  },
  
  expense: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          padding: 20,
          usePointStyle: true,
          font: {
            size: 12,
            weight: 500,
          }
        }
      },
      tooltip: {
        backgroundColor: '#1e293b',
        titleColor: '#f8fafc',
        bodyColor: '#f8fafc',
        borderColor: '#0070d6',
        borderWidth: 1,
        cornerRadius: 8,
        padding: 12,
      }
    }
  },
  
  payment: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          padding: 20,
          usePointStyle: true,
          font: {
            size: 12,
            weight: 500,
          }
        }
      },
      tooltip: {
        backgroundColor: '#1e293b',
        titleColor: '#f8fafc',
        bodyColor: '#f8fafc',
        borderColor: '#0070d6',
        borderWidth: 1,
        cornerRadius: 8,
        padding: 12,
      }
    }
  }
};

// Función para inicializar gráficos con diseño profesional
const initializeCharts = () => {
  nextTick(() => {
    // Gráfico de ventas
    if (salesChart.value && props.metrics.monthly_revenue_chart?.length > 0) {
      const ctx = salesChart.value.getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: props.metrics.monthly_revenue_chart.map(item => item.month),
          datasets: [{
            label: 'Ventas',
            data: props.metrics.monthly_revenue_chart.map(item => item.revenue),
            borderColor: '#0070d6',
            backgroundColor: 'rgba(0, 112, 214, 0.1)',
            fill: true,
            tension: 0.4,
          }]
        },
        options: chartOptions.sales
      });
    }

    // Gráfico de gastos
    if (expenseChart.value && props.metrics.expense_by_category?.length > 0) {
      const ctx = expenseChart.value.getContext('2d');
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: props.metrics.expense_by_category.map(item => item.category),
          datasets: [{
            data: props.metrics.expense_by_category.map(item => item.amount),
            backgroundColor: [
              '#0070d6',
              '#22c55e',
              '#f59e0b',
              '#ef4444',
              '#8b5cf6',
              '#06b6d4'
            ],
            borderWidth: 3,
            borderColor: '#ffffff',
          }]
        },
        options: chartOptions.expense
      });
    }

    // Gráfico de estado de pagos
    if (paymentChart.value && props.metrics.payment_status_chart?.length > 0) {
      const ctx = paymentChart.value.getContext('2d');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: props.metrics.payment_status_chart.map(item => item.status),
          datasets: [{
            label: 'Pagos',
            data: props.metrics.payment_status_chart.map(item => item.count),
            backgroundColor: [
              '#22c55e',
              '#f59e0b',
              '#ef4444'
            ],
            borderRadius: 8,
            borderSkipped: false,
          }]
        },
        options: {
          ...chartOptions.payment,
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: '#e2e8f0',
                borderDash: [2, 2],
              },
              ticks: {
                color: '#64748b',
                font: {
                  size: 12,
                  weight: 500,
                }
              }
            },
            x: {
              grid: {
                display: false,
              },
              ticks: {
                color: '#64748b',
                font: {
                  size: 12,
                  weight: 500,
                }
              }
            }
          }
        }
      });
    }
  });
};

onMounted(() => {
  initializeCharts();
});

// Función para obtener la hora de saludo
const getGreetingTime = () => {
  const hour = new Date().getHours();
  if (hour < 12) return 'Buenos días';
  if (hour < 18) return 'Buenas tardes';
  return 'Buenas noches';
};

// Función para obtener icono de métrica
const getMetricIcon = (key) => {
  const icons = {
    monthly_revenue: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    pending_invoices: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    total_customers: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    total_products: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    low_stock_products: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    cash_flow: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'
  };
  return icons[key] || 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-brand-600 to-brand-700 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex items-center justify-between">
                    <div class="animate-fade-in">
                        <p class="text-brand-200 text-sm font-medium mb-1">{{ getGreetingTime() }}</p>
                        <h1 class="text-3xl font-bold text-white mb-2">{{ dashboardTitle }}</h1>
                        <p class="text-brand-100 text-lg">{{ dashboardSubtitle }}</p>
                    </div>
                    <div class="hidden md:flex md:items-center md:space-x-4">
                        <!-- Fecha actual -->
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 text-center">
                            <p class="text-brand-100 text-sm">Hoy</p>
                            <p class="text-white text-xl font-bold">{{ new Date().toLocaleDateString('es-CL') }}</p>
                        </div>
                        
                        <!-- Estado del sistema -->
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                            <div class="flex items-center space-x-2">
                                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                <div>
                                    <p class="text-brand-100 text-xs">Sistema</p>
                                    <p class="text-white text-sm font-semibold">Operativo</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Métricas Principales - Simplificado -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Total Clientes -->
                <div class="card animate-slide-up" style="animation-delay: 100ms;">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-600 text-sm font-medium mb-2">Total Clientes</p>
                                <p class="text-2xl font-bold text-slate-900">{{ props.metrics.total_customers || 0 }}</p>
                                <p class="text-xs text-slate-500 mt-1">Clientes registrados</p>
                            </div>
                            <div class="p-3 rounded-xl bg-blue-100 text-blue-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Productos -->
                <div class="card animate-slide-up" style="animation-delay: 200ms;">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-600 text-sm font-medium mb-2">Total Productos</p>
                                <p class="text-2xl font-bold text-slate-900">{{ props.metrics.total_products || 0 }}</p>
                                <p class="text-xs text-slate-500 mt-1">En inventario</p>
                            </div>
                            <div class="p-3 rounded-xl bg-green-100 text-green-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documentos del Mes -->
                <div class="card animate-slide-up" style="animation-delay: 300ms;">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-600 text-sm font-medium mb-2">Documentos del Mes</p>
                                <p class="text-2xl font-bold text-slate-900">{{ props.metrics.documents_this_month || 0 }}</p>
                                <p class="text-xs text-slate-500 mt-1">Facturas emitidas</p>
                            </div>
                            <div class="p-3 rounded-xl bg-purple-100 text-purple-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Actividad Reciente -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Facturas Recientes -->
                <div class="card animate-slide-up" style="animation-delay: 400ms;">
                    <div class="card-header">
                        <div class="flex items-center justify-between">
                            <h3 class="text-heading-sm text-slate-900">Facturas Recientes</h3>
                            <Link href="/invoices" class="text-brand-600 hover:text-brand-700 text-sm font-medium">
                                Ver todas
                            </Link>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="overflow-hidden">
                            <div 
                                v-for="(invoice, index) in props.metrics.recent_invoices.slice(0, 5)" 
                                :key="invoice.id"
                                class="p-4 border-b border-slate-100 last:border-b-0 hover:bg-slate-50 transition-colors duration-150"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">
                                            {{ invoice.customer_name }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            Factura #{{ invoice.number }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ $formatCurrency(invoice.total) }}
                                        </p>
                                        <div class="flex items-center justify-end mt-1">
                                            <span 
                                                :class="[
                                                    'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                                    invoice.status === 'paid' ? 'bg-success-100 text-success-800' :
                                                    invoice.status === 'pending' ? 'bg-warning-100 text-warning-800' :
                                                    'bg-slate-100 text-slate-800'
                                                ]"
                                            >
                                                {{ invoice.status === 'paid' ? 'Pagada' : 
                                                   invoice.status === 'pending' ? 'Pendiente' : 'Borrador' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actividad Reciente -->
                <div class="card animate-slide-up" style="animation-delay: 500ms;">
                    <div class="card-header">
                        <h3 class="text-heading-sm text-slate-900">Actividad Reciente</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="overflow-hidden">
                            <div 
                                v-for="(activity, index) in props.metrics.recent_activities.slice(0, 5)" 
                                :key="activity.id"
                                class="p-4 border-b border-slate-100 last:border-b-0 hover:bg-slate-50 transition-colors duration-150"
                            >
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-brand-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-slate-900">
                                            {{ activity.description }}
                                        </p>
                                        <p class="text-xs text-slate-500 mt-1">
                                            {{ $formatDate(activity.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de Resumen y Tareas Pendientes -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Resumen Ejecutivo -->
                <div class="lg:col-span-2 card animate-slide-up" style="animation-delay: 600ms;">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-slate-900">Resumen Ejecutivo</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Tendencias del Mes -->
                            <div>
                                <h4 class="text-sm font-semibold text-slate-700 mb-3">Tendencias del Mes</h4>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-slate-900">Ingresos</p>
                                                <p class="text-xs text-slate-500">vs. mes anterior</p>
                                            </div>
                                        </div>
                                        <span class="text-sm font-semibold text-green-600">+12.5%</span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-slate-900">Nuevos Clientes</p>
                                                <p class="text-xs text-slate-500">Este mes</p>
                                            </div>
                                        </div>
                                        <span class="text-sm font-semibold text-blue-600">{{ props.metrics.new_customers_month || 0 }}</span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-slate-900">Stock Bajo</p>
                                                <p class="text-xs text-slate-500">Productos</p>
                                            </div>
                                        </div>
                                        <span class="text-sm font-semibold text-red-600">{{ props.metrics.low_stock_products || 0 }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Metas del Mes -->
                            <div>
                                <h4 class="text-sm font-semibold text-slate-700 mb-3">Metas del Mes</h4>
                                <div class="space-y-4">
                                    <!-- Meta de Ventas -->
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium text-slate-900">Meta de Ventas</span>
                                            <span class="text-sm text-slate-600">75%</span>
                                        </div>
                                        <div class="w-full bg-slate-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: 75%"></div>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1">$750M de $1,000M</p>
                                    </div>
                                    
                                    <!-- Meta de Cobranza -->
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium text-slate-900">Meta de Cobranza</span>
                                            <span class="text-sm text-slate-600">60%</span>
                                        </div>
                                        <div class="w-full bg-slate-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full" style="width: 60%"></div>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1">$450M de $750M</p>
                                    </div>
                                    
                                    <!-- Meta de Nuevos Clientes -->
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium text-slate-900">Nuevos Clientes</span>
                                            <span class="text-sm text-slate-600">90%</span>
                                        </div>
                                        <div class="w-full bg-slate-200 rounded-full h-2">
                                            <div class="bg-purple-600 h-2 rounded-full" style="width: 90%"></div>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1">18 de 20 clientes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tareas Pendientes y Alertas -->
                <div class="space-y-6">
                    <!-- Tareas Pendientes -->
                    <div class="card animate-slide-up" style="animation-delay: 700ms;">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-slate-900">Tareas Pendientes</h3>
                        </div>
                        <div class="card-body">
                            <div class="space-y-3">
                                <div class="flex items-center space-x-3 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                    <div class="w-2 h-2 bg-orange-500 rounded-full"></div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900">Revisar facturas vencidas</p>
                                        <p class="text-xs text-slate-500">{{ props.metrics.overdue_invoices || 0 }} facturas pendientes</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900">Conciliación bancaria</p>
                                        <p class="text-xs text-slate-500">{{ props.metrics.pending_reconciliations || 0 }} pendientes</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <div class="w-2 h-2 bg-red-500 rounded-full"></div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900">Stock crítico</p>
                                        <p class="text-xs text-slate-500">{{ props.metrics.low_stock_products || 0 }} productos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recordatorios del Día -->
                    <div class="card animate-slide-up" style="animation-delay: 800ms;">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-slate-900">Recordatorios</h3>
                        </div>
                        <div class="card-body">
                            <div class="space-y-3">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mt-0.5">
                                        <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">Cierre mensual</p>
                                        <p class="text-xs text-slate-500">Faltan 5 días para el cierre</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mt-0.5">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">Backup automático</p>
                                        <p class="text-xs text-slate-500">Programado para hoy 23:00</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mt-0.5">
                                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-4 8v-3m0 0V8a2 2 0 012-2h2m-2 6a2 2 0 01-2-2V8a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">Reunión equipo</p>
                                        <p class="text-xs text-slate-500">Mañana 10:00 AM</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enlaces Rápidos y Ayuda -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Enlaces Rápidos -->
                <div class="card animate-slide-up" style="animation-delay: 900ms;">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-slate-900">Enlaces Rápidos</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-2 gap-3">
                            <Link href="/invoices/create" class="flex flex-col items-center p-4 bg-slate-50 hover:bg-blue-50 border border-transparent hover:border-blue-200 rounded-lg transition-all duration-200 group">
                                <div class="w-10 h-10 bg-blue-100 group-hover:bg-blue-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-slate-900 text-center">Nueva Factura</span>
                            </Link>
                            
                            <Link href="/customers/create" class="flex flex-col items-center p-4 bg-slate-50 hover:bg-green-50 border border-transparent hover:border-green-200 rounded-lg transition-all duration-200 group">
                                <div class="w-10 h-10 bg-green-100 group-hover:bg-green-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-slate-900 text-center">Nuevo Cliente</span>
                            </Link>
                            
                            <Link href="/products/create" class="flex flex-col items-center p-4 bg-slate-50 hover:bg-purple-50 border border-transparent hover:border-purple-200 rounded-lg transition-all duration-200 group">
                                <div class="w-10 h-10 bg-purple-100 group-hover:bg-purple-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-slate-900 text-center">Nuevo Producto</span>
                            </Link>
                            
                            <Link href="/reports" class="flex flex-col items-center p-4 bg-slate-50 hover:bg-orange-50 border border-transparent hover:border-orange-200 rounded-lg transition-all duration-200 group">
                                <div class="w-10 h-10 bg-orange-100 group-hover:bg-orange-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-slate-900 text-center">Ver Reportes</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Centro de Ayuda -->
                <div class="card animate-slide-up" style="animation-delay: 1000ms;">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-slate-900">Centro de Ayuda</h3>
                    </div>
                    <div class="card-body">
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mt-0.5">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">Guía de Inicio</p>
                                    <p class="text-xs text-slate-500">Aprende los conceptos básicos del sistema</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mt-0.5">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">Video Tutoriales</p>
                                    <p class="text-xs text-slate-500">Mira cómo usar las funciones principales</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-3 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mt-0.5">
                                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">Soporte Técnico</p>
                                    <p class="text-xs text-slate-500">Contacta con nuestro equipo de ayuda</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-slide-up {
  opacity: 0;
  transform: translateY(20px);
  animation: slideUp 0.6s ease-out forwards;
}

.animate-fade-in {
  opacity: 0;
  animation: fadeIn 0.8s ease-out forwards;
}

@keyframes slideUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeIn {
  to {
    opacity: 1;
  }
}
</style>