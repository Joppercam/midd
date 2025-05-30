<template>
    <SuperAdminLayout>
        <Head title="Gestión de Planes" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestión de Planes</h1>
                    <p class="text-gray-600">Administra los planes de suscripción disponibles</p>
                </div>
                <Link
                    :href="route('super-admin.subscriptions.plans.create')"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                >
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Nuevo Plan
                </Link>
            </div>

            <!-- Navigation Tabs -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <Link
                        :href="route('super-admin.subscriptions.index')"
                        class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                    >
                        Suscripciones
                    </Link>
                    <Link
                        :href="route('super-admin.subscriptions.plans')"
                        class="border-red-500 text-red-600 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                    >
                        Planes
                    </Link>
                </nav>
            </div>

            <!-- Plans Table -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Plan
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Precios
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Suscripciones
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="plan in plans" :key="plan.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ plan.name }}
                                            </div>
                                            <div class="text-sm text-gray-500" v-if="plan.description">
                                                {{ plan.description.length > 50 ? plan.description.substring(0, 50) + '...' : plan.description }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ plan.code }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <div>Mensual: ${{ formatPrice(plan.monthly_price) }}</div>
                                            <div v-if="plan.annual_price" class="text-gray-500">
                                                Anual: ${{ formatPrice(plan.annual_price) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ plan.subscriptions_count || 0 }} activas
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="plan.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Activo
                                        </span>
                                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Inactivo
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <Link
                                            :href="route('super-admin.subscriptions.plans.edit', plan.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Editar
                                        </Link>
                                        <button
                                            @click="togglePlanStatus(plan)"
                                            class="text-yellow-600 hover:text-yellow-900"
                                        >
                                            {{ plan.is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                        <button
                                            @click="deletePlan(plan)"
                                            class="text-red-600 hover:text-red-900"
                                            v-if="(plan.subscriptions_count || 0) === 0"
                                        >
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Empty State -->
                        <div v-if="plans.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay planes</h3>
                            <p class="mt-1 text-sm text-gray-500">Comienza creando tu primer plan de suscripción.</p>
                            <div class="mt-6">
                                <Link
                                    :href="route('super-admin.subscriptions.plans.create')"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                >
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Crear Plan
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    plans: Array
})

const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CL').format(price)
}

const togglePlanStatus = (plan) => {
    if (confirm(`¿Estás seguro de ${plan.is_active ? 'desactivar' : 'activar'} el plan "${plan.name}"?`)) {
        router.patch(route('super-admin.subscriptions.plans.toggle', plan.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Refresh the page data
                router.reload({ only: ['plans'] })
            }
        })
    }
}

const deletePlan = (plan) => {
    if (confirm(`¿Estás seguro de eliminar el plan "${plan.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(route('super-admin.subscriptions.plans.destroy', plan.id), {
            preserveScroll: true,
            onSuccess: () => {
                // Refresh the page data
                router.reload({ only: ['plans'] })
            }
        })
    }
}
</script>