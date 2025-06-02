<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import ModernGuestLayout from '@/Layouts/ModernGuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const showDemoModal = ref(false);

// Detectar si estamos en desarrollo
const isDevelopment = import.meta.env.DEV || window.location.hostname === 'localhost';

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const demoForm = useForm({
    company_name: '',
    contact_name: '',
    email: '',
    phone: '',
    rut: '',
    business_type: '',
    employees: '',
    message: ''
});

const requestDemo = () => {
    showDemoModal.value = true;
};

const submitDemoRequest = () => {
    demoForm.post('/demo-request', {
        onSuccess: () => {
            showDemoModal.value = false;
            demoForm.reset();
            // Mostrar mensaje de éxito
        }
    });
};
</script>

<template>
    <ModernGuestLayout>
        <Head title="Iniciar Sesión" />

        <!-- Alert de estado -->
        <div v-if="status" class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl relative flex items-center" role="alert">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="block sm:inline">{{ status }}</span>
        </div>

        <!-- Título del formulario -->
        <div class="text-center mb-8">
            <h3 class="text-2xl font-bold text-gray-900 mb-2">¡Bienvenido de vuelta!</h3>
            <p class="text-gray-600">
                Ingresa a tu cuenta para continuar gestionando tu empresa
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Campo de Email -->
            <div class="space-y-2">
                <label for="email" class="block text-sm font-semibold text-gray-700">
                    Correo Electrónico
                </label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                        </svg>
                    </div>
                    <input
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        class="block w-full pl-12 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition-all duration-200 text-gray-900"
                        placeholder="superadmin@crecepyme.cl"
                    />
                </div>
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <!-- Campo de Contraseña -->
            <div class="space-y-2">
                <label for="password" class="block text-sm font-semibold text-gray-700">
                    Contraseña
                </label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        class="block w-full pl-12 pr-12 py-3.5 bg-gray-50 border border-gray-200 rounded-xl placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition-all duration-200 text-gray-900"
                        placeholder="••••••••"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 pr-4 flex items-center hover:bg-gray-50 rounded-r-xl transition-colors"
                    >
                        <svg v-if="!showPassword" class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg v-else class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <!-- Recordar y Olvidé contraseña -->
            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center">
                    <Checkbox 
                        id="remember"
                        name="remember" 
                        v-model:checked="form.remember"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label for="remember" class="ml-2 block text-sm text-gray-600 font-medium">
                        Recordarme
                    </label>
                </div>

                <div class="text-sm">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="font-semibold text-blue-600 hover:text-blue-500 transition duration-200"
                    >
                        ¿Olvidaste tu contraseña?
                    </Link>
                </div>
            </div>

            <!-- Botón de Login -->
            <div class="pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3.5 px-4 rounded-xl transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none shadow-lg hover:shadow-xl"
                >
                    <div class="flex items-center justify-center">
                        <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ form.processing ? 'Ingresando...' : 'Iniciar Sesión' }}</span>
                        <svg v-if="!form.processing" class="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </div>
                </button>
            </div>

            <!-- Separador -->
            <div class="relative mt-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 bg-white text-gray-500 font-medium">¿No tienes cuenta?</span>
                </div>
            </div>

            <!-- Solicitar Demo -->
            <div class="mt-6">
                <button
                    @click="requestDemo"
                    class="w-full flex items-center justify-center py-3.5 px-4 border-2 border-emerald-200 rounded-xl text-emerald-700 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all duration-200 font-semibold"
                >
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Solicitar Demo Personalizada
                </button>
            </div>
        </form>

        <!-- Demo disponible solo en desarrollo -->
        <div v-if="isDevelopment" class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
            <div class="flex">
                <svg class="h-5 w-5 text-amber-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-amber-900 mb-1">Acceso de Desarrollo</h4>
                    <p class="text-xs text-amber-700 mb-2">Este entorno es solo para desarrollo:</p>
                    <div class="text-xs text-amber-800 space-y-1 font-mono bg-amber-100 p-2 rounded-lg">
                        <div><strong>Email:</strong> superadmin@crecepyme.cl</div>
                        <div><strong>Contraseña:</strong> password</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información comercial -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 mb-3">
                ¿Eres una empresa y necesitas <strong>facturación electrónica certificada</strong>?
            </p>
            <div class="flex flex-col sm:flex-row gap-2 text-xs">
                <a href="mailto:ventas@crecepyme.cl" class="text-blue-600 hover:text-blue-500 font-medium">
                    📧 ventas@crecepyme.cl
                </a>
                <a href="tel:+56912345678" class="text-blue-600 hover:text-blue-500 font-medium">
                    📱 +56 9 1234 5678
                </a>
            </div>
        </div>

        <!-- Modal de Solicitud de Demo -->
        <Modal :show="showDemoModal" @close="showDemoModal = false" max-width="2xl">
            <div class="p-8">
                <div class="text-center mb-6">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 mb-4">
                        <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 002 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Solicitar Demo Personalizada</h3>
                    <p class="text-gray-600">Completa el formulario y nos contactaremos contigo en menos de 24 horas</p>
                </div>

                <form @submit.prevent="submitDemoRequest" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Empresa*</label>
                            <input 
                                v-model="demoForm.company_name"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                placeholder="Mi Empresa SPA"
                            />
                            <InputError :message="demoForm.errors.company_name" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">RUT de la Empresa*</label>
                            <input 
                                v-model="demoForm.rut"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                placeholder="12.345.678-9"
                            />
                            <InputError :message="demoForm.errors.rut" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Contacto*</label>
                            <input 
                                v-model="demoForm.contact_name"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                placeholder="Juan Pérez"
                            />
                            <InputError :message="demoForm.errors.contact_name" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correo Electrónico*</label>
                            <input 
                                v-model="demoForm.email"
                                type="email"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                placeholder="juan@miempresa.cl"
                            />
                            <InputError :message="demoForm.errors.email" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono*</label>
                            <input 
                                v-model="demoForm.phone"
                                type="tel"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                placeholder="+56 9 1234 5678"
                            />
                            <InputError :message="demoForm.errors.phone" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Número de Empleados</label>
                            <select 
                                v-model="demoForm.employees"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                            >
                                <option value="">Seleccionar...</option>
                                <option value="1-5">1-5 empleados</option>
                                <option value="6-20">6-20 empleados</option>
                                <option value="21-50">21-50 empleados</option>
                                <option value="51-100">51-100 empleados</option>
                                <option value="100+">Más de 100 empleados</option>
                            </select>
                            <InputError :message="demoForm.errors.employees" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Negocio</label>
                        <select 
                            v-model="demoForm.business_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        >
                            <option value="">Seleccionar...</option>
                            <option value="retail">Retail/Comercio</option>
                            <option value="services">Servicios</option>
                            <option value="manufacturing">Manufactura</option>
                            <option value="construction">Construcción</option>
                            <option value="restaurant">Restaurante/Gastronomía</option>
                            <option value="healthcare">Salud</option>
                            <option value="education">Educación</option>
                            <option value="other">Otro</option>
                        </select>
                        <InputError :message="demoForm.errors.business_type" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje Adicional</label>
                        <textarea 
                            v-model="demoForm.message"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                            placeholder="Cuéntanos sobre tus necesidades específicas o preguntas..."
                        ></textarea>
                        <InputError :message="demoForm.errors.message" />
                    </div>

                    <div class="flex space-x-3 pt-4">
                        <button
                            type="button"
                            @click="showDemoModal = false"
                            class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="demoForm.processing"
                            class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                        >
                            {{ demoForm.processing ? 'Enviando...' : 'Enviar Solicitud' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </ModernGuestLayout>
</template>
