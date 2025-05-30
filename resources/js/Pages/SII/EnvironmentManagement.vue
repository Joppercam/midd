<template>
    <Head title="Gestión de Ambientes SII" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gestión de Ambientes SII
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <!-- Current Environment Status -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Estado Actual</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Ambiente Actual:</p>
                                        <p class="text-lg font-semibold" :class="currentEnvironment === 'production' ? 'text-green-600' : 'text-blue-600'">
                                            {{ environments[currentEnvironment] }}
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span v-if="currentEnvironment === 'certification'" 
                                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Certificación
                                        </span>
                                        <span v-else 
                                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Producción
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Certification Status -->
                        <div class="mb-8" v-if="currentEnvironment === 'certification'">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Estado de Certificación</h3>
                            <div class="bg-yellow-50 rounded-lg p-4 mb-4">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <p class="text-sm text-yellow-800">
                                        Debe completar la certificación antes de cambiar a producción.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <button 
                                    @click="validateCertification"
                                    :disabled="validating"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                    <svg v-if="validating" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ validating ? 'Validando...' : 'Validar Certificación' }}
                                </button>
                                
                                <!-- Validation Results -->
                                <div v-if="validationResults" class="mt-4">
                                    <div v-if="validationResults.success" class="bg-green-50 border border-green-200 rounded-md p-4">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <h4 class="text-sm font-medium text-green-800">¡Certificación Completada!</h4>
                                        </div>
                                        <p class="mt-2 text-sm text-green-700">
                                            Su certificación ha sido validada exitosamente. Ahora puede cambiar a producción.
                                        </p>
                                    </div>
                                    
                                    <div v-else class="bg-red-50 border border-red-200 rounded-md p-4">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                            </svg>
                                            <h4 class="text-sm font-medium text-red-800">Certificación Incompleta</h4>
                                        </div>
                                        <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                            <li v-for="error in validationResults.errors" :key="error">{{ error }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Environment Switch -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Cambiar Ambiente</h3>
                            
                            <form @submit.prevent="switchEnvironment" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Seleccionar Ambiente
                                    </label>
                                    <select v-model="selectedEnvironment" 
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option v-for="(label, key) in environments" :key="key" :value="key">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>
                                
                                <div v-if="selectedEnvironment === 'production' && !canSwitchToProduction" 
                                     class="bg-red-50 border border-red-200 rounded-md p-4">
                                    <p class="text-sm text-red-700">
                                        Para cambiar a producción debe completar la certificación primero.
                                    </p>
                                </div>
                                
                                <button type="submit" 
                                        :disabled="processing || selectedEnvironment === currentEnvironment || (selectedEnvironment === 'production' && !canSwitchToProduction)"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                                    {{ processing ? 'Cambiando...' : 'Cambiar Ambiente' }}
                                </button>
                            </form>
                        </div>

                        <!-- Reset Certification -->
                        <div v-if="currentEnvironment === 'production'" class="border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Reiniciar Certificación</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Si necesita realizar cambios en su configuración, puede reiniciar la certificación. 
                                Esto cambiará el ambiente a certificación y deberá completar el proceso nuevamente.
                            </p>
                            <button @click="resetCertification" 
                                    :disabled="processing"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Reiniciar Certificación
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant: Object,
    currentEnvironment: String,
    environments: Object,
    canSwitchToProduction: Boolean,
})

const selectedEnvironment = ref(props.currentEnvironment)
const processing = ref(false)
const validating = ref(false)
const validationResults = ref(null)

const switchForm = useForm({
    environment: props.currentEnvironment,
})

const switchEnvironment = () => {
    if (selectedEnvironment.value === props.currentEnvironment) return
    
    processing.value = true
    switchForm.environment = selectedEnvironment.value
    
    switchForm.post(route('sii.switch-environment'), {
        onFinish: () => {
            processing.value = false
        }
    })
}

const validateCertification = async () => {
    validating.value = true
    validationResults.value = null
    
    try {
        const response = await fetch(route('sii.validate-certification'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        
        const data = await response.json()
        validationResults.value = data
        
        if (data.success) {
            // Refresh the page to update the canSwitchToProduction status
            setTimeout(() => {
                window.location.reload()
            }, 2000)
        }
    } catch (error) {
        console.error('Error validating certification:', error)
        validationResults.value = {
            success: false,
            errors: ['Error al validar la certificación']
        }
    } finally {
        validating.value = false
    }
}

const resetCertification = () => {
    if (confirm('¿Está seguro de que desea reiniciar la certificación? Esto cambiará el ambiente a certificación.')) {
        processing.value = true
        
        const form = useForm({})
        form.post(route('sii.reset-certification'), {
            onFinish: () => {
                processing.value = false
            }
        })
    }
}
</script>