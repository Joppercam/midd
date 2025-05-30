<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Esquemas XSD del SII
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Schema Status -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Estado de los Esquemas</h3>
                            <button
                                @click="downloadSchemas"
                                :disabled="downloading"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                <svg v-if="downloading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                </svg>
                                {{ downloading ? 'Descargando...' : 'Descargar Esquemas' }}
                            </button>
                        </div>

                        <div v-if="schemasExist" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-800">
                                        Todos los esquemas XSD están disponibles para validación
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800">
                                        Faltan esquemas XSD. Descárguelos para habilitar la validación completa.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Esquema
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Archivo
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tamaño
                                        </th>
                                        <th class="relative px-6 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="schema in schemas" :key="schema.name">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ schema.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ schema.filename }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span v-if="schema.exists" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Disponible
                                            </span>
                                            <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                No disponible
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ schema.exists ? formatBytes(schema.size) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                v-if="schema.exists"
                                                @click="viewSchema(schema)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Ver
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- XML Validator -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Validador de XML</h3>
                        
                        <form @submit.prevent="validateXML" class="space-y-4">
                            <div>
                                <label for="documentType" class="block text-sm font-medium text-gray-700">
                                    Tipo de Documento
                                </label>
                                <select
                                    id="documentType"
                                    v-model="validatorForm.type"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                >
                                    <option value="DTE">DTE - Documento Tributario Electrónico</option>
                                    <option value="EnvioDTE">EnvioDTE - Envío de DTEs</option>
                                    <option value="EnvioBOLETA">EnvioBOLETA - Envío de Boletas</option>
                                    <option value="RespuestaDTE">RespuestaDTE - Respuesta de DTE</option>
                                    <option value="ReciboDTE">ReciboDTE - Recibo de DTE</option>
                                </select>
                            </div>

                            <div>
                                <label for="xmlContent" class="block text-sm font-medium text-gray-700">
                                    Contenido XML
                                </label>
                                <textarea
                                    id="xmlContent"
                                    v-model="validatorForm.xml"
                                    rows="10"
                                    class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md font-mono text-xs"
                                    placeholder="Pegue aquí el contenido XML a validar..."
                                ></textarea>
                            </div>

                            <div class="flex items-center space-x-4">
                                <button
                                    type="submit"
                                    :disabled="validating || !validatorForm.xml"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                >
                                    {{ validating ? 'Validando...' : 'Validar XML' }}
                                </button>

                                <button
                                    type="button"
                                    @click="clearValidator"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    Limpiar
                                </button>
                            </div>
                        </form>

                        <!-- Validation Result -->
                        <div v-if="validationResult" class="mt-6">
                            <div
                                class="rounded-md p-4"
                                :class="validationResult.valid ? 'bg-green-50' : 'bg-red-50'"
                            >
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg
                                            v-if="validationResult.valid"
                                            class="h-5 w-5 text-green-400"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-5 w-5 text-red-400"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium" :class="validationResult.valid ? 'text-green-800' : 'text-red-800'">
                                            {{ validationResult.message }}
                                        </h3>
                                        <div v-if="validationResult.errors && validationResult.errors.length > 0" class="mt-2 text-sm" :class="validationResult.valid ? 'text-green-700' : 'text-red-700'">
                                            <p class="font-medium mb-1">Errores encontrados:</p>
                                            <ul class="list-disc list-inside space-y-1">
                                                <li v-for="(error, index) in validationResult.errors" :key="index">
                                                    <span class="font-medium">Línea {{ error.line }}:</span> {{ error.message }}
                                                    <span v-if="error.level" class="text-xs">({{ error.level }})</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schema Viewer Modal -->
        <Modal :show="showSchemaModal" @close="showSchemaModal = false" max-width="4xl">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ selectedSchema?.name }}.xsd
                </h3>
                <div class="bg-gray-100 rounded-lg p-4 overflow-auto max-h-96">
                    <pre class="text-xs">{{ schemaContent }}</pre>
                </div>
                <div class="mt-6 flex justify-end">
                    <button
                        @click="showSchemaModal = false"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

const props = defineProps({
    schemas: Array,
    schemasExist: Boolean,
});

const downloading = ref(false);
const validating = ref(false);
const validationResult = ref(null);
const showSchemaModal = ref(false);
const selectedSchema = ref(null);
const schemaContent = ref('');

const validatorForm = reactive({
    type: 'DTE',
    xml: '',
});

const downloadSchemas = () => {
    downloading.value = true;
    router.post(route('sii.schemas.download'), {}, {
        preserveScroll: true,
        onFinish: () => {
            downloading.value = false;
        },
    });
};

const validateXML = async () => {
    validating.value = true;
    validationResult.value = null;

    try {
        const response = await axios.post(route('sii.schemas.validate'), {
            xml: validatorForm.xml,
            type: validatorForm.type,
        });

        validationResult.value = response.data;
    } catch (error) {
        validationResult.value = {
            valid: false,
            message: error.response?.data?.message || 'Error al validar el XML',
            errors: [],
        };
    } finally {
        validating.value = false;
    }
};

const clearValidator = () => {
    validatorForm.xml = '';
    validationResult.value = null;
};

const viewSchema = async (schema) => {
    try {
        const response = await axios.get(route('sii.schemas.show', schema.name));
        selectedSchema.value = schema;
        schemaContent.value = response.data;
        showSchemaModal.value = true;
    } catch (error) {
        console.error('Error loading schema:', error);
    }
};

const formatBytes = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB'];
    const power = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${Math.round(bytes / Math.pow(1024, power) * 100) / 100} ${units[power]}`;
};
</script>