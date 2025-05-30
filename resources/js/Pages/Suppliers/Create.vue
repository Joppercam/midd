<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Crear Proveedor
                </h2>
                <Link
                    :href="route('suppliers.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Volver al listado
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Información Básica -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información Básica</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="type" value="Tipo de Proveedor" />
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="person">Persona Natural</option>
                                        <option value="company">Empresa</option>
                                    </select>
                                    <InputError :message="form.errors.type" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="rut" value="RUT" />
                                    <TextInput
                                        id="rut"
                                        v-model="form.rut"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="12.345.678-9"
                                        @input="formatRut"
                                        required
                                    />
                                    <InputError :message="form.errors.rut" class="mt-2" />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel 
                                        for="name" 
                                        :value="form.type === 'company' ? 'Razón Social' : 'Nombre Completo'" 
                                    />
                                    <TextInput
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError :message="form.errors.name" class="mt-2" />
                                </div>

                                <div v-if="form.type === 'company'" class="md:col-span-2">
                                    <InputLabel for="business_name" value="Giro Comercial" />
                                    <TextInput
                                        id="business_name"
                                        v-model="form.business_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="Actividad comercial principal"
                                    />
                                    <InputError :message="form.errors.business_name" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Información de Contacto -->
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Contacto</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="email" value="Correo Electrónico" />
                                    <TextInput
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError :message="form.errors.email" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="phone" value="Teléfono" />
                                    <TextInput
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        class="mt-1 block w-full"
                                        placeholder="+56 9 1234 5678"
                                    />
                                    <InputError :message="form.errors.phone" class="mt-2" />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="address" value="Dirección" />
                                    <TextInput
                                        id="address"
                                        v-model="form.address"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError :message="form.errors.address" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="city" value="Ciudad" />
                                    <TextInput
                                        id="city"
                                        v-model="form.city"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError :message="form.errors.city" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="commune" value="Comuna" />
                                    <TextInput
                                        id="commune"
                                        v-model="form.commune"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError :message="form.errors.commune" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="region" value="Región" />
                                    <TextInput
                                        id="region"
                                        v-model="form.region"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError :message="form.errors.region" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Información Comercial -->
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información Comercial</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="payment_terms" value="Condiciones de Pago" />
                                    <select
                                        id="payment_terms"
                                        v-model="form.payment_terms"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="immediate">Contado</option>
                                        <option value="15_days">15 días</option>
                                        <option value="30_days">30 días</option>
                                        <option value="60_days">60 días</option>
                                        <option value="90_days">90 días</option>
                                    </select>
                                    <InputError :message="form.errors.payment_terms" class="mt-2" />
                                </div>

                                <div class="flex items-center">
                                    <input
                                        id="is_active"
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm"
                                    />
                                    <label for="is_active" class="ml-2 text-sm text-gray-600">
                                        Proveedor activo
                                    </label>
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="notes" value="Notas" />
                                    <textarea
                                        id="notes"
                                        v-model="form.notes"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        placeholder="Notas adicionales sobre el proveedor..."
                                    ></textarea>
                                    <InputError :message="form.errors.notes" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                            <Link
                                :href="route('suppliers.index')"
                                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
                            >
                                Cancelar
                            </Link>
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                Crear Proveedor
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
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    type: 'company',
    rut: '',
    name: '',
    business_name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    commune: '',
    region: '',
    payment_terms: '30_days',
    notes: '',
    is_active: true
});

const formatRut = () => {
    let value = form.rut.replace(/\./g, '').replace('-', '');
    if (value.match(/^(\d{2})(\d{3})(\d{3})(\w{1})$/)) {
        value = value.replace(/^(\d{2})(\d{3})(\d{3})(\w{1})$/, '$1.$2.$3-$4');
    } else if (value.match(/^(\d{1})(\d{3})(\d{3})(\w{1})$/)) {
        value = value.replace(/^(\d{1})(\d{3})(\d{3})(\w{1})$/, '$1.$2.$3-$4');
    }
    form.rut = value;
};

const submit = () => {
    form.post(route('suppliers.store'));
};
</script>