<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Centro de Notificaciones
                </h2>
                <div class="flex space-x-3">
                    <Badge :variant="connectionStatus === 'connected' ? 'green' : 'red'">
                        {{ connectionStatus === 'connected' ? 'Conectado' : 'Desconectado' }}
                    </Badge>
                    <Button @click="showTestModal = true" variant="secondary" size="sm">
                        <BeakerIcon class="w-4 h-4 mr-2" />
                        Probar
                    </Button>
                    <Button @click="showSettingsModal = true" variant="secondary" size="sm">
                        <CogIcon class="w-4 h-4 mr-2" />
                        Configurar
                    </Button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Estado de Conexión -->
                <Card>
                    <template #header>
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 rounded-full" :class="connectionStatus === 'connected' ? 'bg-green-500' : 'bg-red-500'"></div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Estado de Conexión en Tiempo Real
                            </h3>
                        </div>
                    </template>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ statistics.today }}</div>
                            <div class="text-sm text-gray-500">Hoy</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ statistics.week }}</div>
                            <div class="text-sm text-gray-500">Esta Semana</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ statistics.month }}</div>
                            <div class="text-sm text-gray-500">Este Mes</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-red-600">{{ statistics.unread }}</div>
                            <div class="text-sm text-gray-500">Sin Leer</div>
                        </div>
                    </div>
                </Card>

                <!-- Notificaciones en Tiempo Real -->
                <Card>
                    <template #header>
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">
                                Notificaciones Recientes
                            </h3>
                            <div class="flex space-x-2">
                                <Button @click="markAllAsRead" variant="secondary" size="sm" :disabled="notifications.length === 0">
                                    Marcar todo como leído
                                </Button>
                                <Button @click="clearNotifications" variant="secondary" size="sm" :disabled="notifications.length === 0">
                                    <TrashIcon class="w-4 h-4 mr-1" />
                                    Limpiar
                                </Button>
                            </div>
                        </div>
                    </template>
                    
                    <div v-if="notifications.length === 0" class="text-center py-8">
                        <BellIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Sin notificaciones</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            No hay notificaciones recientes para mostrar.
                        </p>
                    </div>
                    
                    <div v-else class="space-y-2">
                        <TransitionGroup name="notification" tag="div">
                            <div v-for="notification in notifications" :key="notification.id" 
                                 class="notification-item p-4 border rounded-lg cursor-pointer transition-all duration-200"
                                 :class="[
                                     notification.read ? 'bg-gray-50 border-gray-200' : 'bg-white border-indigo-200 shadow-sm',
                                     getPriorityClasses(notification.priority)
                                 ]"
                                 @click="handleNotificationClick(notification)">
                                
                                <div class="flex items-start space-x-3">
                                    <!-- Icono -->
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                             :class="getIconBackgroundClass(notification.type)">
                                            <component :is="getNotificationIcon(notification.icon)" 
                                                      class="w-4 h-4" 
                                                      :class="getIconClass(notification.type)" />
                                        </div>
                                    </div>
                                    
                                    <!-- Contenido -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-sm font-medium text-gray-900 truncate">
                                                {{ notification.title }}
                                            </h4>
                                            <div class="flex items-center space-x-2">
                                                <Badge v-if="notification.priority === 'high'" variant="red" size="sm">
                                                    Alta
                                                </Badge>
                                                <span class="text-xs text-gray-500">
                                                    {{ formatRelativeTime(notification.timestamp) }}
                                                </span>
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-600 mt-1">
                                            {{ notification.message }}
                                        </p>
                                        <div v-if="notification.action_url" class="mt-2">
                                            <span class="text-xs text-indigo-600 hover:text-indigo-800">
                                                Click para ver detalles →
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Indicador de no leído -->
                                    <div v-if="!notification.read" class="flex-shrink-0">
                                        <div class="w-2 h-2 bg-indigo-600 rounded-full"></div>
                                    </div>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </Card>

                <!-- Notificaciones por Tipo -->
                <Card>
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">
                            Notificaciones por Tipo
                        </h3>
                    </template>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <DocumentTextIcon class="w-8 h-8 mx-auto text-blue-600 mb-2" />
                            <div class="text-xl font-bold text-blue-900">{{ statistics.by_type.invoices }}</div>
                            <div class="text-sm text-blue-700">Facturas</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <CurrencyDollarIcon class="w-8 h-8 mx-auto text-green-600 mb-2" />
                            <div class="text-xl font-bold text-green-900">{{ statistics.by_type.payments }}</div>
                            <div class="text-sm text-green-700">Pagos</div>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 rounded-lg">
                            <ExclamationTriangleIcon class="w-8 h-8 mx-auto text-yellow-600 mb-2" />
                            <div class="text-xl font-bold text-yellow-900">{{ statistics.by_type.system }}</div>
                            <div class="text-sm text-yellow-700">Sistema</div>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <ServerIcon class="w-8 h-8 mx-auto text-purple-600 mb-2" />
                            <div class="text-xl font-bold text-purple-900">{{ statistics.by_type.backups }}</div>
                            <div class="text-sm text-purple-700">Backups</div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Modal de Prueba -->
        <Modal :show="showTestModal" @close="showTestModal = false">
            <template #title>Enviar Notificación de Prueba</template>
            
            <form @submit.prevent="sendTestNotification">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                        <select v-model="testForm.type" class="w-full rounded-md border-gray-300">
                            <option value="success">Éxito</option>
                            <option value="info">Información</option>
                            <option value="warning">Advertencia</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
                        <input v-model="testForm.title" type="text" required 
                               class="w-full rounded-md border-gray-300" 
                               placeholder="Título de la notificación">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mensaje</label>
                        <textarea v-model="testForm.message" rows="3" required
                                  class="w-full rounded-md border-gray-300" 
                                  placeholder="Contenido del mensaje..."></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <Button @click="showTestModal = false" variant="secondary">Cancelar</Button>
                    <Button type="submit" :disabled="sendingTest" variant="primary">
                        <span v-if="sendingTest">Enviando...</span>
                        <span v-else>Enviar Prueba</span>
                    </Button>
                </div>
            </form>
        </Modal>

        <!-- Modal de Configuración -->
        <Modal :show="showSettingsModal" @close="showSettingsModal = false" size="lg">
            <template #title>Configuración de Notificaciones</template>
            
            <form @submit.prevent="saveSettings">
                <div class="space-y-6">
                    <!-- Configuración General -->
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Configuración General</h4>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input v-model="settingsForm.email_notifications" type="checkbox" 
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Recibir notificaciones por email</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input v-model="settingsForm.push_notifications" type="checkbox" 
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Recibir notificaciones push</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input v-model="settingsForm.sound_enabled" type="checkbox" 
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Reproducir sonido</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Tipos de Notificación -->
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Tipos de Notificación</h4>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input v-model="settingsForm.notification_types.invoices" type="checkbox" 
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Facturas y documentos</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input v-model="settingsForm.notification_types.payments" type="checkbox" 
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Pagos y cobranzas</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input v-model="settingsForm.notification_types.system" type="checkbox" 
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Eventos del sistema</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input v-model="settingsForm.notification_types.backups" type="checkbox" 
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Backups y mantenimiento</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Horario Silencioso -->
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Horario Silencioso</h4>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input v-model="settingsForm.quiet_hours.enabled" type="checkbox" 
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Activar horario silencioso</span>
                            </label>
                            
                            <div v-if="settingsForm.quiet_hours.enabled" class="grid grid-cols-2 gap-4 ml-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                                    <input v-model="settingsForm.quiet_hours.start" type="time" 
                                           class="w-full rounded-md border-gray-300">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                                    <input v-model="settingsForm.quiet_hours.end" type="time" 
                                           class="w-full rounded-md border-gray-300">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <Button @click="showSettingsModal = false" variant="secondary">Cancelar</Button>
                    <Button type="submit" :disabled="savingSettings" variant="primary">
                        <span v-if="savingSettings">Guardando...</span>
                        <span v-else">Guardar Configuración</span>
                    </Button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/UI/Card.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Modal from '@/Components/Modal.vue'
