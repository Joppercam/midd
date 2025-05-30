<template>
    <SuperAdminLayout>
        <Head title="Configuración del Sistema" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Configuración del Sistema</h1>
                <p class="text-gray-600">Gestiona la configuración global del sistema</p>
            </div>

            <!-- Settings Form -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Configuración General
                    </h3>
                    
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- System Name -->
                            <div>
                                <label for="system_name" class="block text-sm font-medium text-gray-700">
                                    Nombre del Sistema
                                </label>
                                <input
                                    id="system_name"
                                    v-model="form.system_name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                            </div>

                            <!-- System Email -->
                            <div>
                                <label for="system_email" class="block text-sm font-medium text-gray-700">
                                    Email del Sistema
                                </label>
                                <input
                                    id="system_email"
                                    v-model="form.system_email"
                                    type="email"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                />
                            </div>

                            <!-- Maintenance Mode -->
                            <div class="flex items-center">
                                <input
                                    id="maintenance_mode"
                                    v-model="form.maintenance_mode"
                                    type="checkbox"
                                    class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                />
                                <label for="maintenance_mode" class="ml-2 block text-sm text-gray-900">
                                    Modo de Mantenimiento
                                </label>
                            </div>

                            <!-- Registration Enabled -->
                            <div class="flex items-center">
                                <input
                                    id="registration_enabled"
                                    v-model="form.registration_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                />
                                <label for="registration_enabled" class="ml-2 block text-sm text-gray-900">
                                    Permitir Registro de Nuevas Empresas
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end pt-6 border-t border-gray-200">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Guardando...' : 'Guardar Configuración' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- System Information -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Información del Sistema
                    </h3>
                    
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Versión de PHP</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ systemInfo.php_version || 'N/A' }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Versión de Laravel</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ systemInfo.laravel_version || 'N/A' }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Base de Datos</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ systemInfo.database_driver || 'N/A' }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Servidor Web</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ systemInfo.web_server || 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    settings: Object,
    systemInfo: Object,
})

const form = useForm({
    system_name: props.settings?.system_name || 'CrecePyme',
    system_email: props.settings?.system_email || 'admin@crecepyme.com',
    maintenance_mode: props.settings?.maintenance_mode || false,
    registration_enabled: props.settings?.registration_enabled || true,
})

const submit = () => {
    form.put(route('super-admin.system.settings.update'))
}
</script>