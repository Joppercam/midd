<template>
  <Head title="Terminal de Ventas" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Terminal de Ventas (POS)
        </h2>
        <div class="flex space-x-2">
          <button
            @click="newSale"
            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva Venta
          </button>
          <button
            @click="openCashRegister"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Abrir Caja
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Panel de productos -->
          <div class="lg:col-span-2">
            <!-- Búsqueda y categorías -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
              <div class="p-6">
                <div class="flex space-x-4 mb-4">
                  <div class="flex-1">
                    <input
                      v-model="searchProduct"
                      type="text"
                      placeholder="Buscar producto por nombre, código o código de barras..."
                      @input="filterProducts"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                  </div>
                  <button
                    @click="scanBarcode"
                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V6a1 1 0 011-1h2a1 1 0 011 1v1a1 1 0 001 1h2a1 1 0 011-1V6a1 1 0 011-1h2a1 1 0 011 1v1a1 1 0 001 1h2" />
                    </svg>
                  </button>
                </div>
                
                <!-- Categorías -->
                <div class="flex flex-wrap gap-2">
                  <button
                    @click="selectedCategory = null"
                    :class="[
                      'px-3 py-1 rounded-full text-sm font-medium',
                      selectedCategory === null ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    ]"
                  >
                    Todos
                  </button>
                  <button
                    v-for="category in categories"
                    :key="category.id"
                    @click="selectedCategory = category.id"
                    :class="[
                      'px-3 py-1 rounded-full text-sm font-medium',
                      selectedCategory === category.id ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    ]"
                  >
                    {{ category.name }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Grid de productos -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                  <div 
                    v-for="product in filteredProducts" 
                    :key="product.id"
                    @click="addToCart(product)"
                    class="bg-white border-2 border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:shadow-md cursor-pointer transition-all"
                  >
                    <div class="aspect-square bg-gray-100 rounded-lg mb-3 flex items-center justify-center">
                      <img 
                        v-if="product.image"
                        :src="product.image"
                        :alt="product.name"
                        class="w-full h-full object-cover rounded-lg"
                      />
                      <svg v-else class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                      </svg>
                    </div>
                    <h3 class="font-medium text-sm text-gray-900 mb-1 line-clamp-2">{{ product.name }}</h3>
                    <p class="text-lg font-bold text-indigo-600">${{ formatCurrency(product.price) }}</p>
                    <p class="text-xs text-gray-500">Stock: {{ product.stock }}</p>
                  </div>
                </div>
                
                <!-- Empty state -->
                <div v-if="!filteredProducts.length" class="text-center py-8">
                  <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900">No hay productos</h3>
                  <p class="mt-1 text-sm text-gray-500">No se encontraron productos para mostrar.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Panel de carrito y pago -->
          <div class="lg:col-span-1">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Carrito de Compras</h3>
                
                <!-- Items del carrito -->
                <div class="space-y-3 mb-6 max-h-96 overflow-y-auto">
                  <div 
                    v-for="item in cart" 
                    :key="item.id"
                    class="flex items-center justify-between bg-gray-50 p-3 rounded-lg"
                  >
                    <div class="flex-1 min-w-0">
                      <h4 class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</h4>
                      <p class="text-sm text-gray-500">${{ formatCurrency(item.price) }} c/u</p>
                    </div>
                    <div class="flex items-center space-x-2">
                      <button
                        @click="decreaseQuantity(item)"
                        class="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center hover:bg-gray-300"
                      >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                      </button>
                      <span class="w-8 text-center text-sm font-medium">{{ item.quantity }}</span>
                      <button
                        @click="increaseQuantity(item)"
                        class="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center hover:bg-gray-300"
                      >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                      </button>
                      <button
                        @click="removeFromCart(item)"
                        class="w-6 h-6 bg-red-200 rounded-full flex items-center justify-center hover:bg-red-300 ml-2"
                      >
                        <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  
                  <!-- Empty cart -->
                  <div v-if="!cart.length" class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">El carrito está vacío</p>
                  </div>
                </div>

                <!-- Totales -->
                <div v-if="cart.length" class="border-t pt-4">
                  <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                      <span>Subtotal:</span>
                      <span>${{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                      <span>IVA ({{ taxRate }}%):</span>
                      <span>${{ formatCurrency(tax) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2">
                      <span>Total:</span>
                      <span>${{ formatCurrency(total) }}</span>
                    </div>
                  </div>

                  <!-- Cliente -->
                  <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select
                      v-model="selectedCustomer"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                      <option value="">Cliente General</option>
                      <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                        {{ customer.name }} - {{ customer.rut }}
                      </option>
                    </select>
                  </div>

                  <!-- Método de pago -->
                  <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago</label>
                    <select
                      v-model="paymentMethod"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                      <option value="cash">Efectivo</option>
                      <option value="card">Tarjeta</option>
                      <option value="transfer">Transferencia</option>
                    </select>
                  </div>

                  <!-- Botones de acción -->
                  <div class="mt-6 space-y-2">
                    <button
                      @click="processSale"
                      :disabled="!cart.length"
                      class="w-full bg-green-600 text-white py-3 rounded-md font-medium hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Procesar Venta - ${{ formatCurrency(total) }}
                    </button>
                    <button
                      @click="clearCart"
                      :disabled="!cart.length"
                      class="w-full bg-gray-600 text-white py-2 rounded-md font-medium hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Limpiar Carrito
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  products: Array,
  categories: Array,
  customers: Array,
});

// Estado del POS
const cart = ref([]);
const searchProduct = ref('');
const selectedCategory = ref(null);
const selectedCustomer = ref('');
const paymentMethod = ref('cash');
const taxRate = ref(19); // IVA en Chile

// Productos filtrados
const filteredProducts = computed(() => {
  let filtered = props.products;
  
  // Filtrar por categoría
  if (selectedCategory.value) {
    filtered = filtered.filter(product => product.category_id === selectedCategory.value);
  }
  
  // Filtrar por búsqueda
  if (searchProduct.value) {
    const search = searchProduct.value.toLowerCase();
    filtered = filtered.filter(product => 
      product.name.toLowerCase().includes(search) ||
      product.sku?.toLowerCase().includes(search) ||
      product.barcode?.toLowerCase().includes(search)
    );
  }
  
  return filtered;
});

// Cálculos del carrito
const subtotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

const tax = computed(() => {
  return subtotal.value * (taxRate.value / 100);
});

const total = computed(() => {
  return subtotal.value + tax.value;
});

// Funciones del carrito
const addToCart = (product) => {
  if (product.stock <= 0) {
    alert('Producto sin stock disponible');
    return;
  }
  
  const existingItem = cart.value.find(item => item.id === product.id);
  
  if (existingItem) {
    if (existingItem.quantity < product.stock) {
      existingItem.quantity++;
    } else {
      alert('No hay suficiente stock disponible');
    }
  } else {
    cart.value.push({
      id: product.id,
      name: product.name,
      price: product.price,
      quantity: 1,
      stock: product.stock
    });
  }
};

const removeFromCart = (item) => {
  const index = cart.value.findIndex(cartItem => cartItem.id === item.id);
  if (index > -1) {
    cart.value.splice(index, 1);
  }
};

const increaseQuantity = (item) => {
  if (item.quantity < item.stock) {
    item.quantity++;
  } else {
    alert('No hay suficiente stock disponible');
  }
};

const decreaseQuantity = (item) => {
  if (item.quantity > 1) {
    item.quantity--;
  } else {
    removeFromCart(item);
  }
};

const clearCart = () => {
  if (confirm('¿Está seguro de limpiar el carrito?')) {
    cart.value = [];
  }
};

// Funciones de POS
const newSale = () => {
  clearCart();
  selectedCustomer.value = '';
  paymentMethod.value = 'cash';
};

const processSale = () => {
  if (!cart.value.length) {
    alert('El carrito está vacío');
    return;
  }
  
  const saleData = {
    items: cart.value.map(item => ({
      product_id: item.id,
      quantity: item.quantity,
      price: item.price
    })),
    customer_id: selectedCustomer.value || null,
    payment_method: paymentMethod.value,
    subtotal: subtotal.value,
    tax: tax.value,
    total: total.value
  };
  
  router.post(route('pos.sales.store'), saleData, {
    preserveScroll: true,
    onSuccess: () => {
      alert('Venta procesada exitosamente');
      clearCart();
    },
    onError: (errors) => {
      console.error('Error al procesar la venta:', errors);
      alert('Error al procesar la venta');
    }
  });
};

const openCashRegister = () => {
  router.get(route('pos.cash-register.index'));
};

const scanBarcode = () => {
  // Implementación de escaneo de código de barras
  const barcode = prompt('Ingrese el código de barras:');
  if (barcode) {
    const product = props.products.find(p => p.barcode === barcode);
    if (product) {
      addToCart(product);
    } else {
      alert('Producto no encontrado');
    }
  }
};

const filterProducts = () => {
  // La función se ejecuta automáticamente gracias al computed filteredProducts
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-CL').format(amount);
};

// Cargar datos al montar el componente
onMounted(() => {
  // Aquí se podrían cargar datos adicionales si es necesario
});
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>