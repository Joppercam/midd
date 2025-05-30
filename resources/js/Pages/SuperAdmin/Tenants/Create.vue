<template>
    <SuperAdminLayout>
        <Head title="Crear Nueva Empresa" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Crear Nueva Empresa</h1>
                <p class="text-gray-600">Registra una nueva empresa en el sistema</p>
            </div>

            <!-- Form -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <form @submit.prevent="submit" class="space-y-6 p-6">
                    <!-- Company Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información de la Empresa</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">
                                    Nombre Comercial
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                                <div v-if="form.errors.name" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                    Razón Social
                                </label>
                                <input
                                    id="legal_name"
                                    v-model="form.legal_name"
                                    type="text"
                                    required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                                <div v-if="form.errors.legal_name" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.legal_name }}
                                </div>
                            </div>

                            <div>
                                <label for="rut" class="block text-sm font-medium text-gray-700">
                                    RUT
                                </label>
                                <input
                                    id="rut"
                                    v-model="form.rut"
                                    type="text"
                                    required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                    placeholder="12.345.678-9"
                                />
                                <div v-if="form.errors.rut" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.rut }}
                                </div>
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Email
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                                <div v-if="form.errors.email" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.email }}
                                </div>
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">
                                    Teléfono
                                </label>
                                <input
                                    id="phone"
                                    v-model="form.phone"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                                <div v-if="form.errors.phone" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.phone }}
                                </div>
                            </div>

                            <div>
                                <label for="domain" class="block text-sm font-medium text-gray-700">
                                    Dominio (Opcional)
                                </label>
                                <input
                                    id="domain"
                                    v-model="form.domain"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                    placeholder="empresa.ejemplo.com"
                                />
                                <div v-if="form.errors.domain" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.domain }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subscription Plan -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Plan de Suscripción</h3>
                        <div>
                            <label for="plan" class="block text-sm font-medium text-gray-700">
                                Seleccionar Plan
                            </label>
                            <select
                                id="plan"
                                v-model="form.plan"
                                required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                            >
                                <option value="">Seleccionar plan...</option>
                                <option v-for="plan in plans" :key="plan.id" :value="plan.code">
                                    {{ plan.name }} - {{ formatCurrency(plan.monthly_price) }}/mes
                                </option>
                            </select>
                            <div v-if="form.errors.plan" class="mt-2 text-sm text-red-600">
                                {{ form.errors.plan }}
                            </div>
                        </div>
                    </div>

                    <!-- Administrator Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Administrador</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="admin_name" class="block text-sm font-medium text-gray-700">
                                    Nombre Completo
                                </label>
                                <input
                                    id="admin_name"
                                    v-model="form.admin_name"
                                    type="text"
                                    required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                                <div v-if="form.errors.admin_name" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.admin_name }}
                                </div>
                            </div>

                            <div>
                                <label for="admin_email" class="block text-sm font-medium text-gray-700">
                                    Email del Administrador
                                </label>
                                <input
                                    id="admin_email"
                                    v-model="form.admin_email"
                                    type="email"
                                    required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                                <div v-if="form.errors.admin_email" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.admin_email }}
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label for="admin_password" class="block text-sm font-medium text-gray-700">
                                    Contraseña
                                </label>
                                <input
                                    id="admin_password"
                                    v-model="form.admin_password"
                                    type="password"
                                    required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                                <div v-if="form.errors.admin_password" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.admin_password }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                        <Link
                            :href="route('super-admin.tenants.index')"
                            class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Creando...' : 'Crear Empresa' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    plans: Array,
})

const form = useForm({
    name: '',
    legal_name: '',
    rut: '',
    email: '',
    phone: '',
    domain: '',
    plan: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
})

const submit = () => {
    console.log('Enviando formulario:', form.data())
    console.log('Errores actuales:', form.errors)
    
    form.post(route('super-admin.tenants.store'), {
        onError: (errors) => {
            console.error('Errores de validación:', errors)
        },
        onSuccess: (page) => {
            console.log('Éxito:', page)
        },
        onFinish: () => {
            console.log('Solicitud terminada')
        }
    })
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP'
    }).format(amount)
}
</script>