import { 
    BellIcon, 
    BeakerIcon, 
    CogIcon, 
    TrashIcon,
    DocumentTextIcon,
    CurrencyDollarIcon,
    ExclamationTriangleIcon,
    ServerIcon,
    CheckCircleIcon,
    InformationCircleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    offlineNotifications: Array,
    settings: Object,
    statistics: Object,
    pusherConfig: Object
})

// Estado reactivo
const notifications = ref([...props.offlineNotifications])
const connectionStatus = ref('disconnected')
const showTestModal = ref(false)
const showSettingsModal = ref(false)
const sendingTest = ref(false)
const savingSettings = ref(false)
let pusher = null
let userChannel = null

const testForm = ref({
    type: 'info',
    title: 'Notificación de Prueba',
    message: 'Esta es una notificación de prueba del sistema.'
})

const settingsForm = ref({
    ...props.settings
})

// Métodos
const initializePusher = () => {
    if (typeof window.Pusher === 'undefined') {
        console.error('Pusher not loaded')
        return
    }
    
    pusher = new window.Pusher(props.pusherConfig.key, {
        cluster: props.pusherConfig.cluster,
        forceTLS: props.pusherConfig.forceTLS,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }
    })
    
    // Conexión exitosa
    pusher.connection.bind('connected', () => {
        connectionStatus.value = 'connected'
        console.log('Pusher connected')
    })
    
    // Error de conexión
    pusher.connection.bind('error', (error) => {
        connectionStatus.value = 'error'
        console.error('Pusher connection error:', error)
    })
    
    // Desconexión
    pusher.connection.bind('disconnected', () => {
        connectionStatus.value = 'disconnected'
        console.log('Pusher disconnected')
    })
    
    // Suscribirse al canal del usuario
    const userId = window.Laravel?.user?.id
    if (userId) {
        userChannel = pusher.subscribe(`private-user.${userId}`)
        
        userChannel.bind('notification', (data) => {
            console.log('New notification received:', data)
            addNotification(data.notification)
            playNotificationSound()
        })
        
        userChannel.bind('pusher:subscription_succeeded', () => {
            console.log('Successfully subscribed to user channel')
        })
        
        userChannel.bind('pusher:subscription_error', (error) => {
            console.error('Failed to subscribe to user channel:', error)
        })
    }
}

