<template>
  <Head :title="`Rol: ${formatRoleName(role.name)}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Rol: {{ formatRoleName(role.name) }}
        </h2>
        <div class="flex space-x-2">
          <Link
            :href="route('roles.index')"
            class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver
          </Link>
          <Link
            v-if="!role.is_system"
            :href="route('roles.edit', role.id)"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
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
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Información general del rol -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="flex items-start space-x-6">
              <div class="flex-shrink-0">
                <div :class="[
                  'h-20 w-20 rounded-full flex items-center justify-center text-white font-bold text-2xl',
                  role.is_system ? 'bg-blue-500' : 'bg-gray-500'
                ]">
                  {{ role.name.charAt(0).toUpperCase() }}
                </div>
              </div>
              
              <div class="flex-1 min-w-0">
                <h3 class="text-2xl font-bold text-gray-900">
                  {{ formatRoleName(role.name) }}
                </h3>
                <div class="mt-2 flex items-center space-x-4">
                  <span :class="[
                    'inline-flex px-3 py-1 text-sm font-semibold rounded-full',
                    role.is_system 
                      ? 'bg-blue-100 text-blue-800' 
                      : 'bg-gray-100 text-gray-800'
                  ]">
                    {{ role.is_system ? 'Rol del Sistema' : 'Rol Personalizado' }}
                  </span>
                  <span class="text-sm text-gray-500">
                    Guard: {{ role.guard_name }}
                  </span>
                </div>
                
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Usuarios Asignados</p>
                    <p class="text-2xl font-bold text-gray-900">{{ users.length }}</p>
                  </div>
                  <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Permisos</p>
                    <p class="text-2xl font-bold text-gray-900">{{ rolePermissions.length }}</p>
                  </div>
                  <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Fecha de Creación</p>
                    <p class="text-sm text-gray-900">{{ formatDate(role.created_at) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Usuarios asignados a este rol -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
              Usuarios con este Rol ({{ users.length }})
            </h3>
          </div>
          
          <div v-if="users.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Usuario
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Email
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Estado
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Último Acceso
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-gray-500 flex items-center justify-center text-white font-medium">
                          {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ user.email }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span :class="[
                      'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                      user.email_verified_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                    ]">
                      {{ user.email_verified_at ? 'Activo' : 'Pendiente' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                    {{ formatDate(user.updated_at) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <div v-else class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay usuarios</h3>
            <p class="mt-1 text-sm text-gray-500">Ningún usuario tiene asignado este rol actualmente.</p>
          </div>
        </div>

        <!-- Permisos del rol -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
              Permisos del Rol ({{ rolePermissions.length }})
            </h3>
          </div>
          
          <div v-if="Object.keys(permissions).length" class="p-6">
            <div class="space-y-6">
              <div v-for="(groupPermissions, groupName) in permissions" :key="groupName" class="border border-gray-200 rounded-lg p-4">
                <h4 class="text-md font-medium text-gray-900 capitalize mb-3">
                  {{ formatGroupName(groupName) }}
                </h4>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div
                    v-for="permission in groupPermissions"
                    :key="permission.id"
                    class="flex items-center"
                  >
                    <div :class="[
                      'flex items-center justify-center w-4 h-4 rounded-sm mr-2',
                      rolePermissions.includes(permission.id) 
                        ? 'bg-green-500 text-white' 
                        : 'bg-gray-200'
                    ]">
                      <svg v-if="rolePermissions.includes(permission.id)" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <span :class="[
                      'text-sm',
                      rolePermissions.includes(permission.id) 
                        ? 'text-gray-900 font-medium' 
                        : 'text-gray-400'
                    ]">
                      {{ formatPermissionName(permission.name) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div v-else class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Sin permisos</h3>
            <p class="mt-1 text-sm text-gray-500">Este rol no tiene permisos asignados.</p>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  role: Object,
  permissions: Object,
  rolePermissions: Array,
  users: Array,
});

// Methods
const formatRoleName = (name) => {
  return name.split('-').map(word => 
    word.charAt(0).toUpperCase() + word.slice(1)
  ).join(' ');
};

const formatDate = (date) => {
  if (!date) return 'Nunca';
  return new Date(date).toLocaleDateString('es-CL');
};

const formatGroupName = (groupName) => {
  const groupNames = {
    'hrm': 'Recursos Humanos',
    'pos': 'Punto de Venta',
    'roles': 'Gestión de Roles',
    'users': 'Gestión de Usuarios',
    'customers': 'Clientes',
    'products': 'Productos',
    'sales': 'Ventas',
    'purchases': 'Compras',
    'inventory': 'Inventario',
    'reports': 'Reportes',
    'settings': 'Configuración',
    'audit': 'Auditoría',
  };
  return groupNames[groupName] || groupName.charAt(0).toUpperCase() + groupName.slice(1);
};

const formatPermissionName = (permissionName) => {
  const parts = permissionName.split('.');
  const action = parts[parts.length - 1];
  
  const actionNames = {
    'view': 'Ver',
    'create': 'Crear',
    'edit': 'Editar',
    'delete': 'Eliminar',
    'access': 'Acceder',
    'manage': 'Gestionar',
  };
  
  return actionNames[action] || action.charAt(0).toUpperCase() + action.slice(1);
};
</script>