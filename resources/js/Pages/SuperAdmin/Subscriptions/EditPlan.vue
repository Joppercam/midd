<template>
    <SuperAdminLayout>
        <Head title="Editar Plan de Suscripción" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Editar Plan de Suscripción</h1>
                <p class="text-gray-600">Modifica las características de "{{ plan.name }}"</p>
            </div>

            <!-- Form -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <form @submit.prevent="submit" class="space-y-6 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Plan Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">
                                Nombre del Plan
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                placeholder="Ej: Plan Básico"
                            />
                            <div v-if="form.errors.name" class="mt-2 text-sm text-red-600">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <!-- Plan Code -->
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700">
                                Código del Plan
                            </label>
                            <input
                                id="code"
                                v-model="form.code"
                                type="text"
                                required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                placeholder="BASIC"
                            />
                            <div v-if="form.errors.code" class="mt-2 text-sm text-red-600">
                                {{ form.errors.code }}
                            </div>
                        </div>

                        <!-- Monthly Price -->
                        <div>
                            <label for="monthly_price" class="block text-sm font-medium text-gray-700">
                                Precio Mensual (CLP)
                            </label>
                            <input
                                id="monthly_price"
                                v-model="form.monthly_price"
                                type="number"
                                min="0"
                                step="100"
                                required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                placeholder="19990"
                            />
                            <div v-if="form.errors.monthly_price" class="mt-2 text-sm text-red-600">
                                {{ form.errors.monthly_price }}
                            </div>
                        </div>

                        <!-- Annual Price -->
                        <div>
                            <label for="annual_price" class="block text-sm font-medium text-gray-700">
                                Precio Anual (CLP)
                            </label>
                            <input
                                id="annual_price"
                                v-model="form.annual_price"
                                type="number"
                                min="0"
                                step="100"
                                required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                placeholder="199900"
                            />
                            <div v-if="form.errors.annual_price" class="mt-2 text-sm text-red-600">
                                {{ form.errors.annual_price }}
                            </div>
                        </div>

                        <!-- Trial Days -->
                        <div>
                            <label for="trial_days" class="block text-sm font-medium text-gray-700">
                                Días de Prueba
                            </label>
                            <input
                                id="trial_days"
                                v-model="form.trial_days"
                                type="number"
                                min="0"
                                required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                placeholder="30"
                            />
                            <div v-if="form.errors.trial_days" class="mt-2 text-sm text-red-600">
                                {{ form.errors.trial_days }}
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Estado del Plan
                            </label>
                            <div class="flex items-center">
                                <input
                                    id="is_active"
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                />
                                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                    Plan activo y disponible para nuevas suscripciones
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">
                            Descripción
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                            placeholder="Describe las características de este plan..."
                        ></textarea>
                        <div v-if="form.errors.description" class="mt-2 text-sm text-red-600">
                            {{ form.errors.description }}
                        </div>
                    </div>

                    <!-- Features -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            Características del Plan
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <input
                                    id="api_access"
                                    v-model="form.features.api_access"
                                    type="checkbox"
                                    class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                />
                                <label for="api_access" class="ml-2 block text-sm text-gray-900">
                                    Acceso a API
                                </label>
                            </div>

                            <div class="flex items-center">
                                <input
                                    id="multi_branch"
                                    v-model="form.features.multi_branch"
                                    type="checkbox"
                                    class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                />
                                <label for="multi_branch" class="ml-2 block text-sm text-gray-900">
                                    Múltiples Sucursales
                                </label>
                            </div>

                            <div class="flex items-center">
                                <input
                                    id="advanced_reports"
                                    v-model="form.features.advanced_reports"
                                    type="checkbox"
                                    class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                />
                                <label for="advanced_reports" class="ml-2 block text-sm text-gray-900">
                                    Reportes Avanzados
                                </label>
                            </div>

                            <div class="flex items-center">
                                <input
                                    id="priority_support"
                                    v-model="form.features.priority_support"
                                    type="checkbox"
                                    class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                />
                                <label for="priority_support" class="ml-2 block text-sm text-gray-900">
                                    Soporte Prioritario
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                        <Link
                            :href="route('super-admin.subscriptions.plans')"
                            class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
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
    plan: Object
})

const form = useForm({
    name: props.plan.name,
    code: props.plan.code,
    description: props.plan.description || '',
    monthly_price: props.plan.monthly_price,
    annual_price: props.plan.annual_price,
    trial_days: props.plan.trial_days,
    is_active: props.plan.is_active,
    features: {
        api_access: props.plan.features?.api_access || false,
        multi_branch: props.plan.features?.multi_branch || false,
        advanced_reports: props.plan.features?.advanced_reports || false,
        priority_support: props.plan.features?.priority_support || false,
    }
})

const submit = () => {
    form.put(route('super-admin.subscriptions.plans.update', props.plan.id))
}
</script>