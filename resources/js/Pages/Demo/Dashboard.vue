<template>
    <Head title="Demo MIDD" />

    <div class="min-h-screen bg-gray-50">
        <!-- Demo Banner -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-4 py-2 text-center">
            <div class="flex items-center justify-center space-x-4">
                <span class="text-sm font-medium">🧪 Modo Demo - Explora todas las funcionalidades sin compromiso</span>
                <div class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded">
                    ⏰ Tiempo restante: {{ timeRemaining }}
                </div>
                <button
                    v-if="canExtend"
                    @click="extendSession"
                    class="text-xs bg-white bg-opacity-20 hover:bg-opacity-30 px-2 py-1 rounded transition-colors"
                >
                    + Extender
                </button>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="bg-white shadow-sm border-b navigation-menu">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center space-x-8">
                        <!-- Logo -->
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-br from-emerald-400 via-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <span class="text-xl font-bold text-gray-900">MIDD</span>
                            <span class="bg-emerald-100 text-emerald-800 text-xs font-semibold px-2 py-1 rounded-full">DEMO</span>
                        </div>

                        <!-- Menu Items -->
                        <div class="hidden md:flex space-x-6">
                            <a href="#" class="text-blue-600 border-b-2 border-blue-600 py-2 text-sm font-medium">Dashboard</a>
                            <a href="#" class="text-gray-500 hover:text-gray-700 py-2 text-sm font-medium">Facturación</a>
                            <a href="#" class="text-gray-500 hover:text-gray-700 py-2 text-sm font-medium">Inventario</a>
                            <a href="#" class="text-gray-500 hover:text-gray-700 py-2 text-sm font-medium">Reportes</a>
                            <a href="#" class="text-gray-500 hover:text-gray-700 py-2 text-sm font-medium">Configuración</a>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <button class="p-2 text-gray-400 hover:text-gray-500 notifications-bell">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM11 19H7a2 2 0 01-2-2V7a2 2 0 012-2h5m4 0v6m0 0l4-4m-4 4l-4-4"/>
                            </svg>
                            <span class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">3</span>
                        </button>

                        <!-- User Menu -->
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium text-gray-700">DU</span>
                            </div>
                            <span class="text-sm text-gray-700">Demo User</span>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Welcome Section -->
            <div class="px-4 py-6 sm:px-0">
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">¡Bienvenido a MIDD! 🚀</h1>
                    <p class="text-gray-600 mb-4">
                        Estás explorando nuestra plataforma en modo demo. Puedes probar todas las funcionalidades sin limitaciones.
                    </p>
                    <div class="flex items-center space-x-4">
                        <button
                            @click="startTour"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        >
                            🗺️ Iniciar Tour Guiado
                        </button>
                        <button
                            @click="openChatbot"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        >
                            💬 Abrir Asistente
                        </button>
                    </div>
                </div>

                <!-- Dashboard Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 dashboard-metrics">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Ventas del Mes</dt>
                                        <dd class="text-lg font-medium text-gray-900">${{ formatNumber(demoData.metrics.monthly_sales) }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Facturas Pendientes</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ demoData.metrics.pending_invoices }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-purple-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Clientes Activos</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ demoData.metrics.active_customers }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-orange-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Productos</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ demoData.metrics.products_count }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white shadow rounded-lg p-6 mb-8 quick-actions">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Acciones Rápidas</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <button class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">Nueva Factura</span>
                        </button>
                        
                        <button class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">Nuevo Cliente</span>
                        </button>
                        
                        <button class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">Nuevo Producto</span>
                        </button>
                        
                        <button class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <svg class="w-8 h-8 text-orange-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">Ver Reportes</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Actividad Reciente</h2>
                    <div class="space-y-4">
                        <div v-for="activity in demoData.recent_activities" :key="activity.description" class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-900">{{ activity.description }}</p>
                                <p class="text-xs text-gray-500">Hace {{ activity.time }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Chatbot -->
        <DemoChatbot 
            :demo-session-id="demoSessionId"
            :user-progress="userProgress"
            ref="chatbot"
        />
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import DemoChatbot from '@/Components/DemoChatbot.vue';

const props = defineProps({
    demo_session_id: String,
    demo_config: Object,
    tour_steps: Array,
    demo_data: Object,
});

const demoSessionId = props.demo_session_id;
const demoData = props.demo_data;
const chatbot = ref(null);
const sessionStartTime = ref(Date.now());
const currentTime = ref(Date.now());
const canExtend = ref(true);
const userProgress = ref({
    steps_completed: 0,
    current_section: 'dashboard'
});

// Actualizar tiempo cada segundo
let timeInterval;

onMounted(() => {
    timeInterval = setInterval(() => {
        currentTime.value = Date.now();
    }, 1000);
    
    // Mostrar mensaje de bienvenida del chatbot después de 3 segundos
    setTimeout(() => {
        if (chatbot.value) {
            chatbot.value.hasNewMessage = true;
        }
    }, 3000);
});

onUnmounted(() => {
    if (timeInterval) {
        clearInterval(timeInterval);
    }
});

const timeRemaining = computed(() => {
    const elapsed = Math.floor((currentTime.value - sessionStartTime.value) / 1000);
    const remaining = Math.max(0, (30 * 60) - elapsed); // 30 minutos
    
    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;
    
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('es-CL').format(num);
};

const startTour = () => {
    // Implementar tour guiado
    alert('¡Tour guiado próximamente! Por ahora puedes usar el chatbot para obtener ayuda paso a paso.');
};

const openChatbot = () => {
    if (chatbot.value) {
        chatbot.value.toggleChat();
    }
};

const extendSession = () => {
    if (confirm('¿Quieres extender tu sesión por 30 minutos más?')) {
        // Llamar a la API para extender sesión
        fetch(`/demo/${demoSessionId}/extend`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                sessionStartTime.value = Date.now(); // Reset timer
                alert(data.message);
            } else {
                alert(data.message);
                canExtend.value = false;
            }
        })
        .catch(error => {
            console.error('Error extending session:', error);
        });
    }
};
</script>

<style scoped>
/* Estilos para el tour y highlights */
.highlight-demo {
    @apply ring-4 ring-yellow-400 ring-opacity-75 transition-all duration-1000;
    animation: pulse-highlight 2s infinite;
}

@keyframes pulse-highlight {
    0%, 100% {
        @apply ring-opacity-75;
    }
    50% {
        @apply ring-opacity-100;
    }
}
</style>