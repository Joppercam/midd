<template>
    <div class="min-h-screen bg-gray-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <h1 class="text-9xl font-bold text-gray-300">404</h1>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Página no encontrada
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Lo sentimos, no pudimos encontrar la página que buscas.
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="space-y-4">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-gray-600">
                            La página que intentas acceder no existe o ha sido movida.
                        </p>
                    </div>

                    <div class="flex flex-col space-y-3">
                        <Link :href="route('dashboard')" 
                              class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Ir al Dashboard
                        </Link>
                        
                        <button @click="goBack" 
                                class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Volver atrás
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    Si crees que esto es un error, por favor contacta al soporte.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

const goBack = () => {
    window.history.back();
};
</script>