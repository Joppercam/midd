<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Detalle de Notificación
                </h2>
                <div class="flex space-x-3">
                    <button
                        v-if="notification.status === 'failed'"
                        @click="resendNotification"
                        class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150"
                    >
                        Reenviar
                    </button>
                    <Link
                        :href="route('emails.index')"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-150"
                    >
                        Volver
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Header with Status -->
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">
                                    {{ notification.subject }}
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ getEmailTypeLabel(notification.email_type) }}
                                </p>
                            </div>
                            <span :class="getStatusBadgeClass(notification.status)" class="px-3 py-1 text-sm font-medium rounded-full">
                                {{ getStatusLabel(notification.status) }}
                            </span>
                        </div>

                        <!-- Basic Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Destinatario</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ notification.recipient_email }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Fecha de Envío</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(notification.created_at) }}</p>
                                </div>
                                <div v-if="notification.sent_at">
                                    <label class="block text-sm font-medium text-gray-700">Enviado el</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(notification.sent_at) }}</p>
                                </div>
                                <div v-if="notification.opened_at">
                                    <label class="block text-sm font-medium text-gray-700">Abierto el</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(notification.opened_at) }}</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div v-if="notification.notifiable">
                                    <label class="block text-sm font-medium text-gray-700">Documento Relacionado</label>
                                    <div class="mt-1">
                                        <Link
                                            :href="route('billing.invoices.show', notification.notifiable.id)"
                                            class="text-indigo-600 hover:text-indigo-900 font-medium"
                                        >
                                            Factura {{ notification.notifiable.document_number }}
                                        </Link>
                                        <p class="text-sm text-gray-500">
                                            ${{ formatCurrency(notification.notifiable.total_amount) }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="notification.attempts > 0">
                                    <label class="block text-sm font-medium text-gray-700">Intentos de Envío</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ notification.attempts }}</p>
                                </div>
                                <div v-if="notification.error_message">
                                    <label class="block text-sm font-medium text-gray-700">Error</label>
                                    <p class="mt-1 text-sm text-red-600">{{ notification.error_message }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Metadata -->
                        <div v-if="notification.metadata && Object.keys(notification.metadata).length > 0" class="mb-8">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Información Adicional</h4>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div v-for="(value, key) in notification.metadata" :key="key">
                                        <dt class="text-sm font-medium text-gray-500 capitalize">
                                            {{ formatMetadataKey(key) }}
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            {{ formatMetadataValue(value) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Email Timeline -->
                        <div v-if="emailTimeline.length > 0" class="mb-8">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Historial del Email</h4>
                            <div class="flow-root">
                                <ul class="-mb-8">
                                    <li v-for="(event, index) in emailTimeline" :key="index">
                                        <div class="relative pb-8" :class="{ 'pb-0': index === emailTimeline.length - 1 }">
                                            <span
                                                v-if="index !== emailTimeline.length - 1"
                                                class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200"
                                            ></span>
                                            <div class="relative flex items-start space-x-3">
                                                <div class="relative">
                                                    <div :class="event.iconClass" class="h-10 w-10 rounded-full flex items-center justify-center ring-8 ring-white">
                                                        <component :is="event.icon" class="h-5 w-5 text-white" />
                                                    </div>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div>
                                                        <div class="text-sm">
                                                            <span class="font-medium text-gray-900">{{ event.title }}</span>
                                                        </div>
                                                        <p class="mt-0.5 text-sm text-gray-500">
                                                            {{ formatDateTime(event.timestamp) }}
                                                        </p>
                                                    </div>
                                                    <div v-if="event.description" class="mt-2 text-sm text-gray-700">
                                                        <p>{{ event.description }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Related Notifications -->
                        <div v-if="relatedNotifications.length > 0">
                            <h4 class="text-md font-medium text-gray-900 mb-4">
                                Otras Notificaciones del Mismo Documento
                            </h4>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="space-y-3">
                                    <div
                                        v-for="related in relatedNotifications"
                                        :key="related.id"
                                        class="flex justify-between items-center"
                                    >
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ getEmailTypeLabel(related.email_type) }}
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{ related.recipient_email }} - {{ formatDateTime(related.created_at) }}
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span :class="getStatusBadgeClass(related.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                                {{ getStatusLabel(related.status) }}
                                            </span>
                                            <Link
                                                v-if="related.id !== notification.id"
                                                :href="route('emails.show', related.id)"
                                                class="text-indigo-600 hover:text-indigo-900 text-sm"
                                            >
                                                Ver
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    notification: Object,
    relatedNotifications: {
        type: Array,
        default: () => []
    }
})

const emailTimeline = computed(() => {
    const events = []
    
    // Creation event
    events.push({
        title: 'Email creado',
        timestamp: props.notification.created_at,
        description: 'La notificación fue agregada a la cola de envío',
        icon: 'PlusIcon',
        iconClass: 'bg-blue-500'
    })
    
    // Sent event
    if (props.notification.sent_at) {
        events.push({
            title: 'Email enviado',
            timestamp: props.notification.sent_at,
            description: 'El email fue enviado exitosamente',
            icon: 'PaperAirplaneIcon',
            iconClass: 'bg-green-500'
        })
    }
    
    // Opened event
    if (props.notification.opened_at) {
        events.push({
            title: 'Email abierto',
            timestamp: props.notification.opened_at,
            description: 'El destinatario abrió el email',
            icon: 'EyeIcon',
            iconClass: 'bg-blue-500'
        })
    }
    
    // Failed event
    if (props.notification.status === 'failed') {
        events.push({
            title: 'Envío fallido',
            timestamp: props.notification.updated_at,
            description: props.notification.error_message || 'Error al enviar el email',
            icon: 'ExclamationTriangleIcon',
            iconClass: 'bg-red-500'
        })
    }
    
    return events.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp))
})

const resendNotification = () => {
    if (confirm('¿Está seguro de que desea reenviar esta notificación?')) {
        router.post(route('emails.resend', props.notification.id), {}, {
            preserveScroll: true
        })
    }
}

const getEmailTypeLabel = (type) => {
    const labels = {
        'invoice_sent': 'Factura Enviada',
        'payment_reminder': 'Recordatorio de Pago',
        'payment_overdue': 'Pago Vencido',
        'payment_received': 'Pago Recibido'
    }
    return labels[type] || type
}

const getStatusLabel = (status) => {
    const labels = {
        'pending': 'Pendiente',
        'sent': 'Enviado',
        'opened': 'Abierto',
        'failed': 'Fallido'
    }
    return labels[status] || status
}

const getStatusBadgeClass = (status) => {
    const classes = {
        'pending': 'bg-gray-100 text-gray-800',
        'sent': 'bg-green-100 text-green-800',
        'opened': 'bg-blue-100 text-blue-800',
        'failed': 'bg-red-100 text-red-800'
    }
    return classes[status] || 'bg-gray-100 text-gray-800'
}

const formatDateTime = (date) => {
    return new Date(date).toLocaleString('es-CL', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    })
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL').format(amount)
}

const formatMetadataKey = (key) => {
    return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const formatMetadataValue = (value) => {
    if (typeof value === 'boolean') {
        return value ? 'Sí' : 'No'
    }
    if (Array.isArray(value)) {
        return value.join(', ')
    }
    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2)
    }
    return String(value)
}
</script>