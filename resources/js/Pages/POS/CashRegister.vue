<template>
  <Head title="Caja Registradora" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Caja Registradora
        </h2>
        <div class="flex space-x-2">
          <Link
            :href="route('pos.terminal.index')"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Ir al Terminal
          </Link>
          <Link
            :href="route('pos.sales.index')"
            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2-2z" />
            </svg>
            Ver Ventas
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Estado de la sesión actual -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">Estado de Sesión Actual</h3>
            <div class="flex items-center space-x-2">
              <div :class="[
                'w-3 h-3 rounded-full',
                currentSession.status === 'open' ? 'bg-green-500' : 'bg-red-500'
              ]"></div>
              <span :class="[
                'text-sm font-medium',
                currentSession.status === 'open' ? 'text-green-700' : 'text-red-700'
              ]">
                {{ currentSession.status === 'open' ? 'Sesión Abierta' : 'Sesión Cerrada' }}
              </span>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-sm font-medium text-gray-500">Cajero</h4>
              <p class="text-lg font-semibold text-gray-900">{{ currentSession.cashier }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-sm font-medium text-gray-500">Hora de Apertura</h4>
              <p class="text-lg font-semibold text-gray-900">{{ formatTime(currentSession.opened_at) }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-sm font-medium text-gray-500">Saldo Inicial</h4>
              <p class="text-lg font-semibold text-gray-900">${{ formatPrice(currentSession.opening_balance) }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-sm font-medium text-gray-500">Saldo Actual</h4>
              <p class="text-lg font-semibold text-green-600">${{ formatPrice(currentSession.current_balance) }}</p>
            </div>
          </div>
        </div>

        <!-- Resumen de ventas del día -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-blue-100 rounded-md">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Ventas de Hoy</p>
                <p class="text-2xl font-semibold text-gray-900">{{ todayStats.total_sales }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-green-100 rounded-md">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Efectivo</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatPrice(todayStats.cash_total) }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-purple-100 rounded-md">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Tarjetas</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatPrice(todayStats.card_total) }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0 p-3 bg-yellow-100 rounded-md">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Transferencias</p>
                <p class="text-2xl font-semibold text-gray-900">${{ formatPrice(todayStats.transfer_total) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          
          <!-- Abrir/Cerrar caja -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Gestión de Sesión</h3>
            
            <div v-if="currentSession.status === 'closed'" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Saldo de Apertura</label>
                <input
                  v-model="openingBalance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Notas (opcional)</label>
                <textarea
                  v-model="openingNotes"
                  rows="3"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="Notas de apertura..."
                ></textarea>
              </div>
              <button
                @click="openSession"
                class="w-full py-2 px-4 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium"
              >
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Abrir Caja
              </button>
            </div>

            <div v-else class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Efectivo Final Contado</label>
                <input
                  v-model="closingCash"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Notas de Cierre</label>
                <textarea
                  v-model="closingNotes"
                  rows="3"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="Notas de cierre..."
                ></textarea>
              </div>
              <button
                @click="closeSession"
                class="w-full py-2 px-4 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium"
              >
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Cerrar Caja
              </button>
            </div>
          </div>

          <!-- Movimientos de efectivo -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Movimientos de Efectivo</h3>
            
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Movimiento</label>
                <select
                  v-model="movementType"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                  <option value="">Seleccionar tipo</option>
                  <option value="income">Ingreso de Efectivo</option>
                  <option value="expense">Retiro de Efectivo</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Monto</label>
                <input
                  v-model="movementAmount"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Concepto</label>
                <input
                  v-model="movementConcept"
                  type="text"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="Descripción del movimiento"
                />
              </div>
              <button
                @click="addMovement"
                :disabled="!canAddMovement"
                :class="[
                  'w-full py-2 px-4 rounded-md font-medium',
                  canAddMovement
                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                ]"
              >
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Registrar Movimiento
              </button>
            </div>
          </div>

          <!-- Acciones adicionales -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Acciones Adicionales</h3>
            
            <div class="space-y-3">
              <button
                @click="printCashReport"
                class="w-full py-2 px-4 bg-gray-600 text-white rounded-md hover:bg-gray-700 font-medium text-left"
              >
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Imprimir Reporte de Caja
              </button>
              
              <button
                @click="viewCashMovements"
                class="w-full py-2 px-4 bg-gray-600 text-white rounded-md hover:bg-gray-700 font-medium text-left"
              >
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2-2z" />
                </svg>
                Ver Movimientos del Día
              </button>
              
              <button
                @click="reconcileCash"
                class="w-full py-2 px-4 bg-gray-600 text-white rounded-md hover:bg-gray-700 font-medium text-left"
              >
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Cuadrar Caja
              </button>
            </div>
          </div>
        </div>

        <!-- Historial de movimientos recientes -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Movimientos Recientes</h3>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Hora
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tipo
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Concepto
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Monto
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Estado
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="movement in recentMovements" :key="movement.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatTime(movement.created_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="[
                      'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                      movement.type === 'sale' ? 'bg-green-100 text-green-800' :
                      movement.type === 'income' ? 'bg-blue-100 text-blue-800' :
                      movement.type === 'expense' ? 'bg-red-100 text-red-800' :
                      'bg-gray-100 text-gray-800'
                    ]">
                      {{ getMovementTypeLabel(movement.type) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ movement.concept }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <span :class="[
                      movement.type === 'expense' ? 'text-red-600' : 'text-green-600'
                    ]">
                      {{ movement.type === 'expense' ? '-' : '+' }}${{ formatPrice(movement.amount) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                      Registrado
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
            
            <div v-if="!recentMovements.length" class="text-center py-8">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2-2z" />
              </svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900">No hay movimientos</h3>
              <p class="mt-1 text-sm text-gray-500">No se han registrado movimientos en el día de hoy.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Estado de la sesión actual
const currentSession = ref({
  status: 'open', // open | closed
  cashier: 'Juan Pérez',
  opened_at: '2023-12-01 09:00:00',
  opening_balance: 50000,
  current_balance: 125000,
});

// Estadísticas del día
const todayStats = ref({
  total_sales: 15,
  cash_total: 145000,
  card_total: 89000,
  transfer_total: 45000,
});

// Datos del formulario
const openingBalance = ref(50000);
const openingNotes = ref('');
const closingCash = ref(0);
const closingNotes = ref('');

// Movimientos de efectivo
const movementType = ref('');
const movementAmount = ref(0);
const movementConcept = ref('');

// Movimientos recientes (datos de ejemplo)
const recentMovements = ref([
  {
    id: 1,
    type: 'sale',
    concept: 'Venta POS-20231201001',
    amount: 125990,
    created_at: '2023-12-01 14:30:00',
  },
  {
    id: 2,
    type: 'income',
    concept: 'Cambio de billetes',
    amount: 20000,
    created_at: '2023-12-01 13:15:00',
  },
  {
    id: 3,
    type: 'expense',
    concept: 'Compra de insumos',
    amount: 5000,
    created_at: '2023-12-01 12:00:00',
  },
  {
    id: 4,
    type: 'sale',
    concept: 'Venta POS-20231201002',
    amount: 89990,
    created_at: '2023-12-01 15:45:00',
  },
]);

// Computed properties
const canAddMovement = computed(() => {
  return movementType.value && movementAmount.value > 0 && movementConcept.value.trim();
});

// Métodos
const openSession = () => {
  if (openingBalance.value < 0) {
    alert('El saldo de apertura debe ser mayor o igual a 0');
    return;
  }

  currentSession.value = {
    status: 'open',
    cashier: 'Juan Pérez',
    opened_at: new Date().toISOString(),
    opening_balance: openingBalance.value,
    current_balance: openingBalance.value,
  };

  // Reset form
  openingBalance.value = 50000;
  openingNotes.value = '';

  alert('Sesión de caja abierta exitosamente');
};

const closeSession = () => {
  if (!closingCash.value && closingCash.value !== 0) {
    alert('Debe ingresar el efectivo final contado');
    return;
  }

  const expectedCash = currentSession.value.current_balance;
  const difference = closingCash.value - expectedCash;

  let confirmMessage = `Resumen de cierre:\n`;
  confirmMessage += `Efectivo esperado: $${formatPrice(expectedCash)}\n`;
  confirmMessage += `Efectivo contado: $${formatPrice(closingCash.value)}\n`;
  
  if (difference !== 0) {
    confirmMessage += `Diferencia: ${difference > 0 ? '+' : ''}$${formatPrice(Math.abs(difference))}\n`;
  }
  
  confirmMessage += `\n¿Confirmar cierre de sesión?`;

  if (confirm(confirmMessage)) {
    currentSession.value.status = 'closed';
    
    // Reset form
    closingCash.value = 0;
    closingNotes.value = '';
    
    alert('Sesión de caja cerrada exitosamente');
  }
};

const addMovement = () => {
  if (!canAddMovement.value) return;

  const newMovement = {
    id: recentMovements.value.length + 1,
    type: movementType.value,
    concept: movementConcept.value,
    amount: parseFloat(movementAmount.value),
    created_at: new Date().toISOString(),
  };

  recentMovements.value.unshift(newMovement);

  // Update current balance
  if (movementType.value === 'income') {
    currentSession.value.current_balance += newMovement.amount;
  } else if (movementType.value === 'expense') {
    currentSession.value.current_balance -= newMovement.amount;
  }

  // Reset form
  movementType.value = '';
  movementAmount.value = 0;
  movementConcept.value = '';

  alert('Movimiento registrado exitosamente');
};

const printCashReport = () => {
  alert('Función de impresión de reporte en desarrollo');
};

const viewCashMovements = () => {
  alert('Vista detallada de movimientos en desarrollo');
};

const reconcileCash = () => {
  alert('Función de cuadre de caja en desarrollo');
};

// Helpers
const formatPrice = (price) => {
  return parseFloat(price || 0).toLocaleString('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
};

const formatTime = (datetime) => {
  if (!datetime) return '-';
  return new Date(datetime).toLocaleTimeString('es-CL', {
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getMovementTypeLabel = (type) => {
  const labels = {
    sale: 'Venta',
    income: 'Ingreso',
    expense: 'Retiro',
    opening: 'Apertura',
    closing: 'Cierre',
  };
  return labels[type] || type;
};
</script>