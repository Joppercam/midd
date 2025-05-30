<template>
    <SuperAdminLayout>
        <Head title="Panel Super Administrador" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Resumen del Sistema</h1>
                <p class="text-gray-600">Monitorea y gestiona tu sistema multi-tenant</p>
            </div>

            <!-- Key Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <StatCard
                    title="Total Empresas"
                    :value="stats.total_tenants"
                    icon="building"
                    color="blue"
                />
                <StatCard
                    title="Empresas Activas"
                    :value="stats.active_tenants"
                    icon="check-circle"
                    color="green"
                />
                <StatCard
                    title="Total Usuarios"
                    :value="stats.total_users"
                    icon="users"
                    color="indigo"
                />
                <StatCard
                    title="Ingresos Mensuales"
                    :value="formatCurrency(stats.mrr)"
                    icon="currency-dollar"
                    color="emerald"
                />
            </div>

            <!-- System Health -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- System Metrics -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Estado del Sistema
                        </h3>
                        <div class="space-y-4">
                            <HealthIndicator
                                label="Base de Datos"
                                :status="metrics.database?.status || 'unknown'"
                                :value="metrics.database?.connections || 0"
                                unit="conexiones"
                            />
                            <HealthIndicator
                                label="Caché"
                                :status="metrics.cache?.driver || 'unknown'"
                                :value="metrics.cache?.hit_rate || 0"
                                unit="% aciertos"
                            />
                            <HealthIndicator
                                label="Colas"
                                :status="metrics.queue?.jobs?.pending > 100 ? 'warning' : 'healthy'"
                                :value="metrics.queue?.jobs?.pending || 0"
                                unit="trabajos pendientes"
                            />
                            <HealthIndicator
                                label="Almacenamiento"
                                :status="metrics.storage?.disk?.percentage > 80 ? 'warning' : 'healthy'"
                                :value="metrics.storage?.disk?.percentage || 0"
                                unit="% usado"
                            />
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Rendimiento
                        </h3>
                        <div class="space-y-4">
                            <MetricItem
                                label="Tiempo de Respuesta Promedio"
                                :value="metrics.performance?.response_time?.average || 0"
                                unit="ms"
                            />
                            <MetricItem
                                label="Solicitudes por Hora"
                                :value="metrics.performance?.requests?.per_hour || 0"
                                unit="req/h"
                            />
                            <MetricItem
                                label="Tasa de Error API"
                                :value="metrics.performance?.api?.error_rate || 0"
                                unit="%"
                            />
                            <MetricItem
                                label="Uso de Memoria"
                                :value="metrics.server?.memory?.percentage || 0"
                                unit="%"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Revenue Chart -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Tendencia de Ingresos
                        </h3>
                        <div class="h-64">
                            <LineChart :data="revenueChart" />
                        </div>
                    </div>
                </div>

                <!-- Tenants Chart -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Crecimiento de Empresas
                        </h3>
                        <div class="h-64">
                            <BarChart :data="tenantsChart" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities and Top Tenants -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Activities -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Actividades Recientes
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="activity in recentActivities"
                                :key="activity.id"
                                class="flex items-start space-x-3"
                            >
                                <div class="flex-shrink-0">
                                    <div class="h-2 w-2 bg-red-400 rounded-full mt-2"></div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-gray-900">{{ activity.description }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ formatDate(activity.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Tenants -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Empresas Más Activas
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="tenant in topTenants"
                                :key="tenant.id"
                                class="flex items-center justify-between"
                            >
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ tenant.name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ tenant.users_count }} usuarios, {{ tenant.tax_documents_count }} documentos
                                    </p>
                                </div>
                                <Link
                                    :href="route('super-admin.tenants.show', tenant.id)"
                                    class="text-red-600 hover:text-red-500 text-sm"
                                >
                                    Ver
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
import { Head, Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import StatCard from '@/Components/SuperAdmin/StatCard.vue'
import HealthIndicator from '@/Components/SuperAdmin/HealthIndicator.vue'
import MetricItem from '@/Components/SuperAdmin/MetricItem.vue'
import LineChart from '@/Components/SuperAdmin/LineChart.vue'
import BarChart from '@/Components/SuperAdmin/BarChart.vue'

const props = defineProps({
    stats: Object,
    metrics: Object,
    recentActivities: Array,
    topTenants: Array,
    revenueChart: Array,
    tenantsChart: Array,
})

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP'
    }).format(amount)
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}
</script>