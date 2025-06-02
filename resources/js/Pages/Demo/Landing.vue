<template>
    <Head title="Demo MIDD - Prueba Gratis" />
    
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-emerald-400 via-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-gray-900">MIDD</span>
                        <span class="bg-emerald-100 text-emerald-800 text-xs font-semibold px-2 py-1 rounded-full">DEMO</span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="mailto:ventas@crecepyme.cl" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                            Contacto
                        </a>
                        <Link href="/login" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            Iniciar Sesión
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                        Prueba <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">MIDD</span> 
                        <br>sin compromiso
                    </h1>
                    <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                        Explora todas las funcionalidades de la plataforma de gestión empresarial más completa de Chile. 
                        Facturación electrónica, inventario, reportes y mucho más.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
                        <button
                            @click="startDemo"
                            :disabled="isStartingDemo"
                            class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold px-8 py-4 rounded-xl transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl"
                        >
                            <div class="flex items-center">
                                <svg v-if="isStartingDemo" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m-7 2h6m-6-8h12a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2v-8a2 2 0 012-2z"/>
                                </svg>
                                {{ isStartingDemo ? 'Preparando Demo...' : 'Iniciar Demo Gratis' }}
                            </div>
                        </button>
                        
                        <div class="text-sm text-gray-500 flex items-center">
                            <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            30 minutos • Sin registro • Acceso inmediato
                        </div>
                    </div>

                    <!-- Demo Info -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 max-w-4xl mx-auto border border-gray-100">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-1">30 minutos</h3>
                                <p class="text-sm text-gray-600">Tiempo completo para explorar</p>
                            </div>
                            <div class="text-center">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-1">Datos reales</h3>
                                <p class="text-sm text-gray-600">Ambiente con datos de ejemplo</p>
                            </div>
                            <div class="text-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-2.05-.235l-3.95 1.185 1.185-3.95A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"/>
                                    </svg>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-1">Asistencia</h3>
                                <p class="text-sm text-gray-600">Chatbot que te guía paso a paso</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        Todo lo que necesitas para hacer crecer tu PyME
                    </h2>
                    <p class="text-xl text-gray-600">
                        Una plataforma completa que se adapta a las necesidades de tu empresa
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow border border-gray-100"
                    >
                        <div class="text-4xl mb-4">{{ feature.icon }}</div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ feature.title }}</h3>
                        <p class="text-gray-600">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        Miles de PyMEs confían en nosotros
                    </h2>
                    <p class="text-xl text-gray-600">
                        Descubre por qué somos la opción preferida
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div
                        v-for="testimonial in testimonials"
                        :key="testimonial.name"
                        class="bg-white rounded-xl p-6 shadow-sm border border-gray-100"
                    >
                        <div class="flex mb-4">
                            <svg
                                v-for="star in 5"
                                :key="star"
                                class="w-5 h-5 text-yellow-400"
                                :class="star <= testimonial.rating ? 'text-yellow-400' : 'text-gray-300'"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </div>
                        <blockquote class="text-gray-600 mb-4">"{{ testimonial.text }}"</blockquote>
                        <div>
                            <div class="font-semibold text-gray-900">{{ testimonial.name }}</div>
                            <div class="text-sm text-gray-500">{{ testimonial.company }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    ¿Listo para revolucionar tu empresa?
                </h2>
                <p class="text-xl text-blue-100 mb-8">
                    Únete a las miles de PyMEs que ya están creciendo con nuestra plataforma
                </p>
                <button
                    @click="startDemo"
                    :disabled="isStartingDemo"
                    class="bg-white hover:bg-gray-50 text-blue-600 font-semibold px-8 py-4 rounded-xl transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                >
                    <div class="flex items-center">
                        <svg v-if="isStartingDemo" class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isStartingDemo ? 'Preparando tu demo...' : 'Comenzar Demo Ahora' }}
                    </div>
                </button>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-white py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div>
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-8 h-8 bg-gradient-to-br from-emerald-400 via-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <span class="text-xl font-bold">MIDD</span>
                        </div>
                        <p class="text-gray-400">
                            La plataforma de gestión empresarial que hace crecer tu PyME.
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Producto</h3>
                        <ul class="space-y-2 text-gray-400">
                            <li><a href="#" class="hover:text-white transition-colors">Funcionalidades</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Precios</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Integraciones</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Soporte</h3>
                        <ul class="space-y-2 text-gray-400">
                            <li><a href="#" class="hover:text-white transition-colors">Documentación</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Centro de Ayuda</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Contacto</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Contacto</h3>
                        <ul class="space-y-2 text-gray-400">
                            <li>ventas@crecepyme.cl</li>
                            <li>+56 9 1234 5678</li>
                            <li>Santiago, Chile</li>
                        </ul>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                    <p>&copy; 2025 MIDD. Todos los derechos reservados.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    config: Object,
    features: Array,
    testimonials: Array,
});

const isStartingDemo = ref(false);

const startDemo = async () => {
    isStartingDemo.value = true;
    
    try {
        // Enviar solicitud para iniciar demo
        router.post('/demo/start', {}, {
            onSuccess: () => {
                // El usuario será redirigido automáticamente al dashboard del demo
            },
            onError: (errors) => {
                console.error('Error starting demo:', errors);
                alert('Error al iniciar el demo. Por favor, intenta nuevamente.');
            },
            onFinish: () => {
                isStartingDemo.value = false;
            }
        });
    } catch (error) {
        console.error('Error:', error);
        isStartingDemo.value = false;
    }
};
</script>