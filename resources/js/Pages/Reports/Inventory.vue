<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Reporte de Inventario
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
                        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700">Categoría</label>
                                <select
                                    id="category_id"
                                    v-model="form.category_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todas las categorías</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="all">Todos</option>
                                    <option value="low_stock">Stock Bajo</option>
                                    <option value="out_of_stock">Sin Stock</option>
                                </select>
                            </div>
                            <div class="flex items-end space-x-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700"
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
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Total Productos</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ statistics.total_products }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                productos físicos
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Valor Total</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(statistics.total_value) }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                al costo
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Stock Bajo</div>
                            <div class="mt-1 text-3xl font-semibold text-yellow-600">
                                {{ statistics.low_stock_count }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                productos
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Sin Stock</div>
                            <div class="mt-1 text-3xl font-semibold text-red-600">
                                {{ statistics.out_of_stock_count }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                productos
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Valorización por Categoría -->
                    <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Valorización por Categoría</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Categoría
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Productos
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Stock Total
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Valor Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="category in valuation_by_category" :key="category.category">
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                {{ category.category }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                                {{ category.count }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                                {{ formatNumber(category.total_stock) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                                {{ formatCurrency(category.total_value) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de distribución -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Distribución de Valor</h3>
                            <div class="h-64">
                                <canvas ref="distributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Productos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Detalle de Productos</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            SKU
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Producto
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Categoría
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Stock
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Stock Mínimo
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Costo
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Valor Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="product in products.data" :key="product.id">
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ product.sku }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ product.name }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            {{ product.category?.name || 'Sin categoría' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right">
                                            <span 
                                                class="font-medium"
                                                :class="{
                                                    'text-red-600': product.stock === 0,
                                                    'text-yellow-600': product.stock > 0 && product.stock <= product.minimum_stock,
                                                    'text-gray-900': product.stock > product.minimum_stock
                                                }"
                                            >
                                                {{ product.stock }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                            {{ product.minimum_stock }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                            {{ formatCurrency(product.cost) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                            {{ formatCurrency(product.stock * product.cost) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <div class="mt-4" v-if="products.links">
                            <Pagination :links="products.links" />
                        </div>
                    </div>
                </div>

                <!-- Movimientos Recientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Movimientos Recientes</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Fecha
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Producto
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cantidad
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Descripción
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="movement in recent_movements" :key="movement.id">
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ formatDate(movement.created_at) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ movement.product.name }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <span 
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                :class="getMovementTypeClass(movement.movement_type)"
                                            >
                                                {{ getMovementTypeLabel(movement.movement_type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                            {{ movement.movement_type === 'sale' || movement.movement_type === 'return' ? '-' : '+' }}{{ movement.quantity }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            {{ movement.description }}
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
import Pagination from '@/Components/UI/Pagination.vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    products: Object, // Ahora es un objeto paginado
    statistics: Object,
    recent_movements: Array,
    valuation_by_category: Array,
    filters: Object,
    categories: Array
});

const distributionChart = ref(null);
const form = ref({
    category_id: props.filters.category_id || '',
    status: props.filters.status || 'all'
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

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL');
};

const getMovementTypeLabel = (type) => {
    const labels = {
        'purchase': 'Compra',
        'sale': 'Venta',
        'adjustment': 'Ajuste',
        'return': 'Devolución'
    };
    return labels[type] || type;
};

const getMovementTypeClass = (type) => {
    const classes = {
        'purchase': 'bg-green-100 text-green-800',
        'sale': 'bg-blue-100 text-blue-800',
        'adjustment': 'bg-yellow-100 text-yellow-800',
        'return': 'bg-red-100 text-red-800'
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const applyFilters = () => {
    router.get(route('reports.inventory'), form.value);
};

const exportPDF = () => {
    window.open(route('reports.inventory', { ...form.value, format: 'pdf' }), '_blank');
};

onMounted(() => {
    // Crear gráfico de distribución
    const ctx = distributionChart.value.getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: props.valuation_by_category.map(cat => cat.category),
            datasets: [{
                data: props.valuation_by_category.map(cat => cat.total_value),
                backgroundColor: [
                    'rgb(79, 70, 229)',
                    'rgb(34, 197, 94)',
                    'rgb(234, 179, 8)',
                    'rgb(239, 68, 68)',
                    'rgb(168, 85, 247)',
                    'rgb(251, 146, 60)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + formatCurrency(context.raw);
                        }
                    }
                }
            }
        }
    });
});
</script>