const addNotification = (notification) => {
    // Agregar al inicio de la lista
    notifications.value.unshift({
        ...notification,
        read: false
    })
    
    // Mantener solo las últimas 50 notificaciones
    if (notifications.value.length > 50) {
        notifications.value = notifications.value.slice(0, 50)
    }
}

const playNotificationSound = () => {
    if (settingsForm.value.sound_enabled) {
        // Crear y reproducir sonido de notificación
        const audio = new Audio('/sounds/notification.mp3')
        audio.volume = 0.5
        audio.play().catch(e => console.log('Could not play notification sound:', e))
    }
}

const handleNotificationClick = (notification) => {
    // Marcar como leída
    markNotificationAsRead(notification)
    
    // Navegar si tiene URL de acción
    if (notification.action_url) {
        if (notification.action_url.startsWith('http')) {
            window.open(notification.action_url, '_blank')
        } else {
            router.visit(notification.action_url)
        }
    }
}

const markNotificationAsRead = async (notification) => {
    if (notification.read) return
    
    try {
        await fetch('/notifications/mark-as-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                notification_id: notification.id
            })
        })
        
        // Actualizar localmente
        notification.read = true
    } catch (error) {
        console.error('Error marking notification as read:', error)
    }
}

const markAllAsRead = async () => {
    try {
        await fetch('/notifications/mark-all-as-read', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        
        // Marcar todas como leídas localmente
        notifications.value.forEach(n => n.read = true)
    } catch (error) {
        console.error('Error marking all as read:', error)
    }
}

const clearNotifications = () => {
    notifications.value = []
}

const sendTestNotification = async () => {
    sendingTest.value = true
    
    try {
        router.post('/notifications/send-test', testForm.value, {
            onFinish: () => {
                sendingTest.value = false
                showTestModal.value = false
            }
        })
    } catch (error) {
        sendingTest.value = false
    }
}

const saveSettings = async () => {
    savingSettings.value = true
    
    try {
        router.post('/notifications/settings', settingsForm.value, {
            onFinish: () => {
                savingSettings.value = false
                showSettingsModal.value = false
            }
        })
    } catch (error) {
        savingSettings.value = false
    }
}

const getNotificationIcon = (iconName) => {
    const iconMap = {
        'check-circle': CheckCircleIcon,
        'information-circle': InformationCircleIcon,
        'exclamation-triangle': ExclamationTriangleIcon,
        'x-circle': XCircleIcon,
        'bell': BellIcon,
        'document-text': DocumentTextIcon,
        'currency-dollar': CurrencyDollarIcon,
        'server': ServerIcon
    }
    
    return iconMap[iconName] || BellIcon
}

const getPriorityClasses = (priority) => {
    return {
        'border-l-4 border-l-red-500': priority === 'high',
        'border-l-4 border-l-yellow-500': priority === 'medium',
        'border-l-4 border-l-blue-500': priority === 'low'
    }
}

const getIconBackgroundClass = (type) => {
    const classes = {
        'success': 'bg-green-100',
        'error': 'bg-red-100',
        'warning': 'bg-yellow-100',
        'info': 'bg-blue-100'
    }
    
    return classes[type] || 'bg-gray-100'
}

const getIconClass = (type) => {
    const classes = {
        'success': 'text-green-600',
        'error': 'text-red-600',
        'warning': 'text-yellow-600',
        'info': 'text-blue-600'
    }
    
    return classes[type] || 'text-gray-600'
}

const formatRelativeTime = (timestamp) => {
    const now = new Date()
    const time = new Date(timestamp)
    const diff = now - time
    
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(diff / 3600000)
    const days = Math.floor(diff / 86400000)
    
    if (minutes < 1) return 'Ahora'
    if (minutes < 60) return `${minutes}m`
    if (hours < 24) return `${hours}h`
    return `${days}d`
}

// Lifecycle
onMounted(() => {
    // Cargar Pusher script si no está disponible
    if (typeof window.Pusher === 'undefined') {
        const script = document.createElement('script')
        script.src = 'https://js.pusher.com/8.2.0/pusher.min.js'
        script.onload = initializePusher
        document.head.appendChild(script)
    } else {
        initializePusher()
    }
})

onUnmounted(() => {
    if (pusher) {
        if (userChannel) {
            pusher.unsubscribe(userChannel.name)
        }
        pusher.disconnect()
    }
})
</script>

<style scoped>
.notification-enter-active, .notification-leave-active {
    transition: all 0.3s ease;
}

.notification-enter-from {
    opacity: 0;
    transform: translateX(-100%);
}

.notification-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.notification-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>