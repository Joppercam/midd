<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
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
    admin: `Dashboard Administrativo - Bienvenido ${userName}`,
    sales: `Dashboard de Ventas - Bienvenido ${userName}`,
    accountant: `Dashboard Contable - Bienvenido ${userName}`,
    operations: `Dashboard de Operaciones - Bienvenido ${userName}`
  };
  
  return roleTitles[userRole] || `Dashboard - Bienvenido ${userName}`;
});

// Los formatters ahora están disponibles globalmente como $formatCurrency, $formatDate, etc.

onMounted(() => {
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
          borderColor: 'rgb(99, 102, 241)',
          backgroundColor: 'rgba(99, 102, 241, 0.1)',
          tension: 0.1,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return this.$formatCurrency(value);
              }
            }
          }
        }
      }
    });
  }

  // Gráfico de gastos por categoría
  if (expenseChart.value && props.metrics.expense_by_category?.length > 0) {
    const ctx = expenseChart.value.getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: props.metrics.expense_by_category.map(item => item.category),
        datasets: [{
          data: props.metrics.expense_by_category.map(item => item.amount),
          backgroundColor: [
            'rgb(99, 102, 241)',
            'rgb(59, 130, 246)',
            'rgb(147, 51, 234)',
            'rgb(236, 72, 153)',
            'rgb(251, 146, 60)',
          ],
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
          },
        }
      }
    });
  }

  // Gráfico de estado de pagos
  if (paymentChart.value && props.metrics.payment_status_chart) {
    const ctx = paymentChart.value.getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Pagado', 'Pendiente', 'Vencido'],
        datasets: [{
          label: 'Facturas',
          data: [
            props.metrics.payment_status_chart.paid || 0,
            props.metrics.payment_status_chart.pending || 0,
            props.metrics.payment_status_chart.overdue || 0
          ],
          backgroundColor: [
            'rgb(34, 197, 94)',
            'rgb(251, 191, 36)',
            'rgb(239, 68, 68)',
          ],
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        }
      }
    });
  }
});

