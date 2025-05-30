<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gestión de Roles
                </h2>
                <Link
                    v-if="$page.props.auth.permissions.includes('roles.create')"
                    :href="route('roles.create')"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Nuevo Rol
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- System Roles Info -->
                <div class="bg-blue-50 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">
                                    Roles del Sistema
                                </h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>Los roles marcados como "Sistema" son esenciales para el funcionamiento de CrecePyme y no pueden ser eliminados.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Roles Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="role in roles"
                        :key="role.id"
                        class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition-shadow"
                    >
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        {{ role.name }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ getRoleDescription(role.name) }}
                                    </p>
                                </div>
                                <span
                                    v-if="isSystemRole(role.name)"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                                >
                                    Sistema
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="font-medium text-gray-500">Usuarios</dt>
                                    <dd class="mt-1 text-gray-900">{{ role.users_count }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-500">Permisos</dt>
                                    <dd class="mt-1 text-gray-900">{{ role.permissions_count }}</dd>
                                </div>
                            </div>

                            <div class="mt-6 flex items-center justify-between">
                                <Link
                                    :href="route('roles.show', role.id)"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                                >
                                    Ver detalles
                                </Link>
                                <div class="flex items-center space-x-3">
                                    <Link
                                        v-if="$page.props.auth.permissions.includes('roles.edit') && role.name !== 'admin'"
                                        :href="route('roles.edit', role.id)"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        v-if="$page.props.auth.permissions.includes('roles.create')"
                                        @click="duplicateRole(role)"
                                        class="text-sm font-medium text-green-600 hover:text-green-500"
                                    >
                                        Duplicar
                                    </button>
                                    <button
                                        v-if="$page.props.auth.permissions.includes('roles.delete') && !isSystemRole(role.name) && role.users_count === 0"
                                        @click="confirmDelete(role)"
                                        class="text-sm font-medium text-red-600 hover:text-red-500"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create New Role Card -->
                <div
                    v-if="$page.props.auth.permissions.includes('roles.create')"
                    class="mt-6"
                >
                    <Link
                        :href="route('roles.create')"
                        class="block bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-gray-400 transition"
                    >
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span class="mt-2 block text-sm font-medium text-gray-900">
                            Crear nuevo rol personalizado
                        </span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    ¿Estás seguro de que quieres eliminar este rol?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Esta acción no se puede deshacer. El rol "{{ roleToDelete?.name }}" será eliminado permanentemente.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">
                        Cancelar
                    </SecondaryButton>
                    <DangerButton @click="deleteRole">
                        Eliminar Rol
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
    roles: Array,
});

const showDeleteModal = ref(false);
const roleToDelete = ref(null);

const systemRoles = ['admin', 'gerente', 'contador', 'vendedor', 'usuario'];

const isSystemRole = (roleName) => {
    return systemRoles.includes(roleName);
};

const getRoleDescription = (roleName) => {
    const descriptions = {
        admin: 'Acceso completo al sistema',
        gerente: 'Gestión de operaciones y reportes',
        contador: 'Gestión financiera y contable',
        vendedor: 'Ventas y atención al cliente',
        usuario: 'Acceso básico de lectura',
    };
    return descriptions[roleName] || 'Rol personalizado';
};

const confirmDelete = (role) => {
    roleToDelete.value = role;
    showDeleteModal.value = true;
};

const deleteRole = () => {
    router.delete(route('roles.destroy', roleToDelete.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            roleToDelete.value = null;
        },
    });
};

const duplicateRole = (role) => {
    router.post(route('roles.duplicate', role.id));
};
</script>