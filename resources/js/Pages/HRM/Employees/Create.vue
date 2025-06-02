<template>
  <Head title="Nuevo Empleado" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Nuevo Empleado
        </h2>
        <Link
          :href="route('hrm.employees.index')"
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
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Información Personal -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información Personal</h3>
              
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                  <label for="first_name" class="block text-sm font-medium text-gray-700">Nombres *</label>
                  <input
                    id="first_name"
                    v-model="form.first_name"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required
                  />
                  <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                </div>

                <div>
                  <label for="last_name" class="block text-sm font-medium text-gray-700">Apellidos *</label>
                  <input
                    id="last_name"
                    v-model="form.last_name"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required
                  />
                  <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                </div>

                <div>
                  <label for="rut" class="block text-sm font-medium text-gray-700">RUT *</label>
                  <input
                    id="rut"
                    v-model="form.rut"
                    type="text"
                    @input="formatRut"
                    placeholder="11.111.111-1"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required
                  />
                  <p v-if="form.errors.rut" class="mt-1 text-sm text-red-600">{{ form.errors.rut }}</p>
                </div>

                <div>
                  <label for="birth_date" class="block text-sm font-medium text-gray-700">Fecha de Nacimiento</label>
                  <input
                    id="birth_date"
                    v-model="form.birth_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                  <p v-if="form.errors.birth_date" class="mt-1 text-sm text-red-600">{{ form.errors.birth_date }}</p>
                </div>

                <div>
                  <label for="gender" class="block text-sm font-medium text-gray-700">Género</label>
                  <select
                    id="gender"
                    v-model="form.gender"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  >
                    <option value="">Seleccionar</option>
                    <option value="male">Masculino</option>
                    <option value="female">Femenino</option>
                    <option value="other">Otro</option>
                  </select>
                  <p v-if="form.errors.gender" class="mt-1 text-sm text-red-600">{{ form.errors.gender }}</p>
                </div>

                <div>
                  <label for="marital_status" class="block text-sm font-medium text-gray-700">Estado Civil</label>
                  <select
                    id="marital_status"
                    v-model="form.marital_status"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  >
                    <option value="">Seleccionar</option>
                    <option value="single">Soltero(a)</option>
                    <option value="married">Casado(a)</option>
                    <option value="divorced">Divorciado(a)</option>
                    <option value="widowed">Viudo(a)</option>
                  </select>
                  <p v-if="form.errors.marital_status" class="mt-1 text-sm text-red-600">{{ form.errors.marital_status }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Información de Contacto -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Contacto</h3>
              
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                  <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                  <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required
                  />
                  <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                  <label for="phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                  <input
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                  <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                </div>

                <div>
                  <label for="emergency_contact" class="block text-sm font-medium text-gray-700">Contacto de Emergencia</label>
                  <input
                    id="emergency_contact"
                    v-model="form.emergency_contact"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                  <p v-if="form.errors.emergency_contact" class="mt-1 text-sm text-red-600">{{ form.errors.emergency_contact }}</p>
                </div>

                <div>
                  <label for="emergency_phone" class="block text-sm font-medium text-gray-700">Teléfono de Emergencia</label>
                  <input
                    id="emergency_phone"
                    v-model="form.emergency_phone"
                    type="tel"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                  <p v-if="form.errors.emergency_phone" class="mt-1 text-sm text-red-600">{{ form.errors.emergency_phone }}</p>
                </div>

                <div class="sm:col-span-2">
                  <label for="address" class="block text-sm font-medium text-gray-700">Dirección</label>
                  <textarea
                    id="address"
                    v-model="form.address"
                    rows="3"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  ></textarea>
                  <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Información Laboral -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información Laboral</h3>
              
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                  <label for="employee_code" class="block text-sm font-medium text-gray-700">Código de Empleado</label>
                  <input
                    id="employee_code"
                    v-model="form.employee_code"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                  <p v-if="form.errors.employee_code" class="mt-1 text-sm text-red-600">{{ form.errors.employee_code }}</p>
                </div>

                <div>
                  <label for="hire_date" class="block text-sm font-medium text-gray-700">Fecha de Contratación *</label>
                  <input
                    id="hire_date"
                    v-model="form.hire_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required
                  />
                  <p v-if="form.errors.hire_date" class="mt-1 text-sm text-red-600">{{ form.errors.hire_date }}</p>
                </div>

                <div>
                  <label for="department_id" class="block text-sm font-medium text-gray-700">Departamento</label>
                  <select
                    id="department_id"
                    v-model="form.department_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  >
                    <option value="">Seleccionar</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                      {{ dept.name }}
                    </option>
                  </select>
                  <p v-if="form.errors.department_id" class="mt-1 text-sm text-red-600">{{ form.errors.department_id }}</p>
                </div>

                <div>
                  <label for="position_id" class="block text-sm font-medium text-gray-700">Cargo</label>
                  <select
                    id="position_id"
                    v-model="form.position_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  >
                    <option value="">Seleccionar</option>
                    <option v-for="pos in positions" :key="pos.id" :value="pos.id">
                      {{ pos.title }}
                    </option>
                  </select>
                  <p v-if="form.errors.position_id" class="mt-1 text-sm text-red-600">{{ form.errors.position_id }}</p>
                </div>

                <div>
                  <label for="contract_type" class="block text-sm font-medium text-gray-700">Tipo de Contrato</label>
                  <select
                    id="contract_type"
                    v-model="form.contract_type"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  >
                    <option value="">Seleccionar</option>
                    <option value="indefinite">Indefinido</option>
                    <option value="fixed_term">Plazo Fijo</option>
                    <option value="per_project">Por Obra</option>
                    <option value="part_time">Part Time</option>
                  </select>
                  <p v-if="form.errors.contract_type" class="mt-1 text-sm text-red-600">{{ form.errors.contract_type }}</p>
                </div>

                <div>
                  <label for="work_schedule" class="block text-sm font-medium text-gray-700">Horario de Trabajo</label>
                  <input
                    id="work_schedule"
                    v-model="form.work_schedule"
                    type="text"
                    placeholder="Ej: Lunes a Viernes 9:00 - 18:00"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                  <p v-if="form.errors.work_schedule" class="mt-1 text-sm text-red-600">{{ form.errors.work_schedule }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Información Salarial -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Información Salarial</h3>
              
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                  <label for="salary" class="block text-sm font-medium text-gray-700">Salario Base *</label>
                  <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 sm:text-sm">$</span>
                    </div>
                    <input
                      id="salary"
                      v-model="form.salary"
                      type="number"
                      min="0"
                      class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      required
                    />
                  </div>
                  <p v-if="form.errors.salary" class="mt-1 text-sm text-red-600">{{ form.errors.salary }}</p>
                </div>

                <div>
                  <label for="bank_account" class="block text-sm font-medium text-gray-700">Cuenta Bancaria</label>
                  <input
                    id="bank_account"
                    v-model="form.bank_account"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                  <p v-if="form.errors.bank_account" class="mt-1 text-sm text-red-600">{{ form.errors.bank_account }}</p>
                </div>

                <div>
                  <label for="afp" class="block text-sm font-medium text-gray-700">AFP</label>
                  <select
                    id="afp"
                    v-model="form.afp"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  >
                    <option value="">Seleccionar</option>
                    <option value="capital">Capital</option>
                    <option value="cuprum">Cuprum</option>
                    <option value="habitat">Habitat</option>
                    <option value="modelo">Modelo</option>
                    <option value="planvital">PlanVital</option>
                    <option value="provida">ProVida</option>
                    <option value="uno">Uno</option>
                  </select>
                  <p v-if="form.errors.afp" class="mt-1 text-sm text-red-600">{{ form.errors.afp }}</p>
                </div>

                <div>
                  <label for="health_insurance" class="block text-sm font-medium text-gray-700">Previsión de Salud</label>
                  <select
                    id="health_insurance"
                    v-model="form.health_insurance"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  >
                    <option value="">Seleccionar</option>
                    <option value="fonasa">Fonasa</option>
                    <option value="banmedica">Banmédica</option>
                    <option value="consalud">Consalud</option>
                    <option value="colmena">Colmena</option>
                    <option value="cruz_blanca">Cruz Blanca</option>
                    <option value="mas_vida">Más Vida</option>
                    <option value="vida_tres">Vida Tres</option>
                  </select>
                  <p v-if="form.errors.health_insurance" class="mt-1 text-sm text-red-600">{{ form.errors.health_insurance }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Botones de acción -->
          <div class="flex justify-end space-x-4">
            <Link
              :href="route('hrm.employees.index')"
              class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
            >
              <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Guardar Empleado
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatRut as formatRutUtil, validateRut } from '@/utils/rutValidator';

const props = defineProps({
  departments: Array,
  positions: Array,
});

const form = useForm({
  // Personal
  first_name: '',
  last_name: '',
  rut: '',
  birth_date: '',
  gender: '',
  marital_status: '',
  
  // Contacto
  email: '',
  phone: '',
  emergency_contact: '',
  emergency_phone: '',
  address: '',
  
  // Laboral
  employee_code: '',
  hire_date: '',
  department_id: '',
  position_id: '',
  contract_type: '',
  work_schedule: '',
  
  // Salarial
  salary: '',
  bank_account: '',
  afp: '',
  health_insurance: '',
});

const formatRut = () => {
  form.rut = formatRutUtil(form.rut);
};

const submit = () => {
  // Validar RUT
  if (!validateRut(form.rut)) {
    form.setError('rut', 'El RUT ingresado no es válido');
    return;
  }

  form.post(route('hrm.employees.store'), {
    preserveScroll: true,
    onSuccess: () => {
      // El backend se encargará de redireccionar
    },
  });
};
</script>