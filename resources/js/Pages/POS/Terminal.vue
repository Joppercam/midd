<template>
  <Head title="Terminal POS" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Terminal POS
        </h2>
        <div class="flex items-center space-x-4">
          <div class="text-sm text-gray-600">
            <span class="font-medium">Cajero:</span> {{ session.cashier }}
          </div>
          <div class="text-sm text-gray-600">
            <span class="font-medium">Terminal:</span> {{ session.terminal }}
          </div>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Product Selection Panel -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Search Bar -->
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex space-x-2">
                <div class="flex-1">
                  <input
                    v-model="searchTerm"
                    @input="searchProducts"
                    type="text"
                    placeholder="Buscar productos por nombre, código o código de barras..."
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>
                <button
                  @click="clearSearch"
                  class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                >
                  Limpiar
                </button>
              </div>
            </div>

            <!-- Category Filters -->
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex flex-wrap gap-2">
                <button
                  @click="filterByCategory(null)"
                  :class="[
                    'px-3 py-2 rounded-md text-sm font-medium',
                    selectedCategory === null 
                      ? 'bg-indigo-600 text-white' 
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                  ]"
                >
                  Todas
                </button>
                <button
                  v-for="category in categories"
                  :key="category.id"
                  @click="filterByCategory(category.id)"
                  :class="[
                    'px-3 py-2 rounded-md text-sm font-medium',
                    selectedCategory === category.id 
                      ? 'bg-indigo-600 text-white' 
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                  ]"
                >
                  {{ category.name }}
                </button>
              </div>
            </div>

            <!-- Products Grid -->
            <div class="bg-white rounded-lg shadow p-4">
              <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <div
                  v-for="product in filteredProducts"
                  :key="product.id"
                  @click="addToCart(product)"
                  class="border border-gray-200 rounded-lg p-3 hover:shadow-md cursor-pointer transition-shadow"
                >
                  <div class="aspect-square bg-gray-100 rounded-md mb-2 flex items-center justify-center">
                    <img
                      v-if="product.image"
                      :src="product.image"
                      :alt="product.name"
                      class="w-full h-full object-cover rounded-md"
                    />
                    <svg
                      v-else
                      class="w-8 h-8 text-gray-400"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                  </div>
                  <h3 class="text-sm font-medium text-gray-900 mb-1 line-clamp-2">{{ product.name }}</h3>
                  <p class="text-lg font-bold text-indigo-600">${{ formatPrice(product.price) }}</p>
                  <p class="text-xs text-gray-500">Stock: {{ product.quantity }}</p>
                </div>
              </div>
              
              <div v-if="!filteredProducts.length" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay productos</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron productos con los filtros aplicados.</p>
              </div>
            </div>
          </div>

          <!-- Cart Panel -->
          <div class="space-y-6">
            <!-- Customer Selection -->
            <div class="bg-white rounded-lg shadow p-4">
              <h3 class="text-lg font-medium text-gray-900 mb-3">Cliente</h3>
              <div class="space-y-2">
                <input
                  v-model="customerSearch"
                  @input="searchCustomers"
                  type="text"
                  placeholder="Buscar cliente..."
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <select
                  v-model="selectedCustomer"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                  <option :value="null">Cliente general</option>
                  <option
                    v-for="customer in filteredCustomers"
                    :key="customer.id"
                    :value="customer"
                  >
                    {{ customer.name }} - {{ customer.rut }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Cart Items -->
            <div class="bg-white rounded-lg shadow p-4">
              <h3 class="text-lg font-medium text-gray-900 mb-3">Carrito de Compras</h3>
              
              <div v-if="!cart.length" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13l-1.5-6m0 0L5.4 5M7 13h10M9 19a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Carrito vacío</h3>
                <p class="mt-1 text-sm text-gray-500">Agregue productos para comenzar la venta.</p>
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="(item, index) in cart"
                  :key="item.product_id"
                  class="flex items-center justify-between p-3 border border-gray-200 rounded-lg"
                >
                  <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</h4>
                    <p class="text-sm text-gray-500">${{ formatPrice(item.price) }} c/u</p>
                  </div>
                  <div class="flex items-center space-x-2">
                    <button
                      @click="updateQuantity(index, item.quantity - 1)"
                      class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center hover:bg-gray-300"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                      </svg>
                    </button>
                    <input
                      :value="item.quantity"
                      @change="updateQuantity(index, $event.target.value)"
                      type="number"
                      min="1"
                      class="w-16 text-center text-sm border-gray-300 rounded"
                    />
                    <button
                      @click="updateQuantity(index, item.quantity + 1)"
                      class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center hover:bg-gray-300"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                      </svg>
                    </button>
                    <button
                      @click="removeFromCart(index)"
                      class="text-red-600 hover:text-red-800"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sale Summary -->
            <div v-if="cart.length" class="bg-white rounded-lg shadow p-4">
              <h3 class="text-lg font-medium text-gray-900 mb-3">Resumen de Venta</h3>
              
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span>Subtotal:</span>
                  <span>${{ formatPrice(subtotal) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span>Descuento:</span>
                  <div class="flex items-center space-x-2">
                    <input
                      v-model="discount"
                      type="number"
                      min="0"
                      :max="subtotal"
                      step="0.01"
                      class="w-20 text-right text-sm border-gray-300 rounded"
                    />
                  </div>
                </div>
                <div class="flex justify-between text-sm">
                  <span>{{ settings.tax_name }} ({{ (settings.tax_rate * 100) }}%):</span>
                  <span>${{ formatPrice(tax) }}</span>
                </div>
                <div class="border-t pt-2 flex justify-between text-lg font-bold">
                  <span>Total:</span>
                  <span>${{ formatPrice(total) }}</span>
                </div>
              </div>

              <!-- Payment Method -->
              <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Método de Pago</label>
                <select
                  v-model="paymentMethod"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                  <option value="cash">Efectivo</option>
                  <option value="card">Tarjeta</option>
                  <option value="transfer">Transferencia</option>
                </select>
              </div>

              <!-- Payment Amount -->
              <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Monto Recibido</label>
                <input
                  v-model="paymentAmount"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <div v-if="change > 0" class="mt-2 text-sm text-green-600">
                  Vuelto: ${{ formatPrice(change) }}
                </div>
              </div>

              <!-- Process Sale Button -->
              <div class="mt-6 space-y-2">
                <button
                  @click="processSale"
                  :disabled="!canProcessSale"
                  :class="[
                    'w-full py-3 px-4 rounded-md font-medium',
                    canProcessSale
                      ? 'bg-green-600 text-white hover:bg-green-700'
                      : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  ]"
                >
                  <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                  Procesar Venta
                </button>
                <button
                  @click="clearCart"
                  class="w-full py-2 px-4 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                >
                  Limpiar Carrito
                </button>
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
  session: Object,
  settings: Object,
});

