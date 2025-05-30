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
                    Permisos de {{ user.name }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- User Info Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="h-12 w-12 rounded-full bg-gray-300 flex items-center justify-center">
                                <span class="text-gray-600 font-medium text-lg">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">{{ user.name }}</h3>
                                <p class="text-sm text-gray-500">{{ user.email }}</p>
                                <div class="mt-1">
                                    <span v-for="role in user.roles" :key="role.id"
                                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          :class="getRoleClass(role.name)">
                                        {{ role.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions Form -->
                <form @submit.prevent="submit">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                Permisos Directos
                            </h3>
                            <p class="text-sm text-gray-600 mb-6">
                                Los permisos marcados provienen del rol asignado. Los permisos adicionales se pueden asignar directamente al usuario.
                            </p>

                            <div class="space-y-6">
                                <div v-for="(groupPermissions, group) in permissions" :key="group" class="border-b pb-6 last:border-0">
                                    <h4 class="font-medium text-gray-900 mb-3 capitalize">
                                        {{ getGroupName(group) }}
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <label 
                                            v-for="permission in groupPermissions" 
                                            :key="permission.id"
                                            class="flex items-start"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="permission.name"
                                                v-model="form.permissions"
                                                :disabled="isRolePermission(permission.name)"
                                                class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50"
                                            >
                                            <div class="ml-3">
                                                <span class="text-sm font-medium text-gray-700">
                                                    {{ getPermissionLabel(permission.name) }}
                                                </span>
                                                <span v-if="isRolePermission(permission.name)" class="ml-2 text-xs text-gray-500">
                                                    (Del rol)
                                                </span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 pt-6 border-t">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">
                                            Total de permisos: <span class="font-medium">{{ totalPermissions }}</span>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Permisos del rol: <span class="font-medium">{{ rolePermissionsCount }}</span>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Permisos directos: <span class="font-medium">{{ directPermissionsCount }}</span>
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <Link
                                            :href="route('users.show', user.id)"
                                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
                                        >
                                            Cancelar
                                        </Link>
                                        <PrimaryButton :disabled="form.processing">
                                            <span v-if="form.processing">Guardando...</span>
                                            <span v-else>Guardar Permisos</span>
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Permission Reference -->
                <div class="mt-6 bg-blue-50 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-blue-900 mb-2">
                            Referencia de Permisos
                        </h3>
                        <div class="text-sm text-blue-800 space-y-2">
                            <p><strong>view:</strong> Permite ver y listar registros</p>
                            <p><strong>create:</strong> Permite crear nuevos registros</p>
                            <p><strong>edit:</strong> Permite modificar registros existentes</p>
                            <p><strong>delete:</strong> Permite eliminar registros</p>
                            <p><strong>export:</strong> Permite exportar datos</p>
                            <p><strong>import:</strong> Permite importar datos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    user: Object,
    permissions: Object,
    userPermissions: Array,
});

const form = useForm({
    permissions: [...props.userPermissions],
});

const rolePermissions = computed(() => {
    const allRolePermissions = [];
    props.user.roles.forEach(role => {
        role.permissions.forEach(permission => {
            if (!allRolePermissions.includes(permission.name)) {
                allRolePermissions.push(permission.name);
            }
        });
    });
    return allRolePermissions;
});

const isRolePermission = (permissionName) => {
    return rolePermissions.value.includes(permissionName);
};

const totalPermissions = computed(() => {
    const all = new Set([...rolePermissions.value, ...form.permissions]);
    return all.size;
});

const rolePermissionsCount = computed(() => {
    return rolePermissions.value.length;
});

const directPermissionsCount = computed(() => {
    return form.permissions.filter(p => !isRolePermission(p)).length;
});

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

const getGroupName = (group) => {
    const names = {
        customers: 'Clientes',
        products: 'Productos',
        invoices: 'Facturación',
        payments: 'Pagos',
        suppliers: 'Proveedores',
        expenses: 'Gastos',
        reports: 'Reportes',
        users: 'Usuarios',
        roles: 'Roles',
        'sii-integration': 'Integración SII',
        'bank-reconciliation': 'Conciliación Bancaria',
        backup: 'Respaldos',
        audit: 'Auditoría',
        api: 'API',
    };
    return names[group] || group;
};

const getPermissionLabel = (permission) => {
    const parts = permission.split('.');
    const action = parts[1];
    
    const labels = {
        view: 'Ver',
        create: 'Crear',
        edit: 'Editar',
        delete: 'Eliminar',
        export: 'Exportar',
        import: 'Importar',
        send: 'Enviar',
        configure: 'Configurar',
        manage: 'Gestionar',
        permissions: 'Gestionar permisos',
        impersonate: 'Impersonar',
    };
    
    return labels[action] || action;
};

const submit = () => {
    form.post(route('users.permissions.update', props.user.id));
};
</script>