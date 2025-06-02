<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <Link
                        :href="route('users.index')"
                        class="text-gray-400 hover:text-gray-600 mr-4"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ user.name }}
                    </h2>
                </div>
                <div class="flex space-x-2">
                    <Link
                        :href="route('users.edit', user.id)"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Editar
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- User Info -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Usuario</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                <p class="mt-1 text-sm text-gray-900">{{ user.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <p class="mt-1 text-sm text-gray-900">{{ user.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Roles</label>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"
                                    >
                                        {{ role.name }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Estado</label>
                                <span
                                    :class="[
                                        'mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                        user.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ user.active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div v-if="activities && activities.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Actividad Reciente</h3>
                        <div class="space-y-4">
                            <div
                                v-for="activity in activities"
                                :key="activity.id"
                                class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg"
                            >
                                <div class="flex-1">
                                    <p class="text-sm text-gray-900">{{ activity.description }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ formatDate(activity.created_at) }}
                                    </p>
                                </div>
                            </div>
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

const props = defineProps({
    user: Object,
    activities: Array,
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