// Reactive data
const searchTerm = ref('');
const selectedCategory = ref(null);
const customerSearch = ref('');
const selectedCustomer = ref(null);
const cart = ref([]);
const discount = ref(0);
const paymentMethod = ref('cash');
const paymentAmount = ref(0);

// Computed properties
const filteredProducts = ref(props.products || []);
const filteredCustomers = ref(props.customers || []);

const subtotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

const tax = computed(() => {
  const taxableAmount = subtotal.value - discount.value;
  return taxableAmount * props.settings.tax_rate;
});

const total = computed(() => {
  return subtotal.value - discount.value + tax.value;
});

const change = computed(() => {
  return Math.max(0, paymentAmount.value - total.value);
});

const canProcessSale = computed(() => {
  return cart.value.length > 0 && paymentAmount.value >= total.value;
});

// Methods
const searchProducts = async () => {
  if (searchTerm.value.length < 2) {
    filteredProducts.value = props.products;
    return;
  }

  try {
    const response = await fetch(`/pos/terminal/search-products?search=${encodeURIComponent(searchTerm.value)}`);
    const products = await response.json();
    filteredProducts.value = products;
  } catch (error) {
    console.error('Error searching products:', error);
  }
};

const searchCustomers = async () => {
  if (customerSearch.value.length < 2) {
    filteredCustomers.value = props.customers;
    return;
  }

  try {
    const response = await fetch(`/pos/terminal/search-customers?search=${encodeURIComponent(customerSearch.value)}`);
    const customers = await response.json();
    filteredCustomers.value = customers;
  } catch (error) {
    console.error('Error searching customers:', error);
  }
};

const filterByCategory = async (categoryId) => {
  selectedCategory.value = categoryId;
  
  try {
    const url = categoryId 
      ? `/pos/terminal/products-by-category/${categoryId}`
      : '/pos/terminal/products-by-category/all';
    
    const response = await fetch(url);
    const products = await response.json();
    filteredProducts.value = products;
  } catch (error) {
    console.error('Error filtering by category:', error);
  }
};

const clearSearch = () => {
  searchTerm.value = '';
  filteredProducts.value = props.products;
};

const addToCart = (product) => {
  const existingIndex = cart.value.findIndex(item => item.product_id === product.id);
  
  if (existingIndex >= 0) {
    cart.value[existingIndex].quantity += 1;
  } else {
    cart.value.push({
      product_id: product.id,
      name: product.name,
      price: product.price,
      quantity: 1,
    });
  }
};

const updateQuantity = (index, newQuantity) => {
  const qty = parseInt(newQuantity);
  if (qty <= 0) {
    removeFromCart(index);
  } else {
    cart.value[index].quantity = qty;
  }
};

const removeFromCart = (index) => {
  cart.value.splice(index, 1);
};

const clearCart = () => {
  cart.value = [];
  selectedCustomer.value = null;
  discount.value = 0;
  paymentAmount.value = 0;
};

const processSale = async () => {
  if (!canProcessSale.value) return;

  const saleData = {
    items: cart.value,
    customer_id: selectedCustomer.value?.id,
    payment_method: paymentMethod.value,
    payment_amount: paymentAmount.value,
    discount: discount.value,
    notes: null,
  };

  try {
    const response = await fetch('/pos/terminal/sale', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
      },
      body: JSON.stringify(saleData),
    });

    const result = await response.json();

    if (result.success) {
      alert(`Venta procesada exitosamente!\nNúmero de venta: ${result.sale.sale_number}\nTotal: $${formatPrice(result.sale.total)}`);
      clearCart();
    } else {
      alert('Error al procesar la venta: ' + result.message);
    }
  } catch (error) {
    console.error('Error processing sale:', error);
    alert('Error al procesar la venta. Intente nuevamente.');
  }
};

const formatPrice = (price) => {
  return parseFloat(price || 0).toLocaleString('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
};

// Set initial payment amount when total changes
onMounted(() => {
  // Auto-set payment amount to total when total changes
  const unwatch = computed(() => total.value, (newTotal) => {
    if (paymentMethod.value === 'card' || paymentMethod.value === 'transfer') {
      paymentAmount.value = newTotal;
    }
  });
});
</script>