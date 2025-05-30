<template>
  <Head title="Crear Cliente" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center">
        <Link
          :href="route('customers.index')"
          class="text-gray-400 hover:text-gray-600 mr-4"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </Link>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Crear Nuevo Cliente
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Información básica -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información Básica</h3>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="type" value="Tipo de cliente *" />
                  <select
                    id="type"
                    v-model="form.type"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required
                    @change="handleTypeChange"
                  >
                    <option value="">Seleccionar tipo</option>
                    <option value="person">Persona Natural</option>
                    <option value="company">Empresa</option>
                  </select>
                  <InputError class="mt-2" :message="form.errors.type" />
                </div>

                <div>
                  <InputLabel for="rut" value="RUT *" />
                  <TextInput
                    id="rut"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.rut"
                    required
                    placeholder="12.345.678-9"
                    @blur="formatRut"
                  />
                  <InputError class="mt-2" :message="form.errors.rut" />
                </div>

                <div class="md:col-span-2">
                  <InputLabel for="name" value="Nombre o Razón Social *" />
                  <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    :placeholder="form.type === 'company' ? 'Nombre de la empresa' : 'Nombre completo'"
                  />
                  <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div v-if="form.type === 'company'">
                  <InputLabel for="business_activity" value="Giro comercial" />
                  <TextInput
                    id="business_activity"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.business_activity"
                    placeholder="Actividad económica principal"
                  />
                  <InputError class="mt-2" :message="form.errors.business_activity" />
                </div>

                <div v-if="form.type === 'company'">
                  <InputLabel for="contact_name" value="Persona de contacto" />
                  <TextInput
                    id="contact_name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.contact_name"
                    placeholder="Nombre del contacto principal"
                  />
                  <InputError class="mt-2" :message="form.errors.contact_name" />
                </div>
              </div>
            </div>
          </div>

          <!-- Información de contacto -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Contacto</h3>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="email" value="Email" />
                  <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    placeholder="cliente@ejemplo.com"
                  />
                  <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                  <InputLabel for="phone" value="Teléfono" />
                  <TextInput
                    id="phone"
                    type="tel"
                    class="mt-1 block w-full"
                    v-model="form.phone"
                    placeholder="+56 9 1234 5678"
                  />
                  <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div class="md:col-span-2">
                  <InputLabel for="address" value="Dirección" />
                  <TextInput
                    id="address"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.address"
                    placeholder="Calle, número, depto/oficina"
                  />
                  <InputError class="mt-2" :message="form.errors.address" />
                </div>

                <div>
                  <InputLabel for="commune" value="Comuna" />
                  <TextInput
                    id="commune"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.commune"
                    placeholder="Providencia"
                  />
                  <InputError class="mt-2" :message="form.errors.commune" />
                </div>

                <div>
                  <InputLabel for="city" value="Ciudad" />
                  <TextInput
                    id="city"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.city"
                    placeholder="Santiago"
                  />
                  <InputError class="mt-2" :message="form.errors.city" />
                </div>
              </div>
            </div>
          </div>

          <!-- Información comercial -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información Comercial</h3>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="credit_limit" value="Límite de crédito" />
                  <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 sm:text-sm">$</span>
                    </div>
                    <input
                      id="credit_limit"
                      type="number"
                      v-model="form.credit_limit"
                      class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      min="0"
                      step="1000"
                      placeholder="0"
                    />
                  </div>
                  <InputError class="mt-2" :message="form.errors.credit_limit" />
                  <p class="mt-1 text-sm text-gray-500">
                    Monto máximo de crédito autorizado
                  </p>
                </div>

                <div>
                  <InputLabel for="payment_term_days" value="Plazo de pago (días)" />
                  <input
                    id="payment_term_days"
                    type="number"
                    v-model="form.payment_term_days"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    min="0"
                    max="365"
                    placeholder="30"
                  />
                  <InputError class="mt-2" :message="form.errors.payment_term_days" />
                  <p class="mt-1 text-sm text-gray-500">
                    Días para vencimiento de facturas
                  </p>
                </div>

                <div class="md:col-span-2">
                  <InputLabel for="notes" value="Notas" />
                  <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Observaciones o información adicional sobre el cliente"
                  ></textarea>
                  <InputError class="mt-2" :message="form.errors.notes" />
                </div>
              </div>
            </div>
          </div>

          <!-- Acciones -->
          <div class="flex items-center justify-end space-x-4">
            <Link
              :href="route('customers.index')"
              class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
            >
              Cancelar
            </Link>
            <PrimaryButton :disabled="form.processing">
              Crear Cliente
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
  type: '',
  rut: '',
  name: '',
  email: '',
  phone: '',
  address: '',
  commune: '',
  city: '',
  business_activity: '',
  contact_name: '',
  notes: '',
  credit_limit: '',
  payment_term_days: '30',
});

const handleTypeChange = () => {
  // Limpiar campos específicos de empresa si cambia a persona
  if (form.type === 'person') {
    form.business_activity = '';
    form.contact_name = '';
  }
};

const formatRut = () => {
  // Formatear RUT chileno
  let value = form.rut.replace(/\./g, '').replace(/-/g, '');
  if (value.length > 1) {
    const dv = value.slice(-1);
    let rut = value.slice(0, -1);
    
    // Formatear con puntos
    let formatted = '';
    while (rut.length > 3) {
      formatted = '.' + rut.slice(-3) + formatted;
      rut = rut.slice(0, -3);
    }
    formatted = rut + formatted;
    
    form.rut = formatted + '-' + dv;
  }
};

const submit = () => {
  form.post(route('customers.store'));
};
</script>