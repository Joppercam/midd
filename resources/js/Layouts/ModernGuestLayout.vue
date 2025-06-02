<script setup>
import MiddLogo from '@/Components/MiddLogo.vue';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 relative overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0">
            <!-- Gradient Overlay -->
            <div class="absolute inset-0 bg-gradient-to-br from-white/60 via-blue-50/40 to-indigo-100/60"></div>
            
            <!-- Geometric Shapes -->
            <div class="absolute top-0 left-0 w-72 h-72 bg-gradient-to-br from-blue-400/10 to-purple-400/10 rounded-full -translate-x-32 -translate-y-32 blur-3xl"></div>
            <div class="absolute top-1/2 right-0 w-96 h-96 bg-gradient-to-br from-emerald-400/10 to-blue-400/10 rounded-full translate-x-32 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 w-80 h-80 bg-gradient-to-br from-purple-400/10 to-pink-400/10 rounded-full translate-y-32 blur-3xl"></div>
            
            <!-- Subtle Pattern -->
            <div class="absolute inset-0 opacity-[0.03]">
                <svg width="60" height="60" viewBox="0 0 60 60" class="w-full h-full">
                    <defs>
                        <pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse">
                            <path d="M 60 0 L 0 0 0 60" fill="none" stroke="currentColor" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>
        </div>

        <!-- Main Content -->
        <div class="relative z-10 min-h-screen flex">
            <!-- Left Side - Branding (Hidden on mobile) -->
            <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-center px-12 xl:px-20">
                <div class="max-w-md">
                    <!-- Logo grande -->
                    <div class="mb-8">
                        <MiddLogo :show-text="true" :show-tagline="true" text-size="large" />
                    </div>
                    
                    <!-- Texto principal -->
                    <h1 class="text-4xl xl:text-5xl font-bold text-gray-900 mb-6 leading-tight">
                        Gestiona tu <span class="bg-gradient-to-r from-emerald-600 to-blue-600 bg-clip-text text-transparent">empresa</span> de manera inteligente
                    </h1>
                    
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        Facturación electrónica, control de inventario, gestión de clientes y mucho más en una sola plataforma diseñada para pequeñas y medianas empresas.
                    </p>
                    
                    <!-- Features list -->
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-5 h-5 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span class="text-gray-700 font-medium">Facturación electrónica certificada SII</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-5 h-5 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span class="text-gray-700 font-medium">Control completo de inventario</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-5 h-5 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span class="text-gray-700 font-medium">Reportes financieros en tiempo real</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="w-full lg:w-1/2 xl:w-2/5 flex flex-col justify-center px-6 sm:px-12 lg:px-16 xl:px-20">
                <!-- Mobile Logo (Only visible on mobile) -->
                <div class="lg:hidden flex justify-center mb-8">
                    <MiddLogo :show-text="true" :show-tagline="true" text-size="medium" />
                </div>
                
                <!-- Form Card -->
                <div class="w-full max-w-sm mx-auto">
                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-white/20 p-8">
                        <slot />
                    </div>
                    
                    <!-- Footer (Mobile) -->
                    <div class="mt-8 text-center lg:hidden">
                        <p class="text-xs text-gray-500">
                            © 2025 MIDD. Todos los derechos reservados.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer (Desktop) -->
        <div class="hidden lg:block absolute bottom-6 left-12 xl:left-20">
            <p class="text-xs text-gray-500">
                © 2025 MIDD. Todos los derechos reservados.
            </p>
        </div>
    </div>
</template>