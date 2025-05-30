<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Cliente
                </h2>
                <Link
                    :href="route('customers.index')"
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
                        <!-- Tipo de Cliente -->
                        <div>
                            <InputLabel for="type" value="Tipo de Cliente" />
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

                        <!-- RUT -->
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

                        <!-- Nombre/Razón Social -->
                        <div>
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

                        <!-- Giro (solo para empresas) -->
                        <div v-if="form.type === 'company'">
                            <InputLabel for="business_name" value="Giro" />
                            <TextInput
                                id="business_name"
                                v-model="form.business_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.business_name" class="mt-2" />
                        </div>

                        <!-- Información de Contacto -->
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                Información de Contacto
                            </h3>

                            <div class="space-y-4">
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

                                <div>
                                    <InputLabel for="address" value="Dirección" />
                                    <TextInput
                                        id="address"
                                        v-model="form.address"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError :message="form.errors.address" class="mt-2" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
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
                                </div>
                            </div>
                        </div>

                        <!-- Información Comercial -->
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                Información Comercial
                            </h3>

                            <div class="space-y-4">
                                <div>
                                    <InputLabel for="credit_limit" value="Límite de Crédito" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input
                                            type="number"
                                            id="credit_limit"
                                            v-model="form.credit_limit"
                                            class="block w-full pl-7 pr-12 border-gray-300 rounded-md"
                                            placeholder="0"
                                            min="0"
                                            step="1000"
                                        />
                                    </div>
                                    <InputError :message="form.errors.credit_limit" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="payment_terms" value="Condiciones de Pago" />
                                    <select
                                        id="payment_terms"
                                        v-model="form.payment_terms"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option value="immediate">Contado</option>
                                        <option value="15_days">15 días</option>
                                        <option value="30_days">30 días</option>
                                        <option value="60_days">60 días</option>
                                        <option value="90_days">90 días</option>
                                    </select>
                                    <InputError :message="form.errors.payment_terms" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="notes" value="Notas" />
                                    <textarea
                                        id="notes"
                                        v-model="form.notes"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        placeholder="Notas adicionales sobre el cliente..."
                                    ></textarea>
                                    <InputError :message="form.errors.notes" class="mt-2" />
                                </div>

                                <div class="flex items-center">
                                    <input
                                        id="is_active"
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm"
                                    />
                                    <label for="is_active" class="ml-2 text-sm text-gray-600">
                                        Cliente activo
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                            <Link
                                :href="route('customers.index')"
                                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                            >
                                Cancelar
                            </Link>
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                Actualizar Cliente
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

const props = defineProps({
    customer: {
        type: Object,
        required: true
    }
});

const form = useForm({
    type: props.customer.type,
    rut: props.customer.rut,
    name: props.customer.name,
    business_name: props.customer.business_name || '',
    email: props.customer.email || '',
    phone: props.customer.phone || '',
    address: props.customer.address || '',
    city: props.customer.city || '',
    commune: props.customer.commune || '',
    credit_limit: props.customer.credit_limit || 0,
    payment_terms: props.customer.payment_terms || 'immediate',
    notes: props.customer.notes || '',
    is_active: props.customer.is_active
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
    form.put(route('customers.update', props.customer.id));
};
</script>