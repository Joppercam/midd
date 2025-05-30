<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Demo de Notificaciones en Tiempo Real
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        
                        <!-- Información del sistema -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Sistema de Notificaciones WebSocket</h3>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">
                                            Estado de conexión WebSocket: 
                                            <span :class="connectionStatus === 'connected' ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'">
                                                {{ connectionStatus === 'connected' ? 'Conectado' : 'Desconectado' }}
                                            </span>
                                        </p>
                                        <p class="text-xs text-blue-600 mt-1">
                                            Las notificaciones aparecerán en la esquina superior derecha de la pantalla.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de prueba -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                            
                            <!-- Notificación básica -->
                            <Card title="Notificación de Prueba" class="hover:shadow-lg transition-shadow">
                                <p class="text-sm text-gray-600 mb-4">
                                    Envía una notificación de prueba básica.
                                </p>
                                <Button 
                                    @click="sendTestNotification" 
                                    :disabled="loading.test"
                                    class="w-full bg-blue-600 hover:bg-blue-700"
                                >
                                    <span v-if="loading.test">Enviando...</span>
                                    <span v-else>Enviar Prueba</span>
                                </Button>
                            </Card>

                            <!-- Nueva factura -->
                            <Card title="Nueva Factura" class="hover:shadow-lg transition-shadow">
                                <p class="text-sm text-gray-600 mb-4">
                                    Simula la creación de una nueva factura.
                                </p>
                                <Button 
                                    @click="simulateInvoice" 
                                    :disabled="loading.invoice"
                                    class="w-full bg-green-600 hover:bg-green-700"
                                >
                                    <span v-if="loading.invoice">Enviando...</span>
                                    <span v-else>Simular Factura</span>
                                </Button>
                            </Card>

                            <!-- Pago recibido -->
                            <Card title="Pago Recibido" class="hover:shadow-lg transition-shadow">
                                <p class="text-sm text-gray-600 mb-4">
                                    Simula la recepción de un pago.
                                </p>
                                <Button 
                                    @click="simulatePayment" 
                                    :disabled="loading.payment"
                                    class="w-full bg-emerald-600 hover:bg-emerald-700"
                                >
                                    <span v-if="loading.payment">Enviando...</span>
                                    <span v-else">Simular Pago</span>
                                </Button>
                            </Card>

                            <!-- Stock bajo -->
                            <Card title="Alerta de Stock" class="hover:shadow-lg transition-shadow">
                                <p class="text-sm text-gray-600 mb-4">
                                    Simula una alerta de stock bajo.
                                </p>
                                <Button 
                                    @click="simulateLowStock" 
                                    :disabled="loading.lowStock"
                                    class="w-full bg-orange-600 hover:bg-orange-700"
                                >
                                    <span v-if="loading.lowStock">Enviando...</span>
                                    <span v-else>Simular Stock Bajo</span>
                                </Button>
                            </Card>

                            <!-- Conciliación bancaria -->
                            <Card title="Conciliación Bancaria" class="hover:shadow-lg transition-shadow">
                                <p class="text-sm text-gray-600 mb-4">
                                    Simula finalización de conciliación.
                                </p>
                                <Button 
                                    @click="simulateBankReconciliation" 
                                    :disabled="loading.bankReconciliation"
                                    class="w-full bg-purple-600 hover:bg-purple-700"
                                >
                                    <span v-if="loading.bankReconciliation">Enviando...</span>
                                    <span v-else>Simular Conciliación</span>
                                </Button>
                            </Card>

                            <!-- Alerta del sistema -->
                            <Card title="Alerta del Sistema" class="hover:shadow-lg transition-shadow">
                                <p class="text-sm text-gray-600 mb-4">
                                    Simula una alerta crítica del sistema.
                                </p>
                                <Button 
                                    @click="simulateSystemAlert" 
                                    :disabled="loading.systemAlert"
                                    class="w-full bg-red-600 hover:bg-red-700"
                                >
                                    <span v-if="loading.systemAlert">Enviando...</span>
                                    <span v-else>Simular Alerta</span>
                                </Button>
                            </Card>
                        </div>

                        <!-- Notificación personalizada -->
                        <Card title="Enviar Notificación Personalizada" class="mb-8">
                            <form @submit.prevent="sendCustomNotification" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Título
                                        </label>
                                        <input
                                            v-model="customNotification.title"
                                            type="text"
                                            required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Título de la notificación"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Tipo
                                        </label>
                                        <select
                                            v-model="customNotification.type"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="info">Información</option>
                                            <option value="success">Éxito</option>
                                            <option value="warning">Advertencia</option>
                                            <option value="error">Error</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Mensaje
                                    </label>
                                    <textarea
                                        v-model="customNotification.message"
                                        required
                                        rows="3"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Contenido del mensaje"
                                    ></textarea>
                                </div>
                                <Button 
                                    type="submit" 
                                    :disabled="loading.custom"
                                    class="bg-indigo-600 hover:bg-indigo-700"
                                >
                                    <span v-if="loading.custom">Enviando...</span>
                                    <span v-else>Enviar Notificación Personalizada</span>
                                </Button>
                            </form>
                        </Card>

                        <!-- Información técnica -->
                        <Card title="Información Técnica">
                            <div class="space-y-2 text-sm">
                                <p><strong>WebSocket Server:</strong> Laravel Reverb</p>
                                <p><strong>Puerto:</strong> {{ config.reverb?.port || 'No configurado' }}</p>
                                <p><strong>Host:</strong> {{ config.reverb?.host || 'No configurado' }}</p>
                                <p><strong>Usuario actual:</strong> {{ $page.props.auth.user.name }}</p>
                                <p><strong>Tenant ID:</strong> {{ $page.props.auth.user.tenant_id }}</p>
                                <p><strong>Canales suscritos:</strong> user.{{ $page.props.auth.user.id }}, tenant.{{ $page.props.auth.user.tenant_id }}</p>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/UI/Card.vue'
