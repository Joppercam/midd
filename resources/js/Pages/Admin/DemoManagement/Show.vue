<template>
    <Head title="Detalle Solicitud Demo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Solicitud de Demo: {{ demoRequest.company_name }}
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Creada el {{ formatDate(demoRequest.created_at) }}
                    </p>
                </div>
                <Link
                    href="/admin/demo-management"
                    class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors"
                >
                    ← Volver
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Información Principal -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Datos de la Empresa -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Información de la Empresa</h3>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Empresa</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ demoRequest.company_name }}</dd>
                                    </div>
                                    <div v-if="demoRequest.rut">
                                        <dt class="text-sm font-medium text-gray-500">RUT</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ demoRequest.rut }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Tipo de Negocio</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ getBusinessTypeLabel(demoRequest.business_type) }}</dd>
                                    </div>
                                    <div v-if="demoRequest.employees">
                                        <dt class="text-sm font-medium text-gray-500">Número de Empleados</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ demoRequest.employees }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Datos de Contacto -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Contacto</h3>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Nombre del Contacto</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ demoRequest.contact_name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <a :href="`mailto:${demoRequest.email}`" class="text-blue-600 hover:text-blue-500">
                                                {{ demoRequest.email }}
                                            </a>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Teléfono</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <a :href="`tel:${demoRequest.phone}`" class="text-blue-600 hover:text-blue-500">
                                                {{ demoRequest.phone }}
                                            </a>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Mensaje Adicional -->
                        <div v-if="demoRequest.message" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Mensaje Adicional</h3>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ demoRequest.message }}</p>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Historial de Actividad</h3>
                                <div class="flow-root">
                                    <ul class="-mb-8">
                                        <li v-for="(event, eventIdx) in timeline" :key="eventIdx">
                                            <div class="relative pb-8">
                                                <span v-if="eventIdx !== timeline.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                <div class="relative flex space-x-3">
                                                    <div>
                                                        <span
                                                            class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white"
                                                            :class="getTimelineIconClass(event.type)"
                                                        >
                                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                                <path v-if="event.type === 'created'" fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                                <path v-else-if="event.type === 'contacted'" fill-rule="evenodd" d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884zM18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" clip-rule="evenodd"/>
                                                                <path v-else-if="event.type === 'scheduled'" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                                                                <path v-else d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                        </span>
                                                    </div>
                                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                        <div>
                                                            <p class="text-sm text-gray-900">
                                                                {{ event.title }}
                                                                <span v-if="event.author" class="text-gray-500">por {{ event.author }}</span>
                                                            </p>
                                                            <p class="mt-0.5 text-sm text-gray-500">{{ event.description }}</p>
                                                        </div>
                                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                            {{ formatDate(event.date) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Lateral -->
                    <div class="space-y-6">
                        
                        <!-- Estado Actual -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Estado Actual</h3>
                                <div class="space-y-4">
                                    <div>
                                        <span
                                            class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"
                                            :class="getStatusClass(demoRequest.status)"
                                        >
                                            {{ getStatusLabel(demoRequest.status) }}
                                        </span>
                                    </div>
                                    
                                    <!-- Cambiar Estado -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Cambiar Estado</label>
                                        <form @submit.prevent="updateStatus">
                                            <div class="space-y-3">
                                                <select v-model="statusForm.status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="pending">Pendiente</option>
                                                    <option value="contacted">Contactado</option>
                                                    <option value="demo_scheduled">Demo Agendada</option>
                                                    <option value="demo_completed">Demo Completada</option>
                                                    <option value="converted">Convertido</option>
                                                    <option value="declined">Declinado</option>
                                                </select>
                                                <textarea
                                                    v-model="statusForm.note"
                                                    placeholder="Nota opcional..."
                                                    rows="3"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                ></textarea>
                                                <button
                                                    type="submit"
                                                    :disabled="statusForm.processing"
                                                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                                >
                                                    {{ statusForm.processing ? 'Actualizando...' : 'Actualizar Estado' }}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones Rápidas -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Acciones</h3>
                                <div class="space-y-3">
                                    <button
                                        v-if="demoRequest.status === 'contacted'"
                                        @click="generateCredentials"
                                        :disabled="isGenerating"
                                        class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 transition-colors"
                                    >
                                        {{ isGenerating ? 'Generando...' : 'Generar Credenciales Demo' }}
                                    </button>
                                    
                                    <button
                                        @click="sendEmail"
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                                    >
                                        Enviar Email
                                    </button>
                                    
                                    <button
                                        @click="scheduleCall"
                                        class="w-full px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
                                    >
                                        Programar Llamada
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Agregar Nota -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Agregar Nota</h3>
                                <form @submit.prevent="addNote">
                                    <div class="space-y-3">
                                        <textarea
                                            v-model="noteForm.note"
                                            placeholder="Escribe una nota..."
                                            rows="4"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            required
                                        ></textarea>
                                        <button
                                            type="submit"
                                            :disabled="noteForm.processing"
                                            class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 disabled:opacity-50 transition-colors"
                                        >
                                            {{ noteForm.processing ? 'Agregando...' : 'Agregar Nota' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    demoRequest: Object,
    timeline: Array,
});

const isGenerating = ref(false);

const statusForm = useForm({
    status: props.demoRequest.status,
    note: ''
});

const noteForm = useForm({
    note: ''
});

const updateStatus = () => {
    statusForm.put(`/admin/demo-management/${props.demoRequest.id}/status`, {
        onSuccess: () => {
            statusForm.reset('note');
        }
    });
};

const addNote = () => {
    noteForm.post(`/admin/demo-management/${props.demoRequest.id}/notes`, {
        onSuccess: () => {
            noteForm.reset();
        }
    });
};

const generateCredentials = () => {
    if (confirm('¿Estás seguro de que quieres generar credenciales para este demo?')) {
        isGenerating.value = true;
        
        router.post(`/admin/demo-management/${props.demoRequest.id}/generate-credentials`, {}, {
            onFinish: () => {
                isGenerating.value = false;
            }
        });
    }
};

const sendEmail = () => {
    window.location.href = `mailto:${props.demoRequest.email}?subject=Re: Demo MIDD - ${props.demoRequest.company_name}`;
};

const scheduleCall = () => {
    // Aquí podrías integrar con un sistema de calendario
    alert('Funcionalidad de programación de llamadas próximamente...');
};

const getStatusClass = (status) => {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'contacted': 'bg-blue-100 text-blue-800',
        'demo_scheduled': 'bg-purple-100 text-purple-800',
        'demo_completed': 'bg-green-100 text-green-800',
        'converted': 'bg-emerald-100 text-emerald-800',
        'declined': 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const getStatusLabel = (status) => {
    const labels = {
        'pending': 'Pendiente',
        'contacted': 'Contactado',
        'demo_scheduled': 'Demo Agendada',
        'demo_completed': 'Demo Completada',
        'converted': 'Convertido',
        'declined': 'Declinado'
    };
    return labels[status] || status;
};

const getBusinessTypeLabel = (type) => {
    const labels = {
        'retail': 'Retail/Comercio',
        'restaurant': 'Restaurante',
        'services': 'Servicios',
        'manufacturing': 'Manufactura',
        'construction': 'Construcción',
        'healthcare': 'Salud',
        'education': 'Educación',
        'other': 'Otro'
    };
    return labels[type] || type;
};

const getTimelineIconClass = (type) => {
    const classes = {
        'created': 'bg-blue-500 text-white',
        'contacted': 'bg-green-500 text-white',
        'scheduled': 'bg-purple-500 text-white',
        'note': 'bg-gray-500 text-white',
    };
    return classes[type] || 'bg-gray-500 text-white';
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>