<template>
  <Head title="Gestión de Roles" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Gestión de Roles
        </h2>
        <Link
          :href="route('roles.create')"
          class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Rol
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <!-- Estadísticas -->
          <div class="p-6 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div class="bg-blue-50 p-4 rounded-lg">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total de Roles</p>
                    <p class="text-2xl font-bold text-gray-900">{{ roles.length }}</p>
                  </div>
                </div>
              </div>

              <div class="bg-green-50 p-4 rounded-lg">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Roles del Sistema</p>
                    <p class="text-2xl font-bold text-gray-900">{{ systemRolesCount }}</p>
                  </div>
                </div>
              </div>

              <div class="bg-purple-50 p-4 rounded-lg">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Roles Personalizados</p>
                    <p class="text-2xl font-bold text-gray-900">{{ customRolesCount }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Lista de roles -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Nombre del Rol
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Usuarios
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Permisos
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tipo
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Fecha de Creación
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div :class="[
                          'h-10 w-10 rounded-full flex items-center justify-center text-white font-medium',
                          role.is_system ? 'bg-blue-500' : 'bg-gray-500'
                        ]">
                          {{ role.name.charAt(0).toUpperCase() }}
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">
                          {{ formatRoleName(role.name) }}
                        </div>
                        <div class="text-sm text-gray-500">
                          {{ role.guard_name }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                      {{ role.users_count }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                      {{ role.permissions_count }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span :class="[
                      'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                      role.is_system 
                        ? 'bg-blue-100 text-blue-800' 
                        : 'bg-gray-100 text-gray-800'
                    ]">
                      {{ role.is_system ? 'Sistema' : 'Personalizado' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                    {{ formatDate(role.created_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                      <Link
                        :href="route('roles.show', role.id)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Ver
                      </Link>
                      <Link
                        v-if="!role.is_system"
                        :href="route('roles.edit', role.id)"
                        class="text-yellow-600 hover:text-yellow-900"
                      >
                        Editar
                      </Link>
                      <button
                        @click="duplicateRole(role)"
                        class="text-green-600 hover:text-green-900"
                      >
                        Duplicar
                      </button>
                      <button
                        v-if="!role.is_system && role.users_count === 0"
                        @click="deleteRole(role)"
                        class="text-red-600 hover:text-red-900"
                      >
                        Eliminar
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Empty state -->
          <div v-if="!roles.length" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay roles</h3>
            <p class="mt-1 text-sm text-gray-500">Comienza creando tu primer rol personalizado.</p>
            <div class="mt-6">
              <Link
                :href="route('roles.create')"
                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Rol
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  roles: Array,
});

// Computed properties
const systemRolesCount = computed(() => {
  return props.roles.filter(role => role.is_system).length;
});

const customRolesCount = computed(() => {
  return props.roles.filter(role => !role.is_system).length;
});

// Methods
const formatRoleName = (name) => {
  return name.split('-').map(word => 
    word.charAt(0).toUpperCase() + word.slice(1)
  ).join(' ');
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-CL');
};

const duplicateRole = (role) => {
  if (confirm(`¿Duplicar el rol "${formatRoleName(role.name)}"?`)) {
    router.post(route('roles.duplicate', role.id), {}, {
      preserveScroll: true,
    });
  }
};

const deleteRole = (role) => {
  if (confirm(`¿Estás seguro de eliminar el rol "${formatRoleName(role.name)}"? Esta acción no se puede deshacer.`)) {
    router.delete(route('roles.destroy', role.id), {
      preserveScroll: true,
    });
  }
};
</script>