<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link
                    :href="route('users.show', user.id)"
                    class="text-gray-400 hover:text-gray-600 mr-4"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Actividad: {{ user.name }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Registro de Actividad</h3>
                        
                        <div v-if="activities.data && activities.data.length > 0" class="space-y-4">
                            <div
                                v-for="activity in activities.data"
                                :key="activity.id"
                                class="border border-gray-200 rounded-lg p-4"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-900">{{ activity.description }}</p>
                                        <div v-if="activity.properties" class="mt-2 text-xs text-gray-600">
                                            <pre class="bg-gray-50 p-2 rounded text-xs overflow-x-auto">{{ JSON.stringify(activity.properties, null, 2) }}</pre>
                                        </div>
                                    </div>
                                    <div class="text-right text-xs text-gray-500 ml-4">
                                        <p>{{ formatDate(activity.created_at) }}</p>
                                        <p v-if="activity.ip_address" class="mt-1">IP: {{ activity.ip_address }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Sin actividad</h3>
                            <p class="mt-1 text-sm text-gray-500">Este usuario no tiene actividad registrada.</p>
                        </div>

                        <!-- Pagination -->
                        <div v-if="activities.links && activities.last_page > 1" class="mt-6">
                            <Pagination :links="activities.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/UI/Pagination.vue';

const props = defineProps({
    user: Object,
    activities: Object,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>