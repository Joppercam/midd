<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="transform opacity-0 scale-95"
        enter-to-class="transform opacity-100 scale-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="transform opacity-100 scale-100"
        leave-to-class="transform opacity-0 scale-95"
    >
        <div v-if="show" class="rounded-md p-4" :class="alertClasses">
            <div class="flex">
                <div class="flex-shrink-0">
                    <component :is="iconComponent" class="h-5 w-5" :class="iconClasses" aria-hidden="true" />
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium" :class="titleClasses">
                        {{ title || defaultTitle }}
                    </h3>
                    <div v-if="message" class="mt-2 text-sm" :class="messageClasses">
                        <p>{{ message }}</p>
                    </div>
                    
                    <!-- Lista de errores de validación -->
                    <div v-if="errors && Object.keys(errors).length > 0" class="mt-2">
                        <ul class="list-disc list-inside text-sm" :class="messageClasses">
                            <li v-for="(errorMessages, field) in errors" :key="field">
                                <span class="font-medium">{{ formatFieldName(field) }}:</span>
                                <span v-if="Array.isArray(errorMessages)">
                                    {{ errorMessages.join(', ') }}
                                </span>
                                <span v-else>{{ errorMessages }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- ID de error para soporte -->
                    <div v-if="errorId" class="mt-2 text-xs" :class="messageClasses">
                        <p>ID del error: <code class="font-mono bg-black bg-opacity-10 px-1 py-0.5 rounded">{{ errorId }}</code></p>
                    </div>

                    <!-- Acciones -->
                    <div v-if="actions && actions.length > 0" class="mt-4">
                        <div class="flex space-x-3">
                            <button
                                v-for="(action, index) in actions"
                                :key="index"
                                @click="action.handler"
                                type="button"
                                class="text-sm font-medium focus:outline-none focus:underline"
                                :class="action.class || actionClasses"
                            >
                                {{ action.label }}
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Botón de cerrar -->
                <div v-if="dismissible" class="ml-auto pl-3">
                    <div class="-mx-1.5 -my-1.5">
                        <button
                            @click="dismiss"
                            type="button"
                            class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2"
                            :class="closeButtonClasses"
                        >
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { CheckCircleIcon, ExclamationTriangleIcon, XCircleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    type: {
        type: String,
        default: 'error',
        validator: (value) => ['success', 'error', 'warning', 'info'].includes(value)
    },
    title: String,
    message: String,
    errors: Object,
    errorId: String,
    dismissible: {
        type: Boolean,
        default: true
    },
    autoDismiss: {
        type: Number,
        default: 0 // segundos, 0 = no auto dismiss
    },
    actions: Array
});

const emit = defineEmits(['dismiss']);

const show = ref(true);
let dismissTimer = null;

const dismiss = () => {
    show.value = false;
    emit('dismiss');
};

// Auto dismiss
onMounted(() => {
    if (props.autoDismiss > 0) {
        dismissTimer = setTimeout(() => {
            dismiss();
        }, props.autoDismiss * 1000);
    }
});

// Limpiar timer si el componente se destruye
watch(show, (newValue) => {
    if (!newValue && dismissTimer) {
        clearTimeout(dismissTimer);
    }
});

// Clases computadas según el tipo
const alertClasses = computed(() => {
    const classes = {
        success: 'bg-green-50 border border-green-200',
        error: 'bg-red-50 border border-red-200',
        warning: 'bg-yellow-50 border border-yellow-200',
        info: 'bg-blue-50 border border-blue-200'
    };
    return classes[props.type];
});

const iconClasses = computed(() => {
    const classes = {
        success: 'text-green-400',
        error: 'text-red-400',
        warning: 'text-yellow-400',
        info: 'text-blue-400'
    };
    return classes[props.type];
});

const titleClasses = computed(() => {
    const classes = {
        success: 'text-green-800',
        error: 'text-red-800',
        warning: 'text-yellow-800',
        info: 'text-blue-800'
    };
    return classes[props.type];
});

const messageClasses = computed(() => {
    const classes = {
        success: 'text-green-700',
        error: 'text-red-700',
        warning: 'text-yellow-700',
        info: 'text-blue-700'
    };
    return classes[props.type];
});

const actionClasses = computed(() => {
    const classes = {
        success: 'text-green-800 hover:text-green-600',
        error: 'text-red-800 hover:text-red-600',
        warning: 'text-yellow-800 hover:text-yellow-600',
        info: 'text-blue-800 hover:text-blue-600'
    };
    return classes[props.type];
});

const closeButtonClasses = computed(() => {
    const classes = {
        success: 'bg-green-50 text-green-500 hover:bg-green-100 focus:ring-green-600 focus:ring-offset-green-50',
        error: 'bg-red-50 text-red-500 hover:bg-red-100 focus:ring-red-600 focus:ring-offset-red-50',
        warning: 'bg-yellow-50 text-yellow-500 hover:bg-yellow-100 focus:ring-yellow-600 focus:ring-offset-yellow-50',
        info: 'bg-blue-50 text-blue-500 hover:bg-blue-100 focus:ring-blue-600 focus:ring-offset-blue-50'
    };
    return classes[props.type];
});

const iconComponent = computed(() => {
    const icons = {
        success: CheckCircleIcon,
        error: XCircleIcon,
        warning: ExclamationTriangleIcon,
        info: InformationCircleIcon
    };
    return icons[props.type];
});

const defaultTitle = computed(() => {
    const titles = {
        success: 'Operación exitosa',
        error: 'Ha ocurrido un error',
        warning: 'Advertencia',
        info: 'Información'
    };
    return titles[props.type];
});

// Helper para formatear nombres de campos
const formatFieldName = (field) => {
    return field
        .replace(/_/g, ' ')
        .replace(/\./g, ' ')
        .replace(/([A-Z])/g, ' $1')
        .trim()
        .replace(/\b\w/g, l => l.toUpperCase());
};
</script>