<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Certificado Digital SII
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Certificate Status -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Estado del Certificado</h3>
                    
                    <div v-if="hasCertificate && certificateInfo">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-800">
                                        Certificado digital cargado correctamente
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Titular</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ certificateInfo.subject.CN || 'No disponible' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">RUT</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ extractRutFromSubject(certificateInfo.subject) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Válido desde</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ formatDate(certificateInfo.valid_from) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Válido hasta</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ formatDate(certificateInfo.valid_to) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Emisor</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ certificateInfo.issuer.O || 'No disponible' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Estado</p>
                                <p class="mt-1">
                                    <span v-if="certificateInfo.is_valid" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Válido
                                    </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Expirado
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex space-x-3">
                            <button
                                @click="testCertificate"
                                :disabled="testingCertificate"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ testingCertificate ? 'Probando...' : 'Probar Certificado' }}
                            </button>
                            
                            <button
                                @click="showDeleteConfirmation = true"
                                class="inline-flex items-center px-4 py-2 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Eliminar Certificado
                            </button>
                        </div>
                    </div>
                    
                    <div v-else>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800">
                                        No hay certificado digital cargado. Debe cargar su certificado para poder enviar documentos al SII.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Certificate Form -->
                <div v-if="!hasCertificate" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Cargar Certificado Digital</h3>
                    
                    <form @submit.prevent="uploadCertificate" class="space-y-6">
                        <div>
                            <label for="certificate" class="block text-sm font-medium text-gray-700">
                                Archivo de Certificado (.p12 o .pfx)
                            </label>
                            <div class="mt-1">
                                <input
                                    type="file"
                                    id="certificate"
                                    ref="certificateInput"
                                    accept=".p12,.pfx"
                                    @change="handleFileSelect"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                    required
                                />
                            </div>
                            <p class="mt-2 text-sm text-gray-500">
                                Seleccione el archivo de certificado digital emitido por el SII.
                            </p>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Contraseña del Certificado
                            </label>
                            <div class="mt-1">
                                <input
                                    type="password"
                                    id="password"
                                    v-model="form.password"
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                    required
                                />
                            </div>
                            <p class="mt-2 text-sm text-gray-500">
                                Ingrese la contraseña de su certificado digital.
                            </p>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">Información importante:</h4>
                            <ul class="text-sm text-blue-700 space-y-1 list-disc list-inside">
                                <li>El certificado debe estar vigente y ser emitido por el SII.</li>
                                <li>La contraseña se almacena de forma segura y encriptada.</li>
                                <li>El certificado se utiliza únicamente para firmar documentos tributarios.</li>
                                <li>Puede actualizar o eliminar el certificado en cualquier momento.</li>
                            </ul>
                        </div>

                        <div v-if="uploadError" class="rounded-md bg-red-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-800">{{ uploadError }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="uploading || !form.file || !form.password"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="uploading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ uploading ? 'Cargando...' : 'Cargar Certificado' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Delete Confirmation Modal -->
                <Modal :show="showDeleteConfirmation" @close="showDeleteConfirmation = false">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            ¿Está seguro que desea eliminar el certificado?
                        </h3>
                        <p class="text-sm text-gray-500 mb-6">
                            Esta acción no se puede deshacer. Deberá cargar nuevamente el certificado para poder enviar documentos al SII.
                        </p>
                        <div class="flex justify-end space-x-3">
                            <button
                                @click="showDeleteConfirmation = false"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Cancelar
                            </button>
                            <button
                                @click="deleteCertificate"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                Eliminar Certificado
                            </button>
                        </div>
                    </div>
                </Modal>

                <!-- Test Result Modal -->
                <Modal :show="showTestResult" @close="showTestResult = false">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Resultado de la Prueba
                        </h3>
                        <div v-if="testResult.success" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-800">
                                        {{ testResult.message }}
                                    </p>
                                    <p class="text-sm text-green-700 mt-1">
                                        Firma digital: {{ testResult.signature_valid ? 'Válida' : 'Inválida' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-800">
                                        {{ testResult.message }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button
                                @click="showTestResult = false"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </Modal>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

const props = defineProps({
    hasCertificate: Boolean,
    certificateInfo: Object,
});

const form = reactive({
    file: null,
    password: '',
});

const uploading = ref(false);
const uploadError = ref('');
const showDeleteConfirmation = ref(false);
const testingCertificate = ref(false);
const showTestResult = ref(false);
const testResult = ref({});

const handleFileSelect = (event) => {
    form.file = event.target.files[0];
    uploadError.value = '';
};

const uploadCertificate = async () => {
    if (!form.file || !form.password) return;

    uploading.value = true;
    uploadError.value = '';

    const formData = new FormData();
    formData.append('certificate', form.file);
    formData.append('password', form.password);

    try {
        await router.post(route('sii.certificate.upload'), formData, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                uploadError.value = errors.certificate || 'Error al cargar el certificado';
            },
        });
    } finally {
        uploading.value = false;
    }
};

const deleteCertificate = () => {
    router.delete(route('sii.certificate.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const testCertificate = async () => {
    testingCertificate.value = true;
    
    try {
        const response = await axios.post(route('sii.certificate.test'));
        testResult.value = response.data;
        showTestResult.value = true;
    } catch (error) {
        testResult.value = {
            success: false,
            message: error.response?.data?.message || 'Error al probar el certificado',
        };
        showTestResult.value = true;
    } finally {
        testingCertificate.value = false;
    }
};

const extractRutFromSubject = (subject) => {
    if (!subject) return 'No disponible';
    
    // Try to extract RUT from CN or serialNumber
    const cn = subject.CN || '';
    const serialNumber = subject.serialNumber || '';
    
    // Look for RUT pattern in CN
    const rutMatch = cn.match(/\d{1,2}\.\d{3}\.\d{3}-[\dkK]/);
    if (rutMatch) return rutMatch[0];
    
    // Look for RUT in serialNumber
    if (serialNumber) return serialNumber;
    
    return 'No disponible';
};

const formatDate = (dateString) => {
    if (!dateString) return 'No disponible';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>