</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ dashboardTitle }}</h2>
          <p class="text-sm text-gray-600 mt-1">{{ page.props.auth.user.tenant?.name }}</p>
        </div>
        <div class="text-sm text-gray-500">
          {{ new Date().toLocaleDateString('es-CL', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Widgets Grid Dinámicos -->
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <Link
          v-for="widget in availableWidgets"
          :key="widget.id"
          :href="route(widget.link)"
          class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-all duration-200 transform hover:-translate-y-1"
        >
          <div class="p-5">
            <div class="flex items-center">
              <div :class="['flex-shrink-0 p-3 rounded-md', getWidgetColorClasses(widget.color)]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="widget.icon" />
                </svg>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate flex items-center">
                    {{ widget.title }}
                    <span v-if="widget.alert" class="ml-2 flex h-2 w-2">
                      <span class="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-red-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                    </span>
                  </dt>
                  <dd class="flex items-baseline">
                    <div class="text-2xl font-semibold text-gray-900">
                      {{ formatValue(widget.getValue(), widget.format) }}
                    </div>
                    <div v-if="widget.trend" class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                      <svg class="self-center flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                      </svg>
                      <span>{{ widget.trend }}</span>
                    </div>
                  </dd>
                  <dd v-if="widget.subtitle" class="mt-1 text-xs text-gray-500">
                    {{ widget.subtitle }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </Link>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <!-- Sales Chart -->
        <div class="lg:col-span-2 bg-white shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg leading-6 font-medium text-gray-900">
                Ventas últimos 6 meses
              </h3>
              <Link :href="route('reports.sales')" class="text-sm text-indigo-600 hover:text-indigo-900">
                Ver reporte completo →
              </Link>
            </div>
            <div class="h-64">
              <canvas ref="salesChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Expense by Category -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg leading-6 font-medium text-gray-900">
                Gastos por Categoría
              </h3>
              <Link :href="route('expenses.index')" class="text-sm text-indigo-600 hover:text-indigo-900">
                Ver todos →
              </Link>
            </div>
            <div class="h-64">
              <canvas ref="expenseChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Tables Row -->
      <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- Recent Invoices -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg leading-6 font-medium text-gray-900">
                Facturas Recientes
              </h3>
              <Link :href="route('invoices.create')" class="text-sm text-indigo-600 hover:text-indigo-900">
                Nueva factura →
              </Link>
            </div>
            <div class="flow-root">
              <ul role="list" class="-my-5 divide-y divide-gray-200">
                <li v-for="invoice in metrics.recent_invoices?.slice(0, 5)" :key="invoice.id" class="py-4">
                  <div class="flex items-center space-x-4">
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate">
                        {{ invoice.customer?.name }}
                      </p>
                      <p class="text-sm text-gray-500">
                        {{ invoice.document_number }} - {{ formatDate(invoice.issue_date) }}
                      </p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-900">
                        {{ $formatCurrency(invoice.total_amount) }}
                      </p>
                      <span :class="[
                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                        invoice.payment_status === 'paid' ? 'bg-green-100 text-green-800' : 
                        invoice.payment_status === 'overdue' ? 'bg-red-100 text-red-800' : 
                        'bg-yellow-100 text-yellow-800'
                      ]">
                        {{ invoice.payment_status === 'paid' ? 'Pagado' : 
                            invoice.payment_status === 'overdue' ? 'Vencido' : 'Pendiente' }}
                      </span>
                    </div>
                  </div>
                </li>
              </ul>
              <div v-if="!metrics.recent_invoices?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="mt-2 text-sm text-gray-500">No hay facturas recientes</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending Payments -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg leading-6 font-medium text-gray-900">
                Pagos Pendientes
              </h3>
              <Link :href="route('payments.index')" class="text-sm text-indigo-600 hover:text-indigo-900">
                Ver todos →
              </Link>
            </div>
            <div class="flow-root">
              <ul role="list" class="-my-5 divide-y divide-gray-200">
                <li v-for="payment in metrics.pending_payments?.slice(0, 5)" :key="payment.id" class="py-4">
                  <div class="flex items-center space-x-4">
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate">
                        {{ payment.customer?.name }}
                      </p>
                      <p class="text-sm text-gray-500">
                        Vence {{ formatDate(payment.due_date) }}
                      </p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-900">
                        {{ $formatCurrency(payment.amount) }}
                      </p>
                      <p :class="[
                        'text-xs',
                        new Date(payment.due_date) < new Date() ? 'text-red-600' : 'text-gray-500'
                      ]">
                        {{ new Date(payment.due_date) < new Date() ? 'Vencido' : 'Por vencer' }}
                      </p>
                    </div>
                  </div>
                </li>
              </ul>
              <div v-if="!metrics.pending_payments?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <p class="mt-2 text-sm text-gray-500">No hay pagos pendientes</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment Status Chart -->
      <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
              Estado de Facturas
            </h3>
            <div class="flex space-x-4 text-sm">
              <div class="flex items-center">
                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                <span class="text-gray-600">Pagadas</span>
              </div>
              <div class="flex items-center">
                <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                <span class="text-gray-600">Pendientes</span>
              </div>
              <div class="flex items-center">
                <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                <span class="text-gray-600">Vencidas</span>
              </div>
            </div>
          </div>
          <div class="h-64">
            <canvas ref="paymentChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Quick Actions Dinámicas -->
      <div v-if="quickActions.length > 0" class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-blue-900 mb-4">Acciones Rápidas</h3>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
          <Link
            v-for="action in quickActions"
            :key="action.route"
            :href="route(action.route)"
            class="bg-white rounded-lg p-4 text-center hover:shadow-md transition-all duration-200 transform hover:-translate-y-1"
          >
            <svg :class="['mx-auto h-8 w-8', getActionColorClasses(action.color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="action.icon" />
            </svg>
            <p class="mt-2 text-sm font-medium text-gray-900">{{ action.title }}</p>
          </Link>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>