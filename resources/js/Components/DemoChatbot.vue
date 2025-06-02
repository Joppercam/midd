<template>
    <div class="fixed bottom-4 right-4 z-50">
        <!-- Botón del Chatbot -->
        <button
            v-if="!isOpen"
            @click="toggleChat"
            class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-full p-4 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 animate-pulse"
            :class="{ 'animate-bounce': hasNewMessage }"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-2.05-.235l-3.95 1.185 1.185-3.95A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"/>
            </svg>
            <span v-if="hasNewMessage" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">!</span>
        </button>

        <!-- Ventana del Chat -->
        <div
            v-show="isOpen"
            class="bg-white rounded-lg shadow-2xl border border-gray-200 w-80 h-96 flex flex-col transition-all duration-300 transform"
            :class="isOpen ? 'scale-100 opacity-100' : 'scale-95 opacity-0'"
        >
            <!-- Header del Chat -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-lg flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 2.98.97 4.29L1 23l6.71-1.97C9.02 21.64 10.46 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.21 0-2.35-.28-3.37-.78L7 20l.78-1.63C7.28 17.35 7 16.21 7 15c0-2.76 2.24-5 5-5s5 2.24 5 5-2.24 5-5 5z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm">{{ config.name }}</h3>
                        <p class="text-xs text-blue-100" v-if="isTyping">Escribiendo...</p>
                        <p class="text-xs text-blue-100" v-else>En línea</p>
                    </div>
                </div>
                <button @click="toggleChat" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Área de Mensajes -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3" ref="messagesContainer">
                <!-- Mensaje de Bienvenida -->
                <div v-if="messages.length === 0" class="space-y-3">
                    <div class="flex items-start space-x-2">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 2.98.97 4.29L1 23l6.71-1.97C9.02 21.64 10.46 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.21 0-2.35-.28-3.37-.78L7 20l.78-1.63C7.28 17.35 7 16.21 7 15c0-2.76 2.24-5 5-5s5 2.24 5 5-2.24 5-5 5z"/>
                            </svg>
                        </div>
                        <div class="bg-gray-100 rounded-lg px-3 py-2 max-w-xs">
                            <p class="text-sm text-gray-800">{{ config.welcome_message }}</p>
                        </div>
                    </div>
                    
                    <!-- Sugerencias Rápidas -->
                    <div class="space-y-2">
                        <p class="text-xs text-gray-500 font-medium">Sugerencias rápidas:</p>
                        <button
                            v-for="suggestion in config.auto_suggestions"
                            :key="suggestion"
                            @click="sendQuickMessage(suggestion)"
                            class="block w-full text-left text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg px-3 py-2 transition-colors"
                        >
                            {{ suggestion }}
                        </button>
                    </div>
                </div>

                <!-- Mensajes del Chat -->
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex items-start space-x-2"
                    :class="message.sender === 'user' ? 'flex-row-reverse space-x-reverse' : ''"
                >
                    <div
                        v-if="message.sender === 'bot'"
                        class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0"
                    >
                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 2.98.97 4.29L1 23l6.71-1.97C9.02 21.64 10.46 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.21 0-2.35-.28-3.37-.78L7 20l.78-1.63C7.28 17.35 7 16.21 7 15c0-2.76 2.24-5 5-5s5 2.24 5 5-2.24 5-5 5z"/>
                        </svg>
                    </div>
                    
                    <div
                        class="rounded-lg px-3 py-2 max-w-xs"
                        :class="message.sender === 'user' 
                            ? 'bg-blue-600 text-white' 
                            : 'bg-gray-100 text-gray-800'"
                    >
                        <p class="text-sm" v-html="message.content"></p>
                        <div v-if="message.actions" class="mt-2 space-y-1">
                            <button
                                v-for="action in message.actions"
                                :key="action.text"
                                @click="executeAction(action)"
                                class="block w-full text-left text-xs bg-white bg-opacity-20 hover:bg-opacity-30 rounded px-2 py-1 transition-colors"
                            >
                                {{ action.text }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Indicador de Escritura -->
                <div v-if="isTyping" class="flex items-start space-x-2">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 2.98.97 4.29L1 23l6.71-1.97C9.02 21.64 10.46 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.21 0-2.35-.28-3.37-.78L7 20l.78-1.63C7.28 17.35 7 16.21 7 15c0-2.76 2.24-5 5-5s5 2.24 5 5-2.24 5-5 5z"/>
                        </svg>
                    </div>
                    <div class="bg-gray-100 rounded-lg px-3 py-2">
                        <div class="flex space-x-1">
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input de Mensaje -->
            <div class="p-4 border-t border-gray-200">
                <div class="flex space-x-2">
                    <input
                        v-model="currentMessage"
                        @keypress.enter="sendMessage"
                        type="text"
                        placeholder="Escribe tu pregunta..."
                        class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        :disabled="isTyping"
                    />
                    <button
                        @click="sendMessage"
                        :disabled="!currentMessage.trim() || isTyping"
                        class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg px-3 py-2 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, nextTick, onMounted } from 'vue';

