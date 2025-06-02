<template>
  <Head title="Crear Rol" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Crear Nuevo Rol
        </h2>
        <Link
          :href="route('roles.index')"
          class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Volver
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Información básica del rol -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Rol</h3>
              
              <div class="grid grid-cols-1 gap-6">
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700">
                    Nombre del Rol
                  </label>
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-500': errors.name }"
                    placeholder="Ej: Editor de Contenido, Supervisor de Ventas"
                    required
                  />
                  <p v-if="errors.name" class="mt-2 text-sm text-red-600">{{ errors.name }}</p>
                  <p class="mt-2 text-sm text-gray-500">
                    El nombre del rol debe ser descriptivo y único. Se convertirá automáticamente a formato slug.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Permisos -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Permisos del Rol</h3>
                <div class="flex space-x-2">
                  <button
                    type="button"
                    @click="selectAllPermissions"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                  >
                    Seleccionar Todos
                  </button>
                  <span class="text-gray-300">|</span>
                  <button
                    type="button"
                    @click="clearAllPermissions"
                    class="text-sm text-gray-600 hover:text-gray-500"
                  >
                    Limpiar Todo
                  </button>
                </div>
              </div>

              <div class="space-y-6">
                <div v-for="(groupPermissions, groupName) in permissions" :key="groupName" class="border border-gray-200 rounded-lg p-4">
                  <div class="flex items-center justify-between mb-3">
                    <h4 class="text-md font-medium text-gray-900 capitalize">
                      {{ formatGroupName(groupName) }}
                    </h4>
                    <div class="flex space-x-2">
                      <button
                        type="button"
                        @click="selectGroupPermissions(groupName)"
                        class="text-xs text-indigo-600 hover:text-indigo-500"
                      >
                        Seleccionar Grupo
                      </button>
                      <button
                        type="button"
                        @click="clearGroupPermissions(groupName)"
                        class="text-xs text-gray-600 hover:text-gray-500"
                      >
                        Limpiar Grupo
                      </button>
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                      v-for="permission in groupPermissions"
                      :key="permission.id"
                      class="flex items-center"
                    >
                      <input
                        :id="`permission-${permission.id}`"
                        v-model="form.permissions"
                        :value="permission.id"
                        type="checkbox"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                      />
                      <label
                        :for="`permission-${permission.id}`"
                        class="ml-2 text-sm text-gray-700 cursor-pointer"
                      >
                        {{ formatPermissionName(permission.name) }}
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <p v-if="errors.permissions" class="mt-2 text-sm text-red-600">{{ errors.permissions }}</p>
              
              <!-- Resumen de permisos seleccionados -->
              <div v-if="form.permissions.length" class="mt-4 p-3 bg-indigo-50 rounded-lg">
                <p class="text-sm text-indigo-700">
                  <strong>{{ form.permissions.length }}</strong> permisos seleccionados
                </p>
              </div>
            </div>
          </div>

          <!-- Botones de acción -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center justify-end space-x-3">
                <Link
                  :href="route('roles.index')"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
                >
                  Cancelar
                </Link>
                <button
                  type="submit"
                  :disabled="processing"
                  class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                >
                  <svg v-if="processing" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ processing ? 'Creando...' : 'Crear Rol' }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  permissions: Object,
  errors: Object,
});

// Form setup
const form = useForm({
  name: '',
  permissions: [],
});

// Methods
const submit = () => {
  form.post(route('roles.store'));
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

const selectAllPermissions = () => {
  const allPermissionIds = [];
  Object.values(props.permissions).forEach(group => {
    group.forEach(permission => {
      allPermissionIds.push(permission.id);
    });
  });
  form.permissions = allPermissionIds;
};

const clearAllPermissions = () => {
  form.permissions = [];
};

const selectGroupPermissions = (groupName) => {
  const groupPermissionIds = props.permissions[groupName].map(p => p.id);
  const currentPermissions = [...form.permissions];
  
  groupPermissionIds.forEach(id => {
    if (!currentPermissions.includes(id)) {
      currentPermissions.push(id);
    }
  });
  
  form.permissions = currentPermissions;
};

const clearGroupPermissions = (groupName) => {
  const groupPermissionIds = props.permissions[groupName].map(p => p.id);
  form.permissions = form.permissions.filter(id => !groupPermissionIds.includes(id));
};
</script>