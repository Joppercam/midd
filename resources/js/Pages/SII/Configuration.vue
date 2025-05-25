<template>
    <AppLayout title="Configuración SII">
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
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
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

const submitConfiguration = () => {
    form.put(route('sii.configuration.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const handleCertificateChange = (event) => {
    certificateForm.certificate = event.target.files[0];
};

const uploadCertificate = () => {
    certificateForm.post(route('sii.certificate.upload'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            certificateForm.reset();
        },
    });
};

const testConnection = async () => {
    testingConnection.value = true;
    connectionTestResult.value = null;

    try {
        const response = await axios.post(route('sii.test-connection'));
        connectionTestResult.value = response.data;
    } catch (error) {
        connectionTestResult.value = {
            success: false,
            message: error.response?.data?.message || 'Error al probar la conexión',
        };
    } finally {
        testingConnection.value = false;
    }
};
</script>