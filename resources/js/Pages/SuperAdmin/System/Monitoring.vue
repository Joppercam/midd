<template>
    <SuperAdminLayout>
        <Head title="Monitoreo del Sistema" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Monitoreo del Sistema</h1>
                <p class="text-gray-600">Estado en tiempo real del sistema y sus componentes</p>
            </div>

            <!-- System Health Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Estado del Sistema</dt>
                                    <dd class="text-lg font-medium text-gray-900">Operativo</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Base de Datos</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ metrics?.database?.connections || 0 }} conexiones</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Uso de CPU</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ Math.round(metrics?.server?.cpu?.percentage || 0) }}%</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Uso de Memoria</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ Math.round(metrics?.server?.memory?.percentage || 0) }}%</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Metrics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Server Metrics -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Métricas del Servidor
                        </h3>
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Tiempo de actividad</span>
                                <span class="text-sm font-medium text-gray-900">{{ metrics?.server?.uptime || 'Desconocido' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Carga promedio</span>
                                <span class="text-sm font-medium text-gray-900">{{ metrics?.server?.load_average || 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Espacio en disco usado</span>
                                <span class="text-sm font-medium text-gray-900">{{ Math.round(metrics?.storage?.disk?.percentage || 0) }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Núcleos de CPU</span>
                                <span class="text-sm font-medium text-gray-900">{{ metrics?.server?.cpu?.cores || 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Database Metrics -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Métricas de Base de Datos
                        </h3>
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Tamaño de BD</span>
                                <span class="text-sm font-medium text-gray-900">{{ metrics?.database?.size_mb || 0 }} MB</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Consultas lentas</span>
                                <span class="text-sm font-medium text-gray-900">{{ metrics?.database?.slow_queries || 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Tablas totales</span>
                                <span class="text-sm font-medium text-gray-900">{{ metrics?.database?.tables_count || 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Empresas</span>
                                <span class="text-sm font-medium text-gray-900">{{ metrics?.database?.records?.tenants || 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Métricas de Rendimiento
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ Math.round(metrics?.performance?.response_time?.average || 0) }}ms</div>
                            <div class="text-sm text-gray-500">Tiempo de respuesta promedio</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ metrics?.performance?.requests?.per_hour || 0 }}</div>
                            <div class="text-sm text-gray-500">Solicitudes por hora</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ Math.round(metrics?.performance?.api?.error_rate || 0) }}%</div>
                            <div class="text-sm text-gray-500">Tasa de error API</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Actions -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Acciones del Sistema
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button
                            @click="clearCache"
                            :disabled="processing.cache"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {{ processing.cache ? 'Limpiando...' : 'Limpiar Caché' }}
                        </button>

                        <button
                            @click="optimizeSystem"
                            :disabled="processing.optimize"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            {{ processing.optimize ? 'Optimizando...' : 'Optimizar Sistema' }}
                        </button>

                        <Link
                            :href="route('super-admin.system.settings')"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Configuración
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { ref, reactive } from 'vue'

const props = defineProps({
    metrics: Object,
})

const processing = reactive({
    cache: false,
    optimize: false,
})

const clearCache = () => {
    processing.cache = true
    router.post(route('super-admin.system.cache.clear'), {}, {
        onFinish: () => {
            processing.cache = false
        }
    })
}

const optimizeSystem = () => {
    processing.optimize = true
    router.post(route('super-admin.system.optimize'), {}, {
        onFinish: () => {
            processing.optimize = false
        }
    })
}
</script>