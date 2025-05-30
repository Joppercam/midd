<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Notificaciones por Email
                </h2>
                <button
                    @click="showSendRemindersModal = true"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150"
                >
                    Enviar Recordatorios
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Total Enviados</div>
                        <div class="text-2xl font-bold text-gray-900">{{ statistics.total }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Exitosos</div>
                        <div class="text-2xl font-bold text-green-600">{{ statistics.sent }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Fallidos</div>
                        <div class="text-2xl font-bold text-red-600">{{ statistics.failed }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Abiertos</div>
                        <div class="text-2xl font-bold text-blue-600">{{ statistics.opened }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Buscar por email
                                </label>
                                <input
                                    v-model="filters.search"
                                    @input="applyFilters"
                                    type="email"
                                    placeholder="email@ejemplo.com"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Email
                                </label>
                                <select
                                    v-model="filters.email_type"
                                    @change="applyFilters"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    <option value="invoice_sent">Factura Enviada</option>
                                    <option value="payment_reminder">Recordatorio de Pago</option>
                                    <option value="payment_overdue">Pago Vencido</option>
                                    <option value="payment_received">Pago Recibido</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Estado
                                </label>
                                <select
                                    v-model="filters.status"
                                    @change="applyFilters"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    <option value="pending">Pendiente</option>
                                    <option value="sent">Enviado</option>
                                    <option value="opened">Abierto</option>
                                    <option value="failed">Fallido</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button
                                    @click="clearFilters"
                                    class="w-full bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-150"
                                >
                                    Limpiar Filtros
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications Table -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Destinatario
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Documento
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="notification in notifications.data" :key="notification.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ notification.recipient_email }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ notification.subject }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="getEmailTypeBadgeClass(notification.email_type)" class="px-2 py-1 text-xs font-medium rounded-full">
                                            {{ getEmailTypeLabel(notification.email_type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span v-if="notification.notifiable">
                                            {{ notification.notifiable.document_number }}
                                        </span>
                                        <span v-else class="text-gray-400">
                                            N/A
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="getStatusBadgeClass(notification.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                            {{ getStatusLabel(notification.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(notification.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button
                                                @click="viewNotification(notification)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                                title="Ver detalles"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="notification.status === 'failed'"
                                                @click="resendNotification(notification)"
                                                class="text-green-600 hover:text-green-900"
                                                title="Reenviar"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="notifications.last_page > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Mostrando {{ notifications.from }} a {{ notifications.to }} de {{ notifications.total }} resultados
                            </div>
                            <div class="flex space-x-2">
                                <button
                                    v-for="page in paginationPages"
                                    :key="page"
                                    @click="goToPage(page)"
                                    :class="[
                                        page === notifications.current_page
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-50',
                                        'px-3 py-2 text-sm font-medium border border-gray-300 rounded-md'
                                    ]"
                                >
                                    {{ page }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Send Reminders Modal -->
        <Modal :show="showSendRemindersModal" @close="showSendRemindersModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Enviar Recordatorios de Facturas Vencidas
                </h3>

                <form @submit.prevent="sendOverdueReminders">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Días de vencimiento mínimo
                        </label>
                        <input
                            v-model="reminderForm.days_overdue"
                            type="number"
                            min="1"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <p class="text-sm text-gray-500 mt-1">
                            Se enviarán recordatorios a facturas vencidas por al menos este número de días
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="flex items-center">
                            <input
                                v-model="reminderForm.send_to_all"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <span class="ml-2 text-sm text-gray-700">
                                Enviar a todas las facturas vencidas (incluso si ya se envió recordatorio)
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showSendRemindersModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition duration-150"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="reminderForm.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition duration-150 disabled:opacity-50"
                        >
                            <span v-if="reminderForm.processing">Enviando...</span>
                            <span v-else>Enviar Recordatorios</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    notifications: Object,
    statistics: Object,
    filters: Object
})

const showSendRemindersModal = ref(false)
const filters = ref({
    search: props.filters?.search || '',
    email_type: props.filters?.email_type || '',
    status: props.filters?.status || ''
})

const reminderForm = ref({
    days_overdue: 1,
    send_to_all: false,
    processing: false
})

const paginationPages = computed(() => {
    const current = props.notifications.current_page
    const last = props.notifications.last_page
    const pages = []
    
    for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i)
    }
    
    return pages
})

const applyFilters = () => {
    router.get(route('emails.index'), filters.value, {
        preserveState: true,
        replace: true
    })
}

const clearFilters = () => {
    filters.value = { search: '', email_type: '', status: '' }
    applyFilters()
}

const goToPage = (page) => {
    router.get(route('emails.index'), { ...filters.value, page }, {
        preserveState: true,
        replace: true
    })
}

const viewNotification = (notification) => {
    router.visit(route('emails.show', notification.id))
}

const resendNotification = (notification) => {
    if (confirm('¿Está seguro de que desea reenviar esta notificación?')) {
        router.post(route('emails.resend', notification.id), {}, {
            preserveScroll: true
        })
    }
}

const sendOverdueReminders = () => {
    if (confirm('¿Está seguro de que desea enviar recordatorios a todas las facturas vencidas?')) {
        reminderForm.value.processing = true
        
        router.post(route('emails.send-overdue-reminders'), reminderForm.value, {
            preserveScroll: true,
            onFinish: () => {
                reminderForm.value.processing = false
                showSendRemindersModal.value = false
            }
        })
    }
}

const getEmailTypeLabel = (type) => {
    const labels = {
        'invoice_sent': 'Factura Enviada',
        'payment_reminder': 'Recordatorio',
        'payment_overdue': 'Pago Vencido',
        'payment_received': 'Pago Recibido'
    }
    return labels[type] || type
}

const getEmailTypeBadgeClass = (type) => {
    const classes = {
        'invoice_sent': 'bg-blue-100 text-blue-800',
        'payment_reminder': 'bg-yellow-100 text-yellow-800',
        'payment_overdue': 'bg-red-100 text-red-800',
        'payment_received': 'bg-green-100 text-green-800'
    }
    return classes[type] || 'bg-gray-100 text-gray-800'
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

const formatDate = (date) => {
    return new Date(date).toLocaleString('es-CL', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    })
}
</script>