const props = defineProps({
    demoSessionId: String,
    userProgress: Object
});

const isOpen = ref(false);
const currentMessage = ref('');
const isTyping = ref(false);
const hasNewMessage = ref(false);
const messages = ref([]);
const messagesContainer = ref(null);

const config = reactive({
    name: 'MIDD Assistant',
    avatar: '/images/chatbot-avatar.svg',
    welcome_message: '¡Hola! Soy tu asistente virtual. Te ayudaré a explorar todas las funcionalidades de MIDD. ¿Por dónde te gustaría empezar?',
    auto_suggestions: [
        '¿Cómo crear mi primera factura?',
        'Mostrarme el dashboard principal',
        '¿Cómo funciona la integración con SII?',
        'Ver reportes disponibles',
        'Configurar mi empresa',
    ]
});

// Base de conocimiento del chatbot
const knowledgeBase = {
    'factura': {
        keywords: ['factura', 'facturar', 'documento', 'dte', 'boleta'],
        response: 'Para crear una factura en MIDD:\n\n1. Ve al módulo de **Facturación**\n2. Haz clic en "Nueva Factura"\n3. Selecciona el cliente\n4. Agrega productos o servicios\n5. Revisa los datos y envía al SII\n\n¿Te gustaría que te lleve directamente al módulo de facturación?',
        actions: [
            { text: 'Ir a Facturación', type: 'navigate', target: '/invoices' },
            { text: 'Ver tutorial completo', type: 'tutorial', target: 'invoicing' }
        ]
    },
    'dashboard': {
        keywords: ['dashboard', 'inicio', 'panel', 'resumen'],
        response: 'El dashboard de MIDD te muestra:\n\n📊 **Métricas clave** de tu empresa\n💰 **Ingresos y gastos** del mes\n📈 **Gráficos de ventas**\n🔔 **Notificaciones importantes**\n📋 **Tareas pendientes**\n\n¿Te gustaría que te explique alguna sección específica?',
        actions: [
            { text: 'Ver métricas', type: 'highlight', target: '.metrics-cards' },
            { text: 'Explicar gráficos', type: 'tutorial', target: 'charts' }
        ]
    },
    'sii': {
        keywords: ['sii', 'integración', 'dte', 'certificado', 'timbraje'],
        response: 'La integración con el SII en MIDD permite:\n\n✅ **Envío automático** de DTEs\n🔐 **Certificados digitales** seguros\n📋 **Folios automáticos**\n📊 **Seguimiento de estados**\n\nPara configurar necesitas:\n- Certificado digital (.p12)\n- Resolución de folios\n- Ambiente de certificación/producción',
        actions: [
            { text: 'Configurar SII', type: 'navigate', target: '/sii/configuration' },
            { text: 'Subir certificado', type: 'modal', target: 'upload-certificate' }
        ]
    },
    'reportes': {
        keywords: ['reporte', 'informe', 'análisis', 'estadística'],
        response: 'MIDD ofrece diversos reportes:\n\n📈 **Ventas por período**\n💼 **Estado de clientes**\n📦 **Inventario actual**\n💰 **Flujo de caja**\n📊 **Libro de ventas/compras**\n🎯 **Análisis de rentabilidad**',
        actions: [
            { text: 'Ver reportes', type: 'navigate', target: '/reports' },
            { text: 'Generar reporte personalizado', type: 'tutorial', target: 'custom-reports' }
        ]
    },
    'empresa': {
        keywords: ['empresa', 'configurar', 'datos', 'rut', 'dirección'],
        response: 'Para configurar los datos de tu empresa:\n\n🏢 **Información básica** (nombre, RUT, giro)\n📍 **Dirección fiscal**\n📧 **Datos de contacto**\n🎨 **Logo y colores corporativos**\n⚙️ **Configuraciones fiscales**',
        actions: [
            { text: 'Configurar empresa', type: 'navigate', target: '/company-settings' },
            { text: 'Subir logo', type: 'tutorial', target: 'company-logo' }
        ]
    }
};

