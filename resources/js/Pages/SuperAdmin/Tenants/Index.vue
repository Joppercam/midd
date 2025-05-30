<template>
    <SuperAdminLayout>
        <Head title="Gestión de Empresas" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div class="sm:flex sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestión de Empresas</h1>
                    <p class="text-gray-600">Administra todas las empresas del sistema</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <Link
                        :href="route('super-admin.tenants.create')"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                    >
                        <PlusIcon class="h-4 w-4 mr-2" />
                        Nueva Empresa
                    </Link>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <StatCard
                    title="Total Empresas"
                    :value="stats.total"
                    icon="building"
                    color="blue"
                />
                <StatCard
                    title="Activas"
                    :value="stats.active"
                    icon="check-circle"
                    color="green"
                />
                <StatCard
                    title="En Prueba"
                    :value="stats.trial"
                    icon="clock"
                    color="yellow"
                />
                <StatCard
                    title="Suspendidas"
                    :value="stats.suspended"
                    icon="x-circle"
                    color="red"
                />
            </div>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Buscar
                            </label>
                            <input
                                v-model="searchForm.search"
                                type="text"
                                placeholder="Buscar empresas..."
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                                @input="search"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Estado
                            </label>
                            <select
                                v-model="searchForm.status"
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                                @change="search"
                            >
                                <option value="">Todos los Estados</option>
                                <option value="active">Activa</option>
                                <option value="trial">En Prueba</option>
                                <option value="suspended">Suspendida</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button
                                @click="clearFilters"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                Limpiar Filtros
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenants Table -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Empresa
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Plan
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Uso
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Creada
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr
                            v-for="tenant in tenants.data"
                            :key="tenant.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                                            <span class="text-sm font-medium text-red-700">
                                                {{ tenant.name.charAt(0) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ tenant.name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ tenant.email }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ tenant.plan || 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <TenantStatusBadge :status="tenant.subscription_status" :isActive="tenant.is_active" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{{ tenant.users_count }}/{{ tenant.max_users }} usuarios</div>
                                <div>{{ tenant.tax_documents_count }} documentos</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatDate(tenant.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <Link
                                    :href="route('super-admin.tenants.show', tenant.id)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    Ver
                                </Link>
                                <Link
                                    :href="route('super-admin.tenants.edit', tenant.id)"
                                    class="text-indigo-600 hover:text-indigo-900"
                                >
                                    Editar
                                </Link>
                                <button
                                    v-if="tenant.is_active"
                                    @click="showSuspendModal(tenant)"
                                    class="text-yellow-600 hover:text-yellow-900"
                                >
                                    Suspender
                                </button>
                                <button
                                    v-else
                                    @click="reactivateTenant(tenant)"
                                    class="text-green-600 hover:text-green-900"
                                >
                                    Reactivar
                                </button>
                                <button
                                    @click="impersonateTenant(tenant)"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    Suplantar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <Pagination :links="tenants.links" />
                </div>
            </div>
        </div>

        <!-- Suspend Modal -->
        <Modal :show="suspendModal.show" @close="suspendModal.show = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Suspender Empresa
                </h3>
                <p class="text-sm text-gray-600 mb-4">
                    ¿Estás seguro de que quieres suspender {{ suspendModal.tenant?.name }}? 
                    Esto impedirá que todos los usuarios accedan al sistema.
                </p>
                <form @submit.prevent="suspendTenant">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Motivo de la Suspensión
                        </label>
                        <textarea
                            v-model="suspendForm.reason"
                            rows="3"
                            required
                            class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                            placeholder="Ingresa el motivo de la suspensión..."
                        ></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="suspendModal.show = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="suspendForm.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ suspendForm.processing ? 'Suspendiendo...' : 'Suspender Empresa' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { PlusIcon } from '@heroicons/vue/24/outline'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import StatCard from '@/Components/SuperAdmin/StatCard.vue'
import TenantStatusBadge from '@/Components/SuperAdmin/TenantStatusBadge.vue'
import Modal from '@/Components/Modal.vue'
import Pagination from '@/Components/UI/Pagination.vue'

const props = defineProps({
    tenants: Object,
    stats: Object,
    filters: Object,
})

const searchForm = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
})

const suspendModal = reactive({
    show: false,
    tenant: null,
})

const suspendForm = useForm({
    reason: '',
})

const search = () => {
    router.get(route('super-admin.tenants.index'), searchForm, {
        preserveState: true,
        replace: true,
    })
}

const clearFilters = () => {
    searchForm.search = ''
    searchForm.status = ''
    search()
}

const showSuspendModal = (tenant) => {
    suspendModal.tenant = tenant
    suspendModal.show = true
    suspendForm.reset()
}

const suspendTenant = () => {
    suspendForm.post(route('super-admin.tenants.suspend', suspendModal.tenant.id), {
        onSuccess: () => {
            suspendModal.show = false
        }
    })
}

const reactivateTenant = (tenant) => {
    router.post(route('super-admin.tenants.reactivate', tenant.id))
}

const impersonateTenant = (tenant) => {
    if (confirm(`¿Estás seguro de que quieres suplantar a ${tenant.name}?`)) {
        router.post(route('super-admin.tenants.impersonate', tenant.id))
    }
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL')
}
</script>