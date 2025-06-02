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
                    Permisos: {{ user.name }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Gestión de Permisos</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Configura los permisos específicos para este usuario. Los permisos se agrupan por módulo.
                            </p>
                        </div>

                        <!-- Permission Groups -->
                        <div class="space-y-6">
                            <div
                                v-for="(permissions, group) in allPermissions"
                                :key="group"
                                class="border border-gray-200 rounded-lg p-4"
                            >
                                <h4 class="text-md font-medium text-gray-900 mb-3 capitalize">
                                    {{ group }}
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <label
                                        v-for="permission in permissions"
                                        :key="permission.id"
                                        class="flex items-center"
                                    >
                                        <Checkbox
                                            :checked="form.permissions.includes(permission.name)"
                                            @change="togglePermission(permission.name)"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">
                                            {{ permission.name }}
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t mt-6">
                            <Link
                                :href="route('users.show', user.id)"
                                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                            >
                                Cancelar
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                <span v-if="form.processing" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Guardando...
                                </span>
                                <span v-else>Guardar Permisos</span>
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    user: Object,
    allPermissions: Object,
    userPermissions: Array,
});

const form = useForm({
    permissions: [...props.userPermissions],
});

const togglePermission = (permissionName) => {
    const index = form.permissions.indexOf(permissionName);
    if (index > -1) {
        form.permissions.splice(index, 1);
    } else {
        form.permissions.push(permissionName);
    }
};

const submit = () => {
    form.post(route('users.permissions.update', props.user.id));
};
</script>