const toggleChat = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        hasNewMessage.value = false;
        nextTick(() => {
            scrollToBottom();
        });
    }
};

const sendMessage = async () => {
    if (!currentMessage.value.trim()) return;
    
    const userMessage = {
        id: Date.now(),
        sender: 'user',
        content: currentMessage.value,
        timestamp: new Date()
    };
    
    messages.value.push(userMessage);
    const messageText = currentMessage.value;
    currentMessage.value = '';
    
    // Scroll y mostrar que está escribiendo
    await nextTick();
    scrollToBottom();
    isTyping.value = true;
    
    // Simular delay de respuesta
    setTimeout(() => {
        const response = getBotResponse(messageText);
        messages.value.push(response);
        isTyping.value = false;
        
        nextTick(() => {
            scrollToBottom();
        });
    }, 1000 + Math.random() * 2000);
};

const sendQuickMessage = (message) => {
    currentMessage.value = message;
    sendMessage();
};

const getBotResponse = (message) => {
    const lowerMessage = message.toLowerCase();
    
    // Buscar en la base de conocimiento
    for (const [topic, data] of Object.entries(knowledgeBase)) {
        if (data.keywords.some(keyword => lowerMessage.includes(keyword))) {
            return {
                id: Date.now(),
                sender: 'bot',
                content: data.response,
                actions: data.actions || [],
                timestamp: new Date()
            };
        }
    }
    
    // Respuestas por defecto
    const defaultResponses = [
        {
            id: Date.now(),
            sender: 'bot',
            content: 'Interesante pregunta. Te puedo ayudar con:\n\n• **Facturación electrónica**\n• **Gestión de inventario**\n• **Reportes y análisis**\n• **Configuración del sistema**\n• **Integración con SII**\n\n¿Sobre cuál de estos temas te gustaría saber más?',
            timestamp: new Date()
        }
    ];
    
    return defaultResponses[0];
};

const executeAction = (action) => {
    switch (action.type) {
        case 'navigate':
            window.location.href = action.target;
            break;
        case 'highlight':
            highlightElement(action.target);
            break;
        case 'tutorial':
            startTutorial(action.target);
            break;
        case 'modal':
            openModal(action.target);
            break;
    }
};

const highlightElement = (selector) => {
    const element = document.querySelector(selector);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.classList.add('highlight-demo');
        setTimeout(() => {
            element.classList.remove('highlight-demo');
        }, 3000);
    }
};

const startTutorial = (tutorialId) => {
    // Implementar sistema de tutoriales guiados
    console.log('Starting tutorial:', tutorialId);
};

const openModal = (modalId) => {
    // Implementar apertura de modales específicos
    console.log('Opening modal:', modalId);
};

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

// Mostrar mensaje automático de bienvenida después de unos segundos
onMounted(() => {
    setTimeout(() => {
        if (!isOpen.value && messages.value.length === 0) {
            hasNewMessage.value = true;
        }
    }, 5000);
});
</script>

<style scoped>
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

/* Scrollbar personalizada */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
</style>