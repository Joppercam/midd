<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Estado de Documentos SII
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Document Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Documento</h3>
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ document.type_label }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Número</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ document.number }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Cliente</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ document.customer.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Total</dt>
                                        <dd class="mt-1 text-sm text-gray-900">${{ formatCurrency(document.total) }}</dd>
                                    </div>
                                </dl>
                            </div>
                            
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Estado SII</h3>
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                        <dd class="mt-1">
                                            <span 
                                                :class="[
                                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                    statusClasses[document.sii_status_color]
                                                ]"
                                            >
                                                {{ document.sii_status_label }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div v-if="document.sii_track_id">
                                        <dt class="text-sm font-medium text-gray-500">Track ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ document.sii_track_id }}</dd>
                                    </div>
                                    <div v-if="document.sent_at">
                                        <dt class="text-sm font-medium text-gray-500">Enviado</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(document.sent_at) }}</dd>
                                    </div>
                                    <div v-if="document.sii_accepted_at">
                                        <dt class="text-sm font-medium text-gray-500">Aceptado</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(document.sii_accepted_at) }}</dd>
                                    </div>
                                    <div v-if="document.sii_rejected_at">
                                        <dt class="text-sm font-medium text-gray-500">Rechazado</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(document.sii_rejected_at) }}</dd>
                                    </div>
                                    <div v-if="document.sii_status_detail">
                                        <dt class="text-sm font-medium text-gray-500">Detalle</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ document.sii_status_detail }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-6 flex space-x-3">
                            <button
                                v-if="document.sii_track_id && document.is_sii_pending"
                                @click="checkStatus"
                                :disabled="checkingStatus"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                <svg v-if="checkingStatus" class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                {{ checkingStatus ? 'Consultando...' : 'Consultar Estado' }}
                            </button>

                            <button
                                v-if="document.can_resend_to_sii"
                                @click="resendToSii"
                                :disabled="resending"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                <svg v-if="resending" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                {{ resending ? 'Enviando...' : 'Reenviar al SII' }}
                            </button>

                            <Link
                                :href="route('invoices.show', document.id)"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                Ver Documento
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Event Logs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Historial de Eventos SII</h3>
                        
                        <div v-if="eventLogs.length > 0" class="flow-root">
                            <ul role="list" class="-mb-8">
                                <li v-for="(event, eventIdx) in eventLogs" :key="event.id">
                                    <div class="relative pb-8">
                                        <span
                                            v-if="eventIdx !== eventLogs.length - 1"
                                            class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                                        ></span>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span
                                                    :class="[
                                                        'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white',
                                                        event.is_error ? 'bg-red-500' : 'bg-green-500'
                                                    ]"
                                                >
                                                    <svg
                                                        v-if="event.is_error"
                                                        class="h-5 w-5 text-white"
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                    >
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                    <svg
                                                        v-else
                                                        class="h-5 w-5 text-white"
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                    >
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                <div>
                                                    <p class="text-sm text-gray-900">
                                                        {{ event.formatted_event_type }}
                                                        <span v-if="event.status" class="font-medium">
                                                            - {{ event.status }}
                                                        </span>
                                                    </p>
                                                    <p v-if="event.error_message" class="mt-1 text-sm text-red-600">
                                                        {{ event.error_message }}
                                                    </p>
                                                    <p v-if="event.response_time" class="mt-1 text-xs text-gray-500">
                                                        Tiempo de respuesta: {{ event.response_time_in_seconds }}s
                                                    </p>
                                                </div>
                                                <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                    <time :datetime="event.created_at">
                                                        {{ formatDateTime(event.created_at) }}
                                                    </time>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        
                        <div v-else class="text-center py-4">
                            <p class="text-sm text-gray-500">No hay eventos registrados para este documento.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    document: Object,
    eventLogs: Array,
});

const checkingStatus = ref(false);
const resending = ref(false);

const statusClasses = {
    gray: 'bg-gray-100 text-gray-800',
    blue: 'bg-blue-100 text-blue-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    green: 'bg-green-100 text-green-800',
    red: 'bg-red-100 text-red-800',
};

const checkStatus = async () => {
    checkingStatus.value = true;
    
    try {
        await router.post(route('sii.status.check', props.document.id), {}, {
            preserveScroll: true,
            onFinish: () => {
                checkingStatus.value = false;
            },
        });
    } catch (error) {
        checkingStatus.value = false;
    }
};

const resendToSii = async () => {
    if (!confirm('¿Está seguro de reenviar este documento al SII?')) return;
    
    resending.value = true;
    
    try {
        await router.post(route('sii.send', props.document.id), {}, {
            preserveScroll: true,
            onFinish: () => {
                resending.value = false;
            },
        });
    } catch (error) {
        resending.value = false;
    }
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
};

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>