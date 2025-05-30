<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Cotizaciones
                </h2>
                <Link :href="route('quotes.create')" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nueva Cotización
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-900 text-xl font-bold">{{ stats.total }}</div>
                        <div class="text-gray-600 text-sm">Total Cotizaciones</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 text-xl font-bold">{{ stats.draft }}</div>
                        <div class="text-gray-600 text-sm">Borradores</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-blue-600 text-xl font-bold">{{ stats.sent }}</div>
                        <div class="text-gray-600 text-sm">Enviadas</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-green-600 text-xl font-bold">{{ stats.approved }}</div>
                        <div class="text-gray-600 text-sm">Aprobadas</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-yellow-600 text-xl font-bold">{{ stats.expired }}</div>
                        <div class="text-gray-600 text-sm">Expiradas</div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <form @submit.prevent="search" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                                <input
                                    v-model="form.search"
                                    type="text"
                                    placeholder="Número, cliente..."
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <select
                                    v-model="form.status"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="">Todos</option>
                                    <option value="draft">Borrador</option>
                                    <option value="sent">Enviada</option>
                                    <option value="approved">Aprobada</option>
                                    <option value="rejected">Rechazada</option>
                                    <option value="converted">Convertida</option>
                                    <option value="expired">Expirada</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                                <input
                                    v-model="form.date_from"
                                    type="date"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                                <input
                                    v-model="form.date_to"
                                    type="date"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex items-end space-x-2">
                                <button
                                    type="submit"
                                    class="px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    Filtrar
                                </button>
                                <button
                                    type="button"
                                    @click="clearFilters"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    Limpiar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Cotizaciones -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Número
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cliente
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Vencimiento
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="quote in quotes.data" :key="quote.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <Link :href="route('quotes.show', quote.id)" class="text-blue-600 hover:text-blue-900">
                                            {{ quote.quote_number }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ quote.customer.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(quote.issue_date) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span v-if="quote.days_to_expiry < 0" class="text-red-600">
                                            Expirada hace {{ Math.abs(quote.days_to_expiry) }} días
                                        </span>
                                        <span v-else-if="quote.days_to_expiry === 0" class="text-yellow-600">
                                            Expira hoy
                                        </span>
                                        <span v-else-if="quote.days_to_expiry <= 7" class="text-yellow-600">
                                            {{ quote.days_to_expiry }} días
                                        </span>
                                        <span v-else>
                                            {{ quote.days_to_expiry }} días
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatCurrency(quote.total) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                              :class="{
                                                  'bg-gray-100 text-gray-800': quote.status === 'draft',
                                                  'bg-blue-100 text-blue-800': quote.status === 'sent',
                                                  'bg-green-100 text-green-800': quote.status === 'approved',
                                                  'bg-red-100 text-red-800': quote.status === 'rejected',
                                                  'bg-purple-100 text-purple-800': quote.status === 'converted',
                                                  'bg-yellow-100 text-yellow-800': quote.status === 'expired' || quote.is_expired
                                              }">
                                            {{ quote.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <Link :href="route('quotes.show', quote.id)" class="text-blue-600 hover:text-blue-900">
                                                Ver
                                            </Link>
                                            <Link v-if="quote.can_be_edited" :href="route('quotes.edit', quote.id)" class="text-indigo-600 hover:text-indigo-900">
                                                Editar
                                            </Link>
                                            <button v-if="quote.can_be_sent" @click="sendQuote(quote)" class="text-green-600 hover:text-green-900">
                                                Enviar
                                            </button>
                                            <a :href="route('quotes.download', quote.id)" target="_blank" class="text-gray-600 hover:text-gray-900">
                                                PDF
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="quotes.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <Pagination :links="quotes.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    quotes: Object,
    filters: Object,
    stats: Object,
});

const form = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

const search = () => {
    router.get(route('quotes.index'), form.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    form.value = {
        search: '',
        status: '',
        date_from: '',
        date_to: '',
    };
    search();
};

const sendQuote = (quote) => {
    if (confirm(`¿Está seguro de enviar la cotización ${quote.quote_number}?`)) {
        router.post(route('quotes.send', quote.id), {}, {
            onSuccess: () => {
                // El mensaje de éxito se mostrará automáticamente
            },
        });
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
    }).format(amount);
};
</script>