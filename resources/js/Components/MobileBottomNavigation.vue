<template>
  <div class="sm:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
    <div class="grid grid-cols-5 h-16">
      <!-- Inicio -->
      <Link 
        :href="route('dashboard')"
        :class="[
          'flex flex-col items-center justify-center py-2 hover:bg-gray-50',
          route().current('dashboard') ? 'text-indigo-600' : 'text-gray-600'
        ]"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span class="text-xs mt-1">Inicio</span>
      </Link>

      <!-- Facturar -->
      <Link 
        :href="route('invoices.create')"
        :class="[
          'flex flex-col items-center justify-center py-2 hover:bg-gray-50',
          route().current('invoices.create') ? 'text-indigo-600' : 'text-gray-600'
        ]"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span class="text-xs mt-1">Facturar</span>
      </Link>

      <!-- Acción Principal (FAB) -->
      <div class="relative">
        <button 
          @click="showQuickMenu = !showQuickMenu"
          class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-14 h-14 bg-indigo-600 rounded-full shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-colors"
        >
          <svg class="w-6 h-6 text-white transition-transform duration-200" 
               :class="showQuickMenu ? 'rotate-45' : ''"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
        </button>
      </div>

      <!-- Productos -->
      <Link 
        :href="route('products.index')"
        :class="[
          'flex flex-col items-center justify-center py-2 hover:bg-gray-50',
          route().current('products.*') ? 'text-indigo-600' : 'text-gray-600'
        ]"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
        <span class="text-xs mt-1">Productos</span>
      </Link>

      <!-- Menú -->
      <button 
        @click="$emit('toggle-menu')"
        class="flex flex-col items-center justify-center py-2 hover:bg-gray-50 text-gray-600"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        <span class="text-xs mt-1">Menú</span>
      </button>
    </div>

    <!-- Quick Menu Flotante -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div v-if="showQuickMenu" 
           class="absolute bottom-20 left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-xl p-4 w-64">
        <h3 class="text-sm font-medium text-gray-900 mb-3">Acciones Rápidas</h3>
        <div class="space-y-2">
          <Link 
            :href="route('invoices.create')"
            class="flex items-center p-2 hover:bg-gray-50 rounded-md"
            @click="showQuickMenu = false"
          >
            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-900">Nueva Venta</p>
              <p class="text-xs text-gray-500">Crear factura o boleta</p>
            </div>
          </Link>

          <Link 
            :href="route('customers.create')"
            class="flex items-center p-2 hover:bg-gray-50 rounded-md"
            @click="showQuickMenu = false"
          >
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-900">Nuevo Cliente</p>
              <p class="text-xs text-gray-500">Agregar cliente</p>
            </div>
          </Link>

          <Link 
            :href="route('products.create')"
            class="flex items-center p-2 hover:bg-gray-50 rounded-md"
            @click="showQuickMenu = false"
          >
            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-900">Nuevo Producto</p>
              <p class="text-xs text-gray-500">Agregar al inventario</p>
            </div>
          </Link>

          <Link 
            :href="route('expenses.create')"
            class="flex items-center p-2 hover:bg-gray-50 rounded-md"
            @click="showQuickMenu = false"
          >
            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-900">Registrar Gasto</p>
              <p class="text-xs text-gray-500">Nuevo gasto o compra</p>
            </div>
          </Link>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const showQuickMenu = ref(false);

defineEmits(['toggle-menu']);
</script>

<style scoped>
/* Agregar padding bottom al contenido principal cuando se muestra la navegación */
</style>