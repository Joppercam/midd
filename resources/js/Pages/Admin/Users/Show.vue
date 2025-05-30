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
                        Detalles del Usuario
                    </h2>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        v-if="$page.props.auth.permissions.includes('users.permissions')"
                        :href="route('users.permissions', user.id)"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        Permisos
                    </Link>
                    <Link
                        v-if="$page.props.auth.permissions.includes('users.edit')"
                        :href="route('users.edit', user.id)"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Editar
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- User Profile Card -->
                    <div class="lg:col-span-1">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="text-center">
                                    <div class="mx-auto h-24 w-24 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-gray-600 font-bold text-3xl">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </span>
                                    </div>
                                    <h3 class="mt-4 text-lg font-medium text-gray-900">
                                        {{ user.name }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ user.email }}
                                    </p>
                                    <div class="mt-3">
                                        <span v-for="role in user.roles" :key="role.id"
                                              class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium"
                                              :class="getRoleClass(role.name)">
                                            {{ role.name }}
                                        </span>
                                    </div>
                                    <div class="mt-3">
                                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium"
                                              :class="user.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                            {{ user.active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-6 border-t pt-6 space-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">ID de Usuario</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ user.id }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Creado</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(user.created_at) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Último Acceso</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            {{ user.last_login_at ? formatDate(user.last_login_at) : 'Nunca' }}
                                        </dd>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-6 space-y-2">
                                    <button
                                        v-if="$page.props.auth.permissions.includes('users.impersonate') && user.id !== $page.props.auth.user.id"
                                        @click="impersonate"
                                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Impersonar Usuario
                                    </button>
                                    <button
                                        v-if="$page.props.auth.permissions.includes('users.delete') && user.id !== $page.props.auth.user.id"
                                        @click="confirmDelete"
                                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Eliminar Usuario
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Stats and Activity -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Stats -->
                        <div class="grid grid-cols-2 gap-6">
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-6">
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Total de Ingresos
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                        {{ stats.total_logins }}
                                    </dd>
                                </div>
                            </div>
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-6">
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Facturas Creadas
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                        {{ stats.created_invoices }}
                                    </dd>
                                </div>
                            </div>
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-6">
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Clientes Creados
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                        {{ stats.created_customers }}
                                    </dd>
                                </div>
                            </div>
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-6">
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Permisos Asignados
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                        {{ user.permissions.length }}
                                    </dd>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">
                                    Actividad Reciente
                                </h3>
                                <div v-if="activities.length > 0" class="flow-root">
                                    <ul role="list" class="-mb-8">
                                        <li v-for="(activity, index) in activities" :key="activity.id">
                                            <div class="relative pb-8">
                                                <span v-if="index !== activities.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                <div class="relative flex space-x-3">
                                                    <div>
                                                        <span class="h-8 w-8 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white">
                                                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        </span>
                                                    </div>
                                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                        <div>
                                                            <p class="text-sm text-gray-500">
                                                                {{ activity.description }}
                                                            </p>
                                                        </div>
                                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                            {{ formatRelativeTime(activity.created_at) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                <div v-else class="text-center py-4">
                                    <p class="text-gray-500">No hay actividad reciente</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    ¿Estás seguro de que quieres eliminar este usuario?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Esta acción no se puede deshacer. El usuario "{{ user.name }}" será eliminado permanentemente.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">
                        Cancelar
                    </SecondaryButton>
                    <DangerButton @click="deleteUser">
                        Eliminar Usuario
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    user: Object,
    activities: Array,
    stats: Object,
});

const showDeleteModal = ref(false);

const confirmDelete = () => {
    showDeleteModal.value = true;
};

const deleteUser = () => {
    router.delete(route('users.destroy', props.user.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
        },
    });
};

const impersonate = () => {
    if (confirm(`¿Quieres ver el sistema como ${props.user.name}?`)) {
        router.post(route('users.impersonate', props.user.id));
    }
};

const getRoleClass = (role) => {
    const classes = {
        admin: 'bg-purple-100 text-purple-800',
        gerente: 'bg-blue-100 text-blue-800',
        contador: 'bg-green-100 text-green-800',
        vendedor: 'bg-yellow-100 text-yellow-800',
        usuario: 'bg-gray-100 text-gray-800',
    };
    return classes[role] || 'bg-gray-100 text-gray-800';
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatRelativeTime = (date) => {
    const now = new Date();
    const past = new Date(date);
    const diffInSeconds = Math.floor((now - past) / 1000);
    
    if (diffInSeconds < 60) return 'hace unos segundos';
    if (diffInSeconds < 3600) return `hace ${Math.floor(diffInSeconds / 60)} minutos`;
    if (diffInSeconds < 86400) return `hace ${Math.floor(diffInSeconds / 3600)} horas`;
    if (diffInSeconds < 604800) return `hace ${Math.floor(diffInSeconds / 86400)} días`;
    
    return formatDate(date);
};
</script>