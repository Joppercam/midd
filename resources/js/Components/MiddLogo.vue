<template>
  <div class="flex items-center">
    <!-- Logo SVG -->
    <div class="relative">
      <!-- Fondo del logo con gradiente -->
      <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 via-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
        <!-- Icono principal - Gráfico de crecimiento con elementos de negocios -->
        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" 
                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
        </svg>
        
        <!-- Punto decorativo -->
        <div class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-400 rounded-full shadow-sm"></div>
      </div>
      
      <!-- Efecto de brillo -->
      <div class="absolute inset-0 bg-gradient-to-tr from-white/20 to-transparent rounded-xl"></div>
    </div>
    
    <!-- Nombre de la empresa (opcional, controlado por props) -->
    <div v-if="showText" class="ml-3">
      <h1 :class="textSize === 'large' ? 'text-2xl' : textSize === 'medium' ? 'text-xl' : 'text-lg'" 
          class="font-bold bg-gradient-to-r from-emerald-600 via-blue-600 to-purple-600 bg-clip-text text-transparent">
        MIDD
      </h1>
      <p v-if="showTagline" class="text-xs text-gray-500 font-medium -mt-1">
        Sistema de Gestión Empresarial
      </p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  showText: {
    type: Boolean,
    default: true
  },
  showTagline: {
    type: Boolean,
    default: false
  },
  textSize: {
    type: String,
    default: 'medium', // small, medium, large
    validator: (value) => ['small', 'medium', 'large'].includes(value)
  }
});
</script>

<style scoped>
/* Animación sutil para el logo */
.logo-container:hover .logo-icon {
  transform: scale(1.05);
  transition: transform 0.2s ease-in-out;
}
</style>