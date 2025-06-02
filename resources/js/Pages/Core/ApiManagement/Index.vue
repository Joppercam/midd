<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    tokens: Object,
    stats: Object,
    recentLogs: Array,
    error: String,
});

console.log('API Management Props:', props);
</script>

<template>
    <Head title="Gestión de API" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Gestión de API
                    </h2>
                    <p class="text-gray-600 text-sm mt-1">
                        Administra tokens de acceso, monitorea el uso y configura la API
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Debug Info -->
                <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <strong class="font-bold">Error:</strong>
                    <span class="block sm:inline">{{ error }}</span>
                </div>
                
                <!-- Basic Stats -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas de API</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ stats?.total_tokens || 0 }}</div>
                            <div class="text-sm text-gray-500">Total Tokens</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ stats?.active_tokens || 0 }}</div>
                            <div class="text-sm text-gray-500">Tokens Activos</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ stats?.total_requests_30d || 0 }}</div>
                            <div class="text-sm text-gray-500">Requests (30d)</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">{{ stats?.successful_requests_30d || 0 }}</div>
                            <div class="text-sm text-gray-500">Exitosos</div>
                        </div>
                    </div>
                </div>

                <!-- Tokens List -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Tokens de API</h3>
                        <PrimaryButton>
                            Nuevo Token
                        </PrimaryButton>
                    </div>
                    
                    <div v-if="tokens && tokens.data && tokens.data.length > 0" class="space-y-4">
                        <div v-for="token in tokens.data" :key="token.id" class="border rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ token.name }}</h4>
                                    <p class="text-sm text-gray-500">Creado por: {{ token.user?.name || 'Desconocido' }}</p>
                                    <div class="mt-2">
                                        <span v-for="ability in (token.abilities || [])" :key="ability"
                                              class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1">
                                            {{ ability }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <SecondaryButton size="sm">Editar</SecondaryButton>
                                    <SecondaryButton size="sm">Regenerar</SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="text-center py-8 text-gray-500">
                        No hay tokens de API configurados.
                    </div>
                </div>

                <!-- Recent Logs -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Logs Recientes</h3>
                    
                    <div v-if="recentLogs && recentLogs.length > 0" class="space-y-2">
                        <div v-for="log in recentLogs" :key="log.id" class="flex justify-between items-center py-2 border-b">
                            <div class="flex items-center space-x-4">
                                <span class="font-mono text-sm">{{ log.method }}</span>
                                <span class="text-sm">{{ log.endpoint }}</span>
                                <span :class="{
                                    'text-green-600': log.status_code < 300,
                                    'text-yellow-600': log.status_code >= 300 && log.status_code < 400,
                                    'text-red-600': log.status_code >= 400
                                }" class="text-sm font-medium">
                                    {{ log.status_code }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ log.response_time }}ms
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="text-center py-8 text-gray-500">
                        No hay logs de API recientes.
                    </div>
                </div>
                
                <!-- Debug Info -->
                <div class="mt-6 bg-gray-100 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">Debug Info:</h4>
                    <pre class="text-xs text-gray-600">{{ JSON.stringify({ tokens, stats, recentLogs }, null, 2) }}</pre>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>