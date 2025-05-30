<template>
    <AuthenticatedLayout>
        <template #header>
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
                    Crear Usuario
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Name -->
                        <div>
                            <InputLabel for="name" value="Nombre Completo" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.name"
                                required
                                autofocus
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- Email -->
                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1 block w-full"
                                v-model="form.email"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <!-- Password -->
                        <div>
                            <InputLabel for="password" value="Contraseña" />
                            <TextInput
                                id="password"
                                type="password"
                                class="mt-1 block w-full"
                                v-model="form.password"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                            <p class="mt-1 text-sm text-gray-600">
                                Mínimo 8 caracteres
                            </p>
                        </div>

                        <!-- Password Confirmation -->
                        <div>
                            <InputLabel for="password_confirmation" value="Confirmar Contraseña" />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                v-model="form.password_confirmation"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <!-- Role -->
                        <div>
                            <InputLabel for="role" value="Rol" />
                            <select
                                id="role"
                                v-model="form.role"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Seleccionar rol...</option>
                                <option v-for="role in roles" :key="role.id" :value="role.name">
                                    {{ role.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.role" />
                            <div v-if="form.role" class="mt-2">
                                <p class="text-sm text-gray-600">
                                    {{ getRoleDescription(form.role) }}
                                </p>
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div>
                            <label class="flex items-center">
                                <Checkbox v-model:checked="form.active" />
                                <span class="ml-2 text-sm text-gray-600">
                                    Usuario activo
                                </span>
                            </label>
                            <p class="mt-1 text-sm text-gray-500">
                                Los usuarios inactivos no pueden acceder al sistema
                            </p>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                            <Link
                                :href="route('users.index')"
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
                                    Creando...
                                </span>
                                <span v-else>Crear Usuario</span>
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Help Section -->
                <div class="mt-6 bg-blue-50 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-blue-900 mb-2">
                            Roles y Permisos
                        </h3>
                        <div class="space-y-3 text-sm text-blue-800">
                            <div>
                                <span class="font-semibold">Administrador:</span> Acceso completo al sistema, gestión de usuarios y configuración.
                            </div>
                            <div>
                                <span class="font-semibold">Gerente:</span> Acceso a reportes, gestión de clientes y productos, facturación.
                            </div>
                            <div>
                                <span class="font-semibold">Contador:</span> Acceso a facturación, reportes financieros y conciliación bancaria.
                            </div>
                            <div>
                                <span class="font-semibold">Vendedor:</span> Creación de facturas, gestión de clientes y productos limitada.
                            </div>
                            <div>
                                <span class="font-semibold">Usuario:</span> Acceso básico de solo lectura.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    roles: Array,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    active: true,
});

const submit = () => {
    form.post(route('users.store'));
};

const getRoleDescription = (role) => {
    const descriptions = {
        admin: 'Acceso completo al sistema con todos los permisos',
        gerente: 'Gestión de operaciones, reportes y supervisión',
        contador: 'Gestión financiera, facturación y reportes contables',
        vendedor: 'Creación de facturas y gestión básica de clientes',
        usuario: 'Acceso de solo lectura a la información',
    };
    return descriptions[role] || '';
};
</script>