import Button from '@/Components/UI/Button.vue'

// Estado reactivo
const loading = reactive({
    test: false,
    invoice: false,
    payment: false,
    lowStock: false,
    bankReconciliation: false,
    systemAlert: false,
    custom: false
})

const connectionStatus = ref('disconnected')
const config = ref({})

const customNotification = reactive({
    title: '',
    message: '',
    type: 'info'
})

// Métodos para enviar notificaciones
const sendTestNotification = async () => {
    loading.test = true
    try {
        const response = await fetch('/notifications/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        const data = await response.json()
        console.log('Test notification sent:', data)
    } catch (error) {
        console.error('Error sending test notification:', error)
    } finally {
        loading.test = false
    }
}

const simulateInvoice = async () => {
    loading.invoice = true
    try {
        await fetch('/notifications/simulate/invoice', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
    } catch (error) {
        console.error('Error:', error)
    } finally {
        loading.invoice = false
    }
}

const simulatePayment = async () => {
    loading.payment = true
    try {
        await fetch('/notifications/simulate/payment', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
    } catch (error) {
        console.error('Error:', error)
    } finally {
        loading.payment = false
    }
}

const simulateLowStock = async () => {
    loading.lowStock = true
    try {
        await fetch('/notifications/simulate/low-stock', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
    } catch (error) {
        console.error('Error:', error)
    } finally {
        loading.lowStock = false
    }
}

const simulateBankReconciliation = async () => {
    loading.bankReconciliation = true
    try {
        await fetch('/notifications/simulate/bank-reconciliation', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
    } catch (error) {
        console.error('Error:', error)
    } finally {
        loading.bankReconciliation = false
    }
}

const simulateSystemAlert = async () => {
    loading.systemAlert = true
    try {
        await fetch('/notifications/simulate/system-alert', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
    } catch (error) {
        console.error('Error:', error)
    } finally {
        loading.systemAlert = false
    }
}

const sendCustomNotification = async () => {
    loading.custom = true
    try {
        await fetch('/notifications/tenant', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(customNotification)
        })
        
        // Limpiar formulario
        customNotification.title = ''
        customNotification.message = ''
        customNotification.type = 'info'
    } catch (error) {
        console.error('Error:', error)
    } finally {
        loading.custom = false
    }
}

// Cargar configuración
const loadConfig = async () => {
    try {
        const response = await fetch('/notifications/config')
        config.value = await response.json()
        
        // Simular estado de conexión (en un entorno real esto vendría del WebSocket)
        setTimeout(() => {
            connectionStatus.value = 'connected'
        }, 1000)
    } catch (error) {
        console.error('Error loading config:', error)
    }
}

onMounted(() => {
    loadConfig()
})
</script>