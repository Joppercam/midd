<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Configuración SII
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Company Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información de la Empresa</h3>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nombre</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ tenant.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">RUT</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ tenant.rut }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Certificate Status -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Certificado Digital</h3>
                            <button 
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                @click="showCertificateSection = !showCertificateSection"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                {{ showCertificateSection ? 'Ocultar' : 'Gestionar' }} Certificado
                            </button>
                        </div>
                        <p class="text-sm text-gray-600">
                            Para enviar documentos al SII, debe cargar su certificado digital.
                        </p>
                    </div>
                </div>

                <!-- XSD Schemas Status -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Esquemas de Validación XSD</h3>
                            <button 
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                disabled
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Próximamente
                            </button>
                        </div>
                        <p class="text-sm text-gray-600">
                            Los esquemas XSD permiten validar la estructura de los documentos antes de enviarlos al SII.
                        </p>
                    </div>
                </div>

                <!-- SII Configuration -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Configuración de Facturación Electrónica</h3>
                        
                        <form @submit.prevent="submitConfiguration" class="space-y-6">
                            <div>
                                <InputLabel for="resolution_number" value="Número de Resolución" />
                                <TextInput
                                    id="resolution_number"
                                    type="number"
                                    class="mt-1 block w-full"
                                    v-model="form.resolution_number"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.resolution_number" />
                            </div>

                            <div>
                                <InputLabel for="resolution_date" value="Fecha de Resolución" />
                                <TextInput
                                    id="resolution_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                    v-model="form.resolution_date"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.resolution_date" />
                            </div>

                            <div>
                                <InputLabel for="environment" value="Ambiente" />
                                <select
                                    id="environment"
                                    v-model="form.environment"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="certification">Certificación</option>
                                    <option value="production">Producción</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.environment" />
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="form.processing">
                                    Guardar Configuración
                                </PrimaryButton>

                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">
                                        Configuración guardada.
                                    </p>
                                </Transition>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Certificate Upload -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Certificado Digital</h3>
                        
                        <form @submit.prevent="uploadCertificate" class="space-y-6">
                            <div>
                                <InputLabel for="certificate" value="Archivo de Certificado (.p12 o .pfx)" />
                                <input
                                    id="certificate"
                                    type="file"
                                    class="mt-1 block w-full text-sm text-gray-500
                                        file:mr-4 file:py-2 file:px-4
                                        file:rounded-full file:border-0
                                        file:text-sm file:font-semibold
                                        file:bg-indigo-50 file:text-indigo-700
                                        hover:file:bg-indigo-100"
                                    @change="handleCertificateChange"
                                    accept=".p12,.pfx"
                                    required
                                />
                                <InputError class="mt-2" :message="certificateForm.errors.certificate" />
                            </div>

                            <div>
                                <InputLabel for="password" value="Contraseña del Certificado" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    v-model="certificateForm.password"
                                    required
                                />
                                <InputError class="mt-2" :message="certificateForm.errors.password" />
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="certificateForm.processing">
                                    Cargar Certificado
                                </PrimaryButton>

                                <SecondaryButton @click="testConnection" :disabled="!hasConfiguration || testingConnection">
                                    {{ testingConnection ? 'Probando...' : 'Probar Conexión' }}
                                </SecondaryButton>
                            </div>
                        </form>

                        <div v-if="connectionTestResult" class="mt-4 p-4 rounded-md" 
                             :class="connectionTestResult.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
                            {{ connectionTestResult.message }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    tenant: Object,
    hasConfiguration: Boolean,
    configuration: Object,
});

const form = useForm({
    resolution_number: props.configuration?.resolution_number || '',
    resolution_date: props.configuration?.resolution_date || '',
    environment: props.configuration?.environment || 'certification',
});

const certificateForm = useForm({
    certificate: null,
    password: '',
});

const testingConnection = ref(false);
const connectionTestResult = ref(null);
const showCertificateSection = ref(false);

const submitConfiguration = () => {
    // Funcionalidad pendiente
    alert('Funcionalidad en desarrollo');
};

const handleCertificateChange = (event) => {
    certificateForm.certificate = event.target.files[0];
};

const uploadCertificate = () => {
    // Funcionalidad pendiente
    alert('Carga de certificados en desarrollo');
};

const testConnection = async () => {
    testingConnection.value = true;
    connectionTestResult.value = null;

    // Simular prueba de conexión
    setTimeout(() => {
        connectionTestResult.value = {
            success: true,
            message: 'Conexión simulada exitosa (funcionalidad en desarrollo)',
        };
        testingConnection.value = false;
    }, 2000);
};
</script>