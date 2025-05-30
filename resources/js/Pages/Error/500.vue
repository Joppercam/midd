<template>
    <div class="min-h-screen bg-gray-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <h1 class="text-9xl font-bold text-red-300">500</h1>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Error del servidor
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Ha ocurrido un error inesperado. Estamos trabajando para solucionarlo.
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="space-y-4">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    
                    <div class="rounded-md bg-red-50 p-4" v-if="error_id">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    ID del error: {{ error_id }}
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>Por favor, proporciona este ID al equipo de soporte para ayudarnos a resolver el problema más rápido.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="text-gray-600">
                            Hemos registrado este error y nuestro equipo está trabajando para solucionarlo.
                        </p>
                    </div>

                    <div class="flex flex-col space-y-3">
                        <Link :href="route('dashboard')" 
                              class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Ir al Dashboard
                        </Link>
                        
                        <button @click="reload" 
                                class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Intentar nuevamente
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    Si el problema persiste, contacta a soporte@crecepyme.cl
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    status: Number,
    message: String,
    error_id: String
});

const reload = () => {
    window.location.reload();
};
</script>