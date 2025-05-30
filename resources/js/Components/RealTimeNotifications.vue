<template>
    <div class="fixed top-4 right-4 z-50 space-y-2 max-w-sm">
        <TransitionGroup name="notification" tag="div">
            <div
                v-for="notification in visibleNotifications"
                :key="notification.id"
                :class="[
                    'p-4 rounded-lg shadow-lg border-l-4 cursor-pointer transform transition-all duration-300',
                    getNotificationClass(notification.color)
                ]"
                @click="dismissNotification(notification.id)"
            >
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <component 
                            :is="getIcon(notification.icon)" 
                            :class="getIconClass(notification.color)"
                        />
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ notification.title }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ notification.message }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ formatTime(notification.timestamp) }}
                        </p>
                    </div>
                    <button
                        @click.stop="dismissNotification(notification.id)"
                        class="ml-2 text-gray-400 hover:text-gray-600"
                    >
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </TransitionGroup>
    </div>

    <!-- Badge de notificaciones -->
    <div class="relative">
        <button
            @click="toggleNotificationPanel"
            class="p-2 text-gray-400 hover:text-gray-600 relative"
        >
            <BellIcon class="h-6 w-6" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Panel de notificaciones -->
        <div
            v-if="showPanel"
            class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-xl border z-50"
        >
            <div class="p-4 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Notificaciones</h3>
                    <button
                        v-if="notifications.length > 0"
                        @click="markAllAsRead"
                        class="text-sm text-blue-600 hover:text-blue-800"
                    >
                        Marcar todo como leído
                    </button>
                </div>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <div
                    v-for="notification in notifications.slice(0, 10)"
                    :key="notification.id"
                    :class="[
                        'p-4 border-b hover:bg-gray-50 dark:hover:bg-gray-700',
                        { 'bg-blue-50 dark:bg-blue-900/20': !notification.read }
                    ]"
                    @click="markAsRead(notification.id)"
                >
                    <div class="flex items-start">
                        <component 
                            :is="getIcon(notification.icon)" 
                            :class="['h-5 w-5 mr-3 mt-0.5', getIconClass(notification.color)]"
                        />
                        <div class="flex-1">
                            <p class="text-sm font-medium">{{ notification.title }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ notification.message }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ formatTime(notification.timestamp) }}
                            </p>
                        </div>
                    </div>
                </div>
                <div v-if="notifications.length === 0" class="p-8 text-center text-gray-500">
                    No hay notificaciones
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import {
    BellIcon,
    XMarkIcon,
    DocumentTextIcon,
    CurrencyDollarIcon,
    ExclamationTriangleIcon,
    BuildingLibraryIcon,
    ExclamationCircleIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline'

const page = usePage()
const user = computed(() => page.props.auth.user)
const tenant = computed(() => page.props.auth.tenant)

// Estado reactivo
const notifications = ref([])
const visibleNotifications = ref([])
const showPanel = ref(false)
const echo = ref(null)

// Computed
const unreadCount = computed(() => 
    notifications.value.filter(n => !n.read).length
)

// Configurar Laravel Echo
const setupEcho = () => {
    if (typeof window !== 'undefined') {
        window.Pusher = Pusher

        echo.value = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
            wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
            wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        })
    }
}

// Escuchar notificaciones
const listenForNotifications = () => {
    if (!echo.value || !user.value) return

    // Escuchar canal del usuario
    echo.value.private(`user.${user.value.id}`)
        .listen('.notification', (event) => {
            addNotification(event)
        })

    // Escuchar canal del tenant
    if (tenant.value) {
        echo.value.private(`tenant.${tenant.value.id}`)
            .listen('.notification', (event) => {
                addNotification(event)
            })
    }
}

// Agregar notificación
const addNotification = (event) => {
    const notification = {
        id: Date.now() + Math.random(),
        ...event.data,
        timestamp: event.timestamp || new Date().toISOString(),
        read: false
    }

    notifications.value.unshift(notification)
    showToastNotification(notification)

    // Reproducir sonido
    playNotificationSound()
}

// Mostrar notificación toast
const showToastNotification = (notification) => {
    visibleNotifications.value.unshift(notification)
    
    // Auto-dismiss después de 5 segundos
    setTimeout(() => {
        dismissNotification(notification.id)
    }, 5000)
}

// Descartar notificación toast
const dismissNotification = (id) => {
    const index = visibleNotifications.value.findIndex(n => n.id === id)
    if (index > -1) {
        visibleNotifications.value.splice(index, 1)
    }
}

// Marcar como leída
const markAsRead = (id) => {
    const notification = notifications.value.find(n => n.id === id)
    if (notification) {
        notification.read = true
    }
}

// Marcar todas como leídas
const markAllAsRead = () => {
    notifications.value.forEach(n => n.read = true)
}

// Toggle panel
const toggleNotificationPanel = () => {
    showPanel.value = !showPanel.value
}

// Funciones de utilidad
const getIcon = (iconType) => {
    const icons = {
        invoice: DocumentTextIcon,
        payment: CurrencyDollarIcon,
        warning: ExclamationTriangleIcon,
        bank: BuildingLibraryIcon,
        alert: ExclamationCircleIcon,
        info: InformationCircleIcon
    }
    return icons[iconType] || InformationCircleIcon
}

const getNotificationClass = (color) => {
    const classes = {
        green: 'bg-white border-green-400 shadow-lg',
        blue: 'bg-white border-blue-400 shadow-lg',
        orange: 'bg-white border-orange-400 shadow-lg',
        yellow: 'bg-white border-yellow-400 shadow-lg',
        red: 'bg-white border-red-400 shadow-lg'
    }
    return classes[color] || classes.blue
}

const getIconClass = (color) => {
    const classes = {
        green: 'text-green-500',
        blue: 'text-blue-500',
        orange: 'text-orange-500',
        yellow: 'text-yellow-500',
        red: 'text-red-500'
    }
    return classes[color] || classes.blue
}

const formatTime = (timestamp) => {
    return new Date(timestamp).toLocaleString('es-CL', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit'
    })
}

const playNotificationSound = () => {
    try {
        const audio = new Audio('/sounds/notification.mp3')
        audio.volume = 0.3
        audio.play().catch(() => {
            // Silenciar error si no se puede reproducir
        })
    } catch (e) {
        // Silenciar error
    }
}

// Lifecycle
onMounted(() => {
    setupEcho()
    setTimeout(() => {
        listenForNotifications()
    }, 1000)
})

onUnmounted(() => {
    if (echo.value) {
        echo.value.disconnect()
    }
})

// Click outside para cerrar panel
const handleClickOutside = (event) => {
    if (!event.target.closest('[data-notification-panel]')) {
        showPanel.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.notification-enter-active,
.notification-leave-active {
    transition: all 0.3s ease;
}

.notification-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.notification-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>