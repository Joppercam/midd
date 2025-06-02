<template>
  <div class="push-notifications">
    <!-- Notification Bell Icon -->
    <div class="relative">
      <button
        @click="toggleNotifications"
        class="relative p-2 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        :class="{ 'text-indigo-600': hasUnread }"
      >
        <BellIcon class="h-6 w-6" />
        <span
          v-if="unreadCount > 0"
          class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full"
        >
          {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
      </button>

      <!-- Notifications Dropdown -->
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-1"
      >
        <div
          v-if="showNotifications"
          class="absolute right-0 z-50 mt-2 w-80 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <h3 class="text-sm font-medium text-gray-900">Notificaciones</h3>
            <div class="flex space-x-2">
              <button
                v-if="unreadCount > 0"
                @click="markAllAsRead"
                class="text-xs text-indigo-600 hover:text-indigo-800"
              >
                Marcar todas como leídas
              </button>
              <button
                @click="clearAll"
                class="text-xs text-gray-500 hover:text-gray-700"
              >
                Limpiar
              </button>
            </div>
          </div>

          <!-- Notifications List -->
          <div class="max-h-96 overflow-y-auto">
            <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-gray-500">
              <BellSlashIcon class="mx-auto h-8 w-8 mb-2" />
              <p class="text-sm">No hay notificaciones</p>
            </div>

            <div v-else>
              <div
                v-for="notification in notifications"
                :key="notification.id"
                class="px-4 py-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                :class="{ 'bg-blue-50': !notification.read }"
                @click="handleNotificationClick(notification)"
              >
                <div class="flex items-start space-x-3">
                  <!-- Icon -->
                  <div class="flex-shrink-0">
                    <component
                      :is="getNotificationIcon(notification.icon)"
                      class="h-5 w-5"
                      :class="getNotificationIconColor(notification.priority)"
                    />
                  </div>

                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900" :class="{ 'font-semibold': !notification.read }">
                      {{ notification.title }}
                    </p>
                    <p class="text-sm text-gray-700 mt-1">
                      {{ notification.message }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                      {{ formatTimestamp(notification.timestamp) }}
                    </p>
                  </div>

                  <!-- Unread indicator -->
                  <div v-if="!notification.read" class="flex-shrink-0">
                    <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div v-if="typeof route === 'function' && route.has && route.has('notifications.index')" class="px-4 py-3 border-t border-gray-200">
            <Link
              :href="route('notifications.index')"
              class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
            >
              Ver todas las notificaciones
            </Link>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Toast Notifications -->
    <div class="fixed top-4 right-4 z-50 space-y-2">
      <Transition
        v-for="toast in toastNotifications"
        :key="toast.id"
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          class="max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden"
          :class="getToastBackgroundColor(toast.priority)"
        >
          <div class="p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <component
                  :is="getNotificationIcon(toast.icon)"
                  class="h-6 w-6"
                  :class="getToastIconColor(toast.priority)"
                />
              </div>
              <div class="ml-3 w-0 flex-1 pt-0.5">
                <p class="text-sm font-medium text-gray-900">{{ toast.title }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ toast.message }}</p>
                <div v-if="toast.action_url" class="mt-3">
                  <Link
                    :href="toast.action_url"
                    class="bg-white rounded-md text-sm font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    Ver detalles
                  </Link>
                </div>
              </div>
              <div class="ml-4 flex-shrink-0 flex">
                <button
                  @click="dismissToast(toast.id)"
                  class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <XMarkIcon class="h-5 w-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Progress Notifications -->
    <div v-if="progressOperations.length > 0" class="fixed bottom-4 right-4 z-50 space-y-2">
      <div
        v-for="operation in progressOperations"
        :key="operation.operation_id"
        class="max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden"
      >
        <div class="p-4">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
            </div>
            <div class="ml-3 flex-1">
              <p class="text-sm font-medium text-gray-900">{{ operation.message || 'Procesando...' }}</p>
              <div class="mt-2">
                <div class="bg-gray-200 rounded-full h-2">
                  <div
                    class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                    :style="{ width: operation.progress + '%' }"
                  ></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ operation.progress }}%</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  BellIcon,
  BellSlashIcon,
  XMarkIcon,
  DocumentTextIcon,
  CurrencyDollarIcon,
  ExclamationCircleIcon,
  ServerIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon
} from '@heroicons/vue/24/outline'

// Props
const props = defineProps({
  maxVisible: {
    type: Number,
    default: 10
  },
  toastDuration: {
    type: Number,
    default: 5000
  }
})

// State
const showNotifications = ref(false)
const notifications = ref([])
const toastNotifications = ref([])
const progressOperations = ref([])
const pusher = ref(null)
const userChannel = ref(null)
const systemChannel = ref(null)

// Computed
const unreadCount = computed(() => {
  return notifications.value.filter(n => !n.read).length
})

const hasUnread = computed(() => {
  return unreadCount.value > 0
})

// Methods
const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value
}

const markAllAsRead = async () => {
  try {
    // Verificar que la ruta existe
    if (typeof route === 'function' && route.has && route.has('notifications.mark-all-read')) {
      await fetch(route('notifications.mark-all-read'), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Content-Type': 'application/json'
        }
      })
    }
    
    notifications.value.forEach(n => n.read = true)
  } catch (error) {
    console.error('Failed to mark notifications as read:', error)
  }
}

const clearAll = () => {
  notifications.value = []
  localStorage.removeItem('notifications')
}

const handleNotificationClick = async (notification) => {
  if (!notification.read) {
    await markAsRead(notification.id)
  }
  
  if (notification.action_url) {
    window.location.href = notification.action_url
  }
  
  showNotifications.value = false
}

const markAsRead = async (notificationId) => {
  try {
    // Verificar que la ruta existe
    if (typeof route === 'function' && route.has && route.has('notifications.mark-read')) {
      await fetch(route('notifications.mark-read', notificationId), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      })
    }
    
    const notification = notifications.value.find(n => n.id === notificationId)
    if (notification) {
      notification.read = true
    }
  } catch (error) {
    console.error('Failed to mark notification as read:', error)
  }
}

const addNotification = (notification) => {
  // Add to notifications list
  notifications.value.unshift(notification)
  
  // Keep only recent notifications
  if (notifications.value.length > props.maxVisible) {
    notifications.value = notifications.value.slice(0, props.maxVisible)
  }
  
  // Show toast for high priority notifications
  if (notification.priority === 'high' || notification.type === 'system_event') {
    showToast(notification)
  }
  
  // Store in localStorage
  localStorage.setItem('notifications', JSON.stringify(notifications.value))
}

const showToast = (notification) => {
  const toast = { ...notification, id: `toast_${Date.now()}` }
  toastNotifications.value.push(toast)
  
  // Auto-dismiss after duration
  setTimeout(() => {
    dismissToast(toast.id)
  }, props.toastDuration)
}

const dismissToast = (toastId) => {
  const index = toastNotifications.value.findIndex(t => t.id === toastId)
  if (index > -1) {
    toastNotifications.value.splice(index, 1)
  }
}

const updateProgress = (data) => {
  const existing = progressOperations.value.find(op => op.operation_id === data.operation_id)
  
  if (existing) {
    Object.assign(existing, data)
    
    // Remove completed operations
    if (data.progress >= 100) {
      setTimeout(() => {
        const index = progressOperations.value.findIndex(op => op.operation_id === data.operation_id)
        if (index > -1) {
          progressOperations.value.splice(index, 1)
        }
      }, 2000)
    }
  } else {
    progressOperations.value.push(data)
  }
}

const getNotificationIcon = (iconName) => {
  const icons = {
    'bell': BellIcon,
    'document-text': DocumentTextIcon,
    'currency-dollar': CurrencyDollarIcon,
    'exclamation-circle': ExclamationCircleIcon,
    'server': ServerIcon,
    'check-circle': CheckCircleIcon,
    'exclamation-triangle': ExclamationTriangleIcon,
    'information-circle': InformationCircleIcon
  }
  
  return icons[iconName] || BellIcon
}

const getNotificationIconColor = (priority) => {
  return {
    'high': 'text-red-600',
    'medium': 'text-yellow-600',
    'low': 'text-gray-600'
  }[priority] || 'text-gray-600'
}

const getToastBackgroundColor = (priority) => {
  return {
    'high': 'border-l-4 border-red-500',
    'medium': 'border-l-4 border-yellow-500',
    'low': 'border-l-4 border-blue-500'
  }[priority] || 'border-l-4 border-blue-500'
}

const getToastIconColor = (priority) => {
  return {
    'high': 'text-red-500',
    'medium': 'text-yellow-500',
    'low': 'text-blue-500'
  }[priority] || 'text-blue-500'
}

const formatTimestamp = (timestamp) => {
  const date = new Date(timestamp)
  const now = new Date()
  const diff = now - date
  
  if (diff < 60000) { // Less than 1 minute
    return 'Hace un momento'
  } else if (diff < 3600000) { // Less than 1 hour
    const minutes = Math.floor(diff / 60000)
    return `Hace ${minutes} minuto${minutes > 1 ? 's' : ''}`
  } else if (diff < 86400000) { // Less than 1 day
    const hours = Math.floor(diff / 3600000)
    return `Hace ${hours} hora${hours > 1 ? 's' : ''}`
  } else {
    return date.toLocaleDateString('es-CL')
  }
}

const loadOfflineNotifications = async () => {
  try {
    // Verificar que la ruta existe antes de hacer fetch
    if (typeof route === 'function' && route.has && route.has('notifications.offline')) {
      const response = await fetch(route('notifications.offline'))
      if (response.ok) {
        const offlineNotifications = await response.json()
        offlineNotifications.forEach(notification => {
          addNotification(notification)
        })
      }
    }
  } catch (error) {
    console.error('Failed to load offline notifications:', error)
  }
}

const initializePusher = () => {
  try {
    // Verificar que Pusher esté disponible
    if (typeof Pusher === 'undefined') {
      console.warn('Pusher is not available - real-time notifications disabled')
      return
    }
    
    // Verificar configuración
    if (!window.pusherConfig || !window.pusherConfig.key) {
      console.warn('Pusher configuration missing - real-time notifications disabled')
      return
    }
    
    // Initialize Pusher
    pusher.value = new Pusher(window.pusherConfig.key, {
      cluster: window.pusherConfig.cluster,
      encrypted: true
    })
    
    const user = usePage().props.auth.user
    
    // Subscribe to user channel
    userChannel.value = pusher.value.subscribe(`user.${user.id}`)
    
    // Listen for notifications
    userChannel.value.bind('notification', (data) => {
      addNotification(data)
    })
    
    // Listen for progress updates
    userChannel.value.bind('progress', (data) => {
      updateProgress(data)
    })
    
    // Subscribe to system channel
    systemChannel.value = pusher.value.subscribe('system')
    systemChannel.value.bind('notification', (data) => {
      addNotification(data)
    })
    
    // Subscribe to tenant channel for entity updates
    if (user.tenant_id) {
      const tenantChannel = pusher.value.subscribe(`tenant.${user.tenant_id}`)
      tenantChannel.bind('entity.updated', (data) => {
        // Handle entity updates (refresh data, show notifications, etc.)
        console.log('Entity updated:', data)
      })
    }
  } catch (error) {
    console.error('Failed to initialize Pusher:', error)
  }
}

// Lifecycle
onMounted(() => {
  // Load stored notifications
  const stored = localStorage.getItem('notifications')
  if (stored) {
    notifications.value = JSON.parse(stored)
  }
  
  // Load offline notifications
  loadOfflineNotifications()
  
  // Initialize real-time notifications
  if (window.pusherConfig) {
    initializePusher()
  }
  
  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.push-notifications')) {
      showNotifications.value = false
    }
  })
})

onUnmounted(() => {
  if (pusher.value) {
    pusher.value.disconnect()
  }
})
</script>

<style scoped>
.push-notifications {
  /* Custom styles if needed